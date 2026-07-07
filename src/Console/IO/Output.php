<?php

namespace Potager\Console\IO;

class Output
{
    private const RESET  = "\033[0m";
    private const GREEN  = "\033[32m";
    private const RED    = "\033[31m";
    private const YELLOW = "\033[33m";
    private const CYAN   = "\033[36m";
    private const BOLD   = "\033[1m";
    private const DIM    = "\033[2m";
    private const WHITE  = "\033[37m";

    private bool $ansi;

    public function __construct(bool $ansi = true)
    {
        $this->ansi = $ansi && $this->supportsAnsi();
    }

    private function supportsAnsi(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT);
        }
        return stream_isatty(STDOUT);
    }

    public function format(string $text, string ...$styles): string
    {
        if (!$this->ansi) {
            return $text;
        }

        $codes = array_map(fn(string $style) => match ($style) {
            'green'  => self::GREEN,
            'red'    => self::RED,
            'yellow' => self::YELLOW,
            'cyan'   => self::CYAN,
            'bold'   => self::BOLD,
            'dim'    => self::DIM,
            'white'  => self::WHITE,
            default  => '',
        }, $styles);

        return implode('', $codes) . $text . self::RESET;
    }

    public function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        $this->line($this->format($message, 'cyan'));
    }

    public function success(string $message): void
    {
        $this->line($this->format($message, 'green'));
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $this->format($message, 'red') . PHP_EOL);
    }

    public function warning(string $message): void
    {
        $this->line($this->format($message, 'yellow'));
    }

    public function newLine(int $count = 1): void
    {
        echo str_repeat(PHP_EOL, $count);
    }

    public function table(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $separator = '+' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . '+';

        $this->line($separator);

        $headerRow = '|';
        foreach ($headers as $i => $header) {
            $headerRow .= ' ' . $this->format(str_pad($header, $widths[$i]), 'bold') . ' |';
        }
        $this->line($headerRow);
        $this->line($separator);

        foreach ($rows as $row) {
            $rowLine = '|';
            foreach (array_values($row) as $i => $cell) {
                $rowLine .= ' ' . str_pad((string) $cell, $widths[$i]) . ' |';
            }
            $this->line($rowLine);
        }

        $this->line($separator);
    }

    public function ask(string $question): string
    {
        echo $this->format($question, 'cyan') . ' ';
        return rtrim((string) fgets(STDIN), "\n\r");
    }

    public function confirm(string $question, bool $default = false): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo $this->format($question, 'cyan') . " {$hint} ";
        $answer = strtolower(rtrim((string) fgets(STDIN), "\n\r"));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes'], true);
    }
}
