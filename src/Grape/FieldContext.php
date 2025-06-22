<?php

namespace Potager\Grape;

use PDO;

class FieldContext
{

    // The current value of the field under validation
    private mixed $value;
    // The root element under validation
    private mixed $root;
    // The sanitized output
    private mixed $sanitized;
    // The name (key) of the field under validation (index in case of array)
    private string|int|float|null $name;
    // A boolean set to false if validation failed
    private bool $valid;
    // The parent element of the field undervalidation when nested field, root otherwize
    private ?FieldContext $parent;
    // The path to the fueld under validation
    private array $path;
    // The error reporter
    private ErrorReporter $errorReporter;

    private ?PDO $pdo;


    public function __construct(mixed &$value, mixed &$root = null, mixed &$sanitized = null, $name = null, ?FieldContext $parent = null, $path = [], $errorReporter = null, $pdo = null)
    {
        $this->value = &$value;

        $this->root = &$root;
        if ($root === null) {
            $this->root = &$this->value;
        }

        if ($sanitized === null) {
            $sanitized = [];
        }
        $this->sanitized = &$sanitized;

        $this->name = $name;
        $this->parent = $parent;
        $this->valid = true;
        $this->path = $name !== null ? [...$path, $name] : $path;
        $this->errorReporter = $errorReporter ?? new ErrorReporter();

        $this->pdo = $pdo ?? Grape::getPDO() ?? null;
    }



    public function getValue()
    {
        return $this->value;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getMessages()
    {
        return $this->errorReporter->messages;
    }

    public function isValid()
    {
        return $this->valid;
    }

    public function getPath()
    {
        return implode('.', $this->path);
    }

    public function getSanitized()
    {
        return $this->sanitized;
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    public function mutate($value)
    {
        $this->value = $value;
    }

    public function sanitize()
    {
        $current = &$this->sanitized;
        foreach ($this->path as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current = $this->getValue();
    }

    public function report(string $message, string $rule)
    {
        $this->valid = false;

        $field = $this->getPath();
        $message = str_replace('{{ field }}', $this->getName(), $message);

        $this->errorReporter->report($field, $rule, $message);
    }

    public function getNestedContext(int|float|string $key)
    {
        if (is_array($this->value) && array_key_exists($key, $this->value)) {
            $nestedValue = &$this->value[$key];
        } else {
            $null = null;
            $nestedValue = &$null;
        }

        return new FieldContext($nestedValue, $this->root, $this->sanitized, $key, $this, $this->path, $this->errorReporter, $this->pdo);
    }


}