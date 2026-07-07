<?php

namespace Potager\Contracts\Console;

use Potager\Console\IO\InputDefinition;

interface InputInterface
{
    public function getScriptName(): ?string;

    public function getCommandName(): ?string;

    public function setCommandName(?string $name): void;

    public function getArguments(): array;

    public function argument(string $name): mixed;

    public function hasArgument(string $name): bool;

    public function setArgument(string $name, mixed $value): void;

    public function getOptions(): array;

    public function option(string $name): mixed;

    public function hasOption(string $name): bool;

    public function setOption(string $name, mixed $value): void;

    public function isInteractive(): bool;

    public function setInteractive(bool $interactive): void;

    public function bindDefinition(InputDefinition $definition): void;

    public function validate(): void;

    public function hasRawOption(string|array $options, bool $optionsOnly = false): bool;

    public function getRawOption(string|array $options, mixed $default = null, bool $optionsOnly = false): mixed;
}
