<?php

namespace Potager\Limpid\Definitions;

/**
 * Class ModelMeta
 *
 * Represents metadata for a model, including its table name,
 * column definitions, and computed properties.
 *
 * @package Potager\Limpid\Metadata
 */
class ModelDefinition
{
    /**
     * The database table name associated with the model.
     * @var string
     */
    public string $table;

    /**
     * The primary key Column
     * @var ColumnDefinition
     */
    public ColumnDefinition $primary;

    /**
     * Array of column metadata indexed by column property name.
     * @var array<string, ColumnDefinition>
     */
    public array $columns = [];

    /**
     * Array of computed property metadata indexed by property name.
     * @var array<string, ComputedDefinition>
     */
    public array $computeds = [];

    /**
     * Get the table name.
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key
     * @return ColumnDefinition
     */
    public function getPrimary(): ColumnDefinition
    {
        return $this->primary;
    }

    /**
     * Get all column metadata.
     * @return array<string, ColumnDefinition>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get all computed metadata.
     * @return array<string, ComputedDefinition>
     */
    public function getComputeds(): array
    {
        return $this->computeds;
    }

    /**
     * Get the ColumnMeta instance for a given column name.
     * @param string $name The name of the column.
     * @return ColumnDefinition|null The corresponding ColumnMeta, or null if it doesn't exist.
     */
    public function getColumn(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name || $column->property === $name) {
                return $column;
            }
        }
        return null;
    }

    /**
     * Get the ComputedMeta instance for a given computed property name.
     * @param string $name The name of the computed property.
     * @return ComputedDefinition|null The corresponding ComputedMeta, or null if it doesn't exist.
     */
    public function getComputed(string $name): ?ComputedDefinition
    {
        return $this->computeds[$name] ?? null;
    }

    /**
     * Get the names of all defined columns.
     * @return string[]
     */
    public function getColumnsNames(): array
    {
        return array_values(array_map(fn($col) => $col->name, $this->columns));
    }

    /**
     * Get the names of all defined properties that maps to a column.
     * @return string[]
     */
    public function getColumnsPropertiesNames(): array
    {
        return array_keys($this->columns);
    }

    /**
     * Get the names of all defined computed properties.
     * @return string[]
     */
    public function getComputedsNames(): array
    {
        return array_keys($this->computeds);
    }

    /**
     * Check if the given name corresponds to a defined column.
     * @param string $name
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name || $column->property === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the given name corresponds to a defined computed property.
     * @param string $name
     * @return bool
     */
    public function hasComputed(string $name): bool
    {
        return array_key_exists($name, $this->computeds);
    }

    /**
     * Convert the model metadata into an associative array.
     * @return array
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'primary' => $this->primary,
            'columns' => array_map(fn(ColumnDefinition $col) => [
                'name' => $col->name,
                'type' => $col->type,
                'isPrimary' => $col->isPrimary,
            ], $this->columns),
            'computeds' => array_map(fn(ComputedDefinition $comp) => [
                'property' => $comp->property,
                'resolver' => $comp->resolver,
            ], $this->computeds),
        ];
    }
}
