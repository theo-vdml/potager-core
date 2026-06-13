<?php

use Potager\Limpid\Definitions\ColumnDefinition;
use Potager\Limpid\Definitions\ModelDefinition;
use Potager\Test\Models\ModelWithCustomPrimary;
use Potager\Test\Models\ModelWithDefaultPrimary;
use Potager\Test\Models\ModelExample;

test('Model returns a valid ModelDefinition instance', function () {
    $definition = ModelExample::getDefinition();
    expect($definition)->toBeInstanceOf(ModelDefinition::class);
});

test('Table name is resolved from #[Table] attribute', function () {
    $definition = \Potager\Test\Models\ModelWithTableNameAttribute::getDefinition();
    expect($definition->table)->toBe('custom_table');
});

test('Table name is resolved from static tableName() method', function () {
    $definition = \Potager\Test\Models\ModelWithTableNameMethod::getDefinition();
    expect($definition->table)->toBe('from_static_method');
});

test('Table name falls back to snake pluralized class name', function () {
    $definition = \Potager\Test\Models\ModelWithoutTableName::getDefinition();
    expect($definition->table)->toBe('model_without_table_names');
});

test('Model definition exposes column metadata as an array', function () {
    $definition = ModelExample::getDefinition();
    expect($definition->getColumns())->toBeArray();
    expect($definition->getColumns())->toContainOnlyInstancesOf(ColumnDefinition::class);
});


test('Model definition sets the correct primary key', function () {
    $definition = ModelWithCustomPrimary::getDefinition();
    expect($definition->getPrimary()->name)->toBe('uuid');
});

test('Model definition fallback on the default primary key', function () {
    $definition = ModelWithDefaultPrimary::getDefinition();
    expect($definition->getPrimary()->name)->toBe('id');
});

test('Model definition includes all declared column names', function () {
    $definition = ModelExample::getDefinition();
    expect($definition->getColumnsPropertiesNames())->toBe([
        'uuid',
        'firstName',
        'lastName',
        'email',
    ]);
    expect($definition->getColumnsNames())->toBe([
        'uuid',
        'first_name',
        'last_name',
        'email',
    ]);
});

test('Model definition includes all declared computed names', function () {
    $definition = ModelExample::getDefinition();
    expect($definition->getComputedsNames())->toBe([
        'fullName',
        'greating'
    ]);
});

test('Computed property is mapped to the correct resolver method', function () {
    $definition = ModelExample::getDefinition();
    $computed = $definition->getComputed('fullName');
    expect($computed->resolver)->toBe('computeFullName');
});

test('Computed property is mapped to the custom resolver method', function () {
    $definition = ModelExample::getDefinition();
    $computed = $definition->getComputed('greating');
    expect($computed->resolver)->toBe('sayGreating');
});

test('Throws on using both #[Column] and #[Computed] on same property', function () {
    expect(fn() => \Potager\Test\Models\ModelWithInvalidCombination::getDefinition())
        ->toThrow(\Potager\Limpid\Exceptions\InvalidAttributeCombinationException::class);
});

test('Throws on duplicate column definition', function () {
    expect(fn() => \Potager\Test\Models\ModelWithDuplicateColumn::getDefinition())
        ->toThrow(\Potager\Limpid\Exceptions\DuplicateColumnException::class);
});

test('Throws on multiple primary keys', function () {
    expect(fn() => \Potager\Test\Models\ModelWithMultiplePrimaryKeys::getDefinition())
        ->toThrow(\Potager\Limpid\Exceptions\MultiplePrimaryKeyException::class);
});

test('Throws when computed has no resolver method', function () {
    expect(fn() => \Potager\Test\Models\ModelWithMissingComputedResolver::getDefinition())
        ->toThrow(\Potager\Limpid\Exceptions\MissingComputedResolverException::class);
});

test('Throws when computed resolver is not public', function () {
    expect(fn() => \Potager\Test\Models\ModelWithPrivateComputedResolver::getDefinition())
        ->toThrow(\Potager\Limpid\Exceptions\InvalidComputedResolverVisibilityException::class);
});