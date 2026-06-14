<?php

namespace Potager\Limpid\Boot;

use DateTime;
use Exception;
use Potager\Limpid\Attributes\Table;
use Potager\Limpid\Contracts\DataTransformer;
use Potager\Limpid\Definitions\ColumnDefinition;
use Potager\Limpid\Definitions\ComputedDefinition;
use Potager\Limpid\Definitions\ModelDefinition;
use Potager\Limpid\Exceptions\DuplicateColumnException;
use Potager\Limpid\Exceptions\InvalidAttributeCombinationException;
use Potager\Limpid\Exceptions\InvalidBootMethodException;
use Potager\Limpid\Exceptions\InvalidColumnMethodException;
use Potager\Limpid\Exceptions\InvalidComputedResolverVisibilityException;
use Potager\Limpid\Exceptions\MissingComputedResolverException;
use Potager\Limpid\Attributes\Column;
use Potager\Limpid\Attributes\Computed;
use Potager\Limpid\Attributes\Hook;
use Potager\Limpid\Exceptions\MissingPrimaryKeyException;
use Potager\Limpid\Exceptions\MultiplePrimaryKeyException;
use Potager\Limpid\Model;
use Potager\Limpid\Transformers\ArrayTransformer;
use Potager\Limpid\Transformers\BooleanTransformer;
use Potager\Limpid\Transformers\DateTimeTransformer;
use Potager\Limpid\Transformers\DefaultTransformer;
use Potager\Support\Str;
use Potager\Support\Utils;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;


/**
 * Responsible for bootstrapping Limpid model metadata,
 * parsing properties, methods, and traits to generate
 * a ModelMeta structure, register hooks, and invoke boot methods.
 */
class ModelBooter
{
    /**
     * Boot a given model class by analyzing its metadata,
     * registering computed properties and hooks, and invoking
     * boot methods for traits and the model itself.
     *
     * @param string $modelClass Fully-qualified model class name
     * @return ModelDefinition The booted model's metadata
     * @throws MissingComputedResolverException If a Computed property has no resolver method
     */
    public static function boot(string $modelClass): ModelDefinition
    {
        $reflection = new ReflectionClass($modelClass);
        $meta = new ModelDefinition();

        /**
         * ─────────────────────────────────────────────────────────────
         * TABLE NAME RESOLUTION
         * ─────────────────────────────────────────────────────────────
         * Determine the database table name for the model.
         *
         * Resolution order:
         *   1. #[Table("custom_name")] attribute on the class
         *   2. static tableName(): string method on the model
         *   3. Defaults to snake_case + pluralized class base name
         *      e.g., PostCategory → post_categories
         */
        $table = null;
        $tableAttr = $reflection->getAttributes(Table::class)[0] ?? null;
        if ($tableAttr) {
            $table = $tableAttr->newInstance()->name;
        } else if ($reflection->hasMethod('tableName')) {
            $method = $reflection->getMethod('tableName');
            $returnType = $method->getReturnType();
            $isStringReturn = $returnType instanceof \ReflectionNamedType && $returnType->getName() === 'string';
            if ($method->isStatic() && $isStringReturn) {
                $table = $method->invoke(null);
            } else {
                // TODO : Add a WARNING log
                // Message: Model {model name} defines 'tableName' but it must be a static method with return type of string. Ignored.
            }
        }
        $meta->table = $table ?? Str::pluralize(Str::toSnakeCase(Utils::classBasename($modelClass)));

        /**
         * ─────────────────────────────────────────────────────────────
         * PROPERTY PARSING: COLUMNS & COMPUTEDS
         * ─────────────────────────────────────────────────────────────
         * Loop through all class properties to extract metadata.
         *
         * - #[Column]: Registered in $meta->columns as ColumnMeta
         * - #[Computed]: Registered in $meta->computeds as ComputedMeta
         *   - Resolver method is required (explicit or inferred)
         *   - Missing resolvers will throw MissingComputedResolverException
         * - Ensure there is a primary key
         */

        $primaryKeyAssigned = false;

        /** @var ReflectionProperty $property */
        foreach ($reflection->getProperties() as $property) {

            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();

            $reflectionType = $property->getType();
            $propertyType = null;

            if ($reflectionType instanceof \ReflectionNamedType) {
                $propertyType = $reflectionType->getName();
            } else if ($reflectionType instanceof \ReflectionUnionType) {
                foreach ($reflectionType->getTypes() as $type) {
                    if ($type instanceof \ReflectionNamedType) {
                        if ($type->getName() !== 'null') {
                            $propertyType = $type->getName();
                            break;
                        }
                    }
                }
            }

            // $propertyType = $property->getType()?->getName() ?? null;
            $attrs = self::extractAttributes($property);

            // Prevent using both Column AND Computed on the same property
            if (isset($attrs[Column::class]) && isset($attrs[Computed::class])) {
                throw new InvalidAttributeCombinationException($modelClass, $propertyName);
            }

            // Handle #[Column] attribute
            if (isset($attrs[Column::class])) {
                /** @var Column $column */
                $column = $attrs[Column::class]();
                $definition = static::processColumnAttribute($reflection, $propertyName, $propertyType, $column);
                if ($meta->hasColumn($definition->name)) {
                    $existing = $meta->getColumn($definition->name);
                    throw new DuplicateColumnException($modelClass, $existing->property, $propertyName, $definition->name);
                }
                if ($definition->isPrimary) {
                    if ($primaryKeyAssigned)
                        throw new MultiplePrimaryKeyException($modelClass, $meta->primary->property, $propertyName);
                    $primaryKeyAssigned = true;
                    $meta->primary = $definition;
                }
                $meta->columns[$propertyName] = $definition;
            }
            // Handle #[Computed] attribute
            else if (isset($attrs[Computed::class])) {
                /** @var Computed $computed */
                $computed = $attrs[Computed::class]();
                $resolver = $computed->getResolver() ?? Str::toCamelCase("compute {$propertyName}");
                // Ensure Resolver Exists
                if (!$reflection->hasMethod($resolver))
                    throw new MissingComputedResolverException($modelClass, $propertyName, $resolver);
                $resolverMethod = $reflection->getMethod($resolver);
                // Ensure Resolver is public
                if (!$resolverMethod->isPublic()) {
                    throw new InvalidComputedResolverVisibilityException($modelClass, $propertyName, $resolver);
                }
                $meta->computeds[$propertyName] = new ComputedDefinition(
                    property: $propertyName,
                    resolver: $resolver
                );
            }
        }

        /**
         * Ensure the model has a primary key assigned.
         * If no primary key is explicitly assigned, check for the default 'id' column.
         * Throws an exception if the 'id' column is not found.
         * Sets the primary key to the 'id' column if it exists.
         */
        if (!$primaryKeyAssigned) {
            if (!$meta->hasColumn('id')) {
                throw new MissingPrimaryKeyException();
            }
            $meta->primary = $meta->getColumn('id');
            $meta->primary->isPrimary = true; // Ensure primary flag is set
        }

        /**
         * ─────────────────────────────────────────────────────────────
         * METHOD PARSING: HOOKS
         * ─────────────────────────────────────────────────────────────
         * Loop through all class methods and extract #[Hook] attributes.
         *
         * For each #[Hook], register the associated callback to the model’s
         * lifecycle event via Model::registerHookOn().
         * 
         * Callback is automatically bound to the model instance (unless static).
         */
        /** @var ReflectionMethod $method */
        foreach ($reflection->getMethods() as $method) {
            $attrs = self::extractAttributes($method);

            // Handle #[Hook] attribute
            if (isset($attrs[Hook::class])) {
                /** @var Hook $hook */
                $hook = $attrs[Hook::class]();
                foreach ($hook->getEvents() as $event) {
                    $callback = function ($model) use ($method) {
                        $method->setAccessible(true);
                        $method->invoke($method->isStatic() ? null : $model, $model);
                    };
                    Model::registerHookOn($modelClass, $event, $callback, $hook->getPriority());
                }
            }
        }

        /**
         * ─────────────────────────────────────────────────────────────
         * TRAIT BOOTING
         * ─────────────────────────────────────────────────────────────
         * If the model uses traits and defines boot methods for them,
         * automatically call them using the naming convention:
         *   boot{TraitName}()
         *
         * e.g., bootSoftDeletes() for use SoftDeletes;
         */
        foreach ($reflection->getTraits() as $trait) {
            /** @var ReflectionClass $trait */
            $traitName = $trait->getName();
            $traitBaseName = Utils::classBasename($traitName);
            $traitBootMethod = Str::toCamelCase("boot {$traitBaseName}");
            if ($reflection->hasMethod($traitBootMethod)) {
                $method = $reflection->getMethod($traitBootMethod);
                $hasRequiredParameters = array_filter($method->getParameters(), fn($param) => !$param->isOptional());
                if (!$method->isStatic() || !$method->isPublic() || count($hasRequiredParameters) > 0) {
                    throw new InvalidBootMethodException($modelClass, $traitBootMethod);
                }
                $method->invoke(null);
            }
        }


        /**
         * ─────────────────────────────────────────────────────────────
         * MODEL BOOT METHOD
         * ─────────────────────────────────────────────────────────────
         * Check if the model defines a boot method for itself using:
         *   boot{ClassName}()
         *
         * If so, invoke it (assumed static).
         */
        $classBaseName = Utils::classBasename($modelClass);
        $bootMethod = Str::toCamelCase("boot {$classBaseName}");
        if ($reflection->hasMethod($bootMethod)) {
            $method = $reflection->getMethod($bootMethod);
            $hasRequiredParameters = array_filter($method->getParameters(), fn($param) => !$param->isOptional());
            if (!$method->isStatic() || !$method->isPublic() || count($hasRequiredParameters) > 0) {
                throw new InvalidBootMethodException($modelClass, $bootMethod);
            }
            $method->invoke(null);
        }

        /**
         * ─────────────────────────────────────────────────────────────
         * DONE
         * ─────────────────────────────────────────────────────────────
         * Return the fully constructed metadata structure.
         */
        return $meta;
    }

    /**
     * Processes a property annotated with a Column attribute and returns its corresponding ColumnDefinition.
     *
     * @param ReflectionClass $reflection The reflection of the class containing the property.
     * @param string $property The name of the property being processed.
     * @param string $type The type of the property (e.g., 'int', 'string', 'DateTime', etc.).
     * @param Column $column The Column attribute instance attached to the property.
     *
     * @return ColumnDefinition The generated ColumnDefinition based on the provided metadata.
     *
     * @throws Exception If the 'prepare' or 'consume' method is defined but not public and non-static.
     */
    protected static function processColumnAttribute(ReflectionClass $reflection, string $property, string $type, Column $column): ColumnDefinition
    {
        // Determine the column name to use in the database, defaulting to snake_case version of the property name
        $name = $column->name ?? Str::toSnakeCase($property);
        // Validate the 'prepare' method: it must exist and be a public, non-static method
        if ($column->prepare && !static::isPublicNonStaticMethodDefined($reflection, $column->prepare)) {
            throw InvalidColumnMethodException::prepareMethodNotCallable($property, $column->prepare);
        }
        // Validate the 'consume' method: it must exist and be a public, non-static method
        if ($column->consume && !static::isPublicNonStaticMethodDefined($reflection, $column->consume)) {
            throw InvalidColumnMethodException::consumeMethodNotCallable($property, $column->consume);
        }
        // Build and return the ColumnDefinition object with appropriate metadata
        return new ColumnDefinition(
            name: $name,
            property: $property,
            type: $type,
            isPrimary: $column->isPrimary(),
            prepare: $column->prepare,
            consume: $column->consume,
            transformer: $column->transformer ?? static::getDefaultTransformer($type)
        );
    }

    /**
     * Checks whether the given method exists on the class and is both public and non-static.
     *
     * @param ReflectionClass $reflection The reflection instance of the class to inspect.
     * @param string $method The name of the method to check.
     *
     * @return bool True if the method exists, is public, and is not static; false otherwise.
     */
    protected static function isPublicNonStaticMethodDefined(ReflectionClass $reflection, string $method): bool
    {
        if (!$reflection->hasMethod($method)) {
            return false;
        }
        $refMethod = $reflection->getMethod($method);
        return $refMethod->isPublic() && !$refMethod->isStatic();
    }

    /**
     * Returns a default transformer instance based on the given type.
     *
     * This is used to automatically assign a data transformer to a column
     * when none is explicitly provided.
     *
     * Supported types:
     * - "array" => ArrayTransformer
     * - "bool"  => BooleanTransformer
     * - DateTime::class => DateTimeTransformer
     *
     * @param string|null $type The data type to get the transformer for (e.g., 'array', 'bool', DateTime::class).
     *
     * @return DataTransformer An instance of the corresponding transformer
     */
    protected static function getDefaultTransformer(?string $type = null): DataTransformer
    {
        return match ($type) {
            "array" => new ArrayTransformer(),
            "bool" => new BooleanTransformer(),
            DateTime::class => new DateTimeTransformer(),
            default => new DefaultTransformer()
        };
    }


    /**
     * Extract and defer instantiation of attributes from a property or method.
     * Uses lazy-loading via closures to minimize overhead.
     *
     * @param ReflectionProperty|ReflectionMethod $reflector
     * @return array<class-string, \Closure(): object> Lazily-instantiable attribute map
     */
    private static function extractAttributes(ReflectionProperty|ReflectionMethod $reflector): array
    {
        $attrs = [];
        foreach ($reflector->getAttributes() as $attr) {
            $attrs[$attr->getName()] = fn(): object => $attr->newInstance();
        }
        return $attrs;
    }
}
