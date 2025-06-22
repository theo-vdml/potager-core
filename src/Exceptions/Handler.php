<?php

namespace Potager\Exceptions;

use DohFormatting\Doh\Doh;
use ErrorException;
use Exception;
use Potager\Router\HttpContext;
use Potager\Router\Request;
use Psr\Container\ContainerInterface;
use Throwable;
use Psr\Log\LoggerInterface;

/**
 * Class Handler
 *
 * Handles PHP errors, exceptions, and shutdown errors in a unified way.
 */
class Handler
{

    private HttpContext $context;
    private Request $request;

    /**
     * Handler constructor.
     *
     * @param \Psr\Container\ContainerInterface $container
     * @param ?LoggerInterface $logger PSR-3 logger instance used for logging errors.
     * @param bool $isDev Whether the application is in development mode (controls verbosity).
     */
    public function __construct(
        private ContainerInterface $container,
        private ?LoggerInterface $logger = null,
        private bool $isDev = false,
    ) {
        $this->context = $this->container->get(HttpContext::class);
        $this->request = $this->context->request();
    }

    /**
     * Handles a PHP error and converts it into an ErrorException.
     *
     * @param int $severity The severity level of the error.
     * @param string $message The error message.
     * @param string $file The filename where the error occurred.
     * @param int $line The line number where the error occurred.
     * @return bool True if the error was handled.
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $exception = new ErrorException($message, 0, $severity, $file, $line);

        $level = $this->mapSeverityToLogLevel($severity);

        $this->logger?->log($level, $message, [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
        ]);

        $this->processThrowable($exception);
        return true;
    }

    /**
     * Handles uncaught exceptions.
     *
     * @param Exception $exception The exception to handle.
     * @return bool True if the exception was handled.
     */
    public function handleUncaughtException(Throwable $exception): bool
    {
        if ($exception instanceof HttpException) {
            $this->processHttpException($exception);
            return true;
        }

        $this->logger?->error($exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->processThrowable($exception);
        return true;
    }

    /**
     * Handles fatal errors on script shutdown.
     *
     * @return bool True if a fatal error was handled.
     */
    public function handleFatalShutdown(): bool
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger?->critical($error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]);

            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $this->processThrowable($exception);
        }
        return true;
    }

    /**
     * Processes a throwable depending on environment mode.
     *
     * In production, responds with a generic message.
     * In development, includes stack trace.
     *
     * @param \Throwable $throwable The caught exception or error.
     * @return void
     */
    protected function processThrowable(Throwable $throwable): void
    {
        if (!$this->isDev) {
            $exception = new HttpException(500);
            http_response_code(500);
            $this->respondWithoutStackTrace($exception);
            return;
        }

        http_response_code(500);
        $this->respondWithStackTrace($throwable);
        exit;
    }

    /**
     * Handles HttpException separately.
     *
     * @param \Potager\Exceptions\HttpException $exception
     * @return void
     */
    protected function processHttpException(HttpException $exception): void
    {
        $this->logger?->warning($exception->getMessage(), [
            'exception' => $exception,
            'status_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if (!$this->isDev) {
            http_response_code($exception->getCode());
            $this->respondWithoutStackTrace($exception);
            exit;
        }

        if ($exception->getCode() < 500) {
            http_response_code($exception->getCode());
            $this->respondWithoutStackTrace($exception);
            exit;
        }

        http_response_code($exception->getCode());
        $this->respondWithStackTrace($exception);
        exit;
    }

    /**
     * Maps a PHP error severity to a PSR-3 log level.
     *
     * @param int $severity The PHP error severity constant.
     * @return string The corresponding PSR-3 log level.
     */
    protected function mapSeverityToLogLevel(int $severity): string
    {
        return match (true) {
            $severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR) => 'error',
            $severity & (E_WARNING | E_USER_WARNING) => 'warning',
            $severity & (E_NOTICE | E_USER_NOTICE) => 'notice',
            $severity & (E_DEPRECATED | E_USER_DEPRECATED) => 'info',
            default => 'debug',
        };
    }

    /**
     * Responds with a detailed stack trace depending on Accept header.
     *
     * @param \Throwable $throwable The exception to display.
     * @return void
     */
    protected function respondWithStackTrace(Throwable $throwable)
    {
        $formatter = new Doh($throwable);
        $priorities = ['text/html', 'application/json', 'text/plain'];
        $best = $this->request->accepts($priorities);
        $type = $best?->getType();

        if ($type === 'text/html') {
            $html = $formatter->toHtml();
            echo $html;
            return;
        }

        if ($type === 'application/json') {
            $json = $formatter->toJson();
            echo $json;
            return;
        }

        $text = $formatter->toPlainText();
        echo $text;
        return;
    }

    /**
     * Responds with a clean, user-facing error message
     * (no stack trace), based on Accept header.
     *
     * @param \Throwable $throwable The exception to respond with.
     * @return void
     */
    protected function respondWithoutStackTrace(Throwable $throwable)
    {
        $priorities = ['text/html', 'application/json', 'text/plain'];
        $best = $this->request->accepts($priorities);
        $type = $best?->getType();

        $message = $throwable->getMessage();
        $statusCode = $throwable->getCode();

        if ($type === 'application/json') {
            echo json_encode([
                'error' => $message,
                'status' => $statusCode,
            ]);
            return;
        }

        if ($type === 'text/html') {
            echo "<html><head><title>Error</title></head><body><h1>{$statusCode} Error</h1><p>{$message}</p></body></html>";
            return;
        }

        echo "{$statusCode} Error: {$message}";
        return;
    }
}