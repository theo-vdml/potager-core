<?php

namespace Potager\Exceptions;

use ErrorException;
use Exception;
use Throwable;

use Psr\Log\LoggerInterface;
use DohFormatting\Doh\Doh;

use Potager\Config;
use Potager\LatteEngine;
use Potager\Router\Request;

/**
 * Handles all error, exception, and shutdown logic for the application.
 *
 * Converts PHP errors into exceptions, logs issues via PSR-3, and renders
 * appropriate error responses depending on the environment and request type.
 *
 * Supports HTML, JSON, and plain-text responses based on the client's Accept header.
 */

class Handler
{
    /**
     * Indicates if the application is running in development mode.
     *
     * Affects whether detailed error information (stack traces) is shown
     * or a simplified user-friendly message is returned.
     *
     * @var bool
     */
    private bool $isDevelopmentMode = false;

    /**
     * Initializes the error handler with dependencies.
     *
     * @param Config $config Application configuration.
     * @param Request $request The current HTTP request.
     * @param LatteEngine $latteEngine Template rendering engine.
     * @param LoggerInterface|null $logger Optional PSR-3 logger.
     */
    public function __construct(
        private Config $config,
        private Request $request,
        private LatteEngine $latteEngine,
        private ?LoggerInterface $logger = null,
    ) {
        $this->isDevelopmentMode = $this->config->get('environment', 'production') === 'dev';
    }

    /**
     * Converts a standard PHP error into a catchable ErrorException and processes it.
     *
     * Logs the error based on severity and renders an appropriate response
     * (stack trace or simplified error view depending on environment).
     *
     * @param int $severity PHP error severity constant (e.g. E_WARNING, E_NOTICE).
     * @param string $message The error message.
     * @param string $file Filename where the error occurred.
     * @param int $line Line number in the file where the error occurred.
     * @return bool True if the error was processed; false if it was suppressed by error_reporting.
     */
    public function handlePhpErrorAsException(int $severity, string $message, string $file, int $line): bool
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

        $this->renderThrowableResponse($exception);
        return true;
    }

    /**
     * Handles uncaught exceptions (Throwable) that were not previously caught.
     *
     * Differentiates between HttpException and general exceptions. Logs the error
     * and returns either a developer stack trace or a user-facing error depending on the environment.
     *
     * @param Throwable $exception The uncaught exception or error.
     * @return bool Always true after processing.
     */
    public function handleUnhandledException(Throwable $exception): bool
    {
        if ($exception instanceof HttpException) {
            $this->renderHttpExceptionResponse($exception);
            return true;
        }

        $this->logger?->error($exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->renderThrowableResponse($exception);
        return true;
    }

    /**
     * Catches fatal shutdown errors (e.g. out-of-memory, parse errors) using error_get_last().
     *
     * If a fatal error is found, it is converted into an ErrorException and processed
     * similarly to an uncaught exception. Non-fatal shutdowns are ignored.
     *
     * @return bool True if a fatal error was detected and handled.
     */
    public function handleShutdownFatalError(): bool
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger?->critical($error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]);

            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $this->renderThrowableResponse($exception);
        }
        return true;
    }

    /**
     * Determines the appropriate way to render a Throwable based on the environment mode.
     *
     * In development mode, displays a detailed error with stack trace (HTML/JSON/Text).
     * In production, responds with a simplified message using an HttpException wrapper.
     *
     * @param Throwable $throwable The exception or error to render.
     * @return void
     */
    protected function renderThrowableResponse(Throwable $throwable): void
    {
        if (!$this->isDevelopmentMode) {
            $exception = new HttpException(500);
            http_response_code(500);
            $this->renderUserFriendlyError($exception);
            return;
        }

        http_response_code(500);
        $this->renderDetailedError($throwable);
        exit;
    }

    /**
     * Handles HttpException instances by logging and rendering an appropriate response.
     *
     * Determines the correct output (detailed vs. user-friendly) based on the environment
     * and the HTTP status code. In development mode, shows stack traces for server errors (5xx),
     * but renders user-friendly views for client errors (4xx). In production, always renders
     * simplified error views without trace.
     *
     * @param HttpException $exception The HTTP-related exception to process and respond to.
     * @return void
     */
    protected function renderHttpExceptionResponse(HttpException $exception): void
    {
        $this->logger?->warning($exception->getMessage(), [
            'exception' => $exception,
            'status_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if (!$this->isDevelopmentMode) {
            http_response_code($exception->getCode());
            $this->renderUserFriendlyError($exception);
            exit;
        }

        if ($exception->getCode() < 500) {
            http_response_code($exception->getCode());
            $this->renderUserFriendlyError($exception);
            exit;
        }

        http_response_code($exception->getCode());
        $this->renderDetailedError($exception);
        exit;
    }

    /**
     * Renders a detailed debug output of a Throwable using Doh formatter.
     *
     * The response format (HTML, JSON, or plain text) is negotiated via the request's Accept header.
     * This method is intended only for development environments to assist debugging.
     *
     * @param Throwable $throwable The exception or error to be formatted and displayed.
     * @return void
     */
    protected function renderDetailedError(Throwable $throwable)
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
     * Sends a production-safe error response without stack traces,
     * formatted according to the client's Accept header.
     *
     * Supports HTML (via template rendering), JSON, or plain text.
     *
     * @param Throwable $throwable The exception or error to render.
     * @return void
     */
    protected function renderUserFriendlyError(Throwable $throwable)
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
            $this->renderCustomErrorView($statusCode, $message);
            return;
        }

        echo "{$statusCode} Error: {$message}";
        return;
    }

    /**
     * Attempts to render an HTML error view using predefined Latte templates.
     *
     * Tries multiple fallback templates based on specificity (e.g., 404, 40x, 4xx, generic).
     * If no view is found, a plain HTML error message is rendered directly.
     *
     * @param int $status HTTP status code (e.g. 404, 500).
     * @param string $message A human-readable error message to include in the response.
     * @return void
     */

    protected function renderCustomErrorView(int $status, string $message)
    {

        $code = (string) $status;

        $candidates = [
            "errors.{$code}",
            "errors.{$code[0]}{$code[1]}x",
            "errors.{$code[0]}xx",
            "errors.http",
            __DIR__ . "/ressources/http.latte"
        ];

        foreach ($candidates as $view) {
            if ($this->latteEngine->viewExists($view)) {
                echo $this->latteEngine->render($view, [
                    'status' => $status,
                    'message' => $message
                ]);
                return;
            }
        }

        $statusHtml = htmlspecialchars((string) $status);
        $messageHtml = htmlspecialchars($message);
        echo "<html><head><title>Error</title></head><body><h1>{$statusHtml} Error</h1><p>{$messageHtml}</p></body></html>";

    }

    /**
     * Translates a PHP native error severity into a PSR-3 compatible log level string.
     *
     * This allows proper categorization of PHP runtime errors in PSR-3-compliant logs.
     * For example, E_WARNING maps to "warning", E_NOTICE to "notice", etc.
     *
     * @param int $severity A PHP error constant (e.g., E_WARNING, E_NOTICE).
     * @return string Corresponding PSR-3 log level: 'error', 'warning', 'notice', 'info', or 'debug'.
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
}