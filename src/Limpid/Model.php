<?php

namespace Potager\Limpid;

use PDOStatement;
use Potager\Limpid\Boot\ModelBooter;
use Potager\Limpid\Definitions\ColumnDefinition;
use Potager\Limpid\Definitions\ComputedDefinition;
use Potager\Limpid\Definitions\ModelDefinition;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Potager\Limpid\Exceptions\MissingComputedResolverException;
use Potager\Support\Utils;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base abstract model class for ORM functionality.
 * Handles model definition loading, attributes, and lifecycle tracking.
 */
abstract class Model
{

    /**
     * The database instance or a closure that resolves to a database instance.
     * Using a closure allows for lazy-loading the database connection, 
     * ensuring that the PDO connection is only established when an ORM 
     * operation is actually performed.
     *
     * @var Database|\Closure|null
     */
    protected static Database|\Closure|null $_databaseResolver = null;

    /**
     * Sets the database resolver for the ORM.
     *
     * This method binds the database connection to the model statically.
     * It accepts either a direct Database instance (ideal for mocking in tests)
     * or a Closure that returns a Database instance (ideal for production lazy-loading).
     *
     * @param \Closure|Database|null $resolver The database instance or a resolver closure.
     * @return void
     */
    public static function setDatabaseResolver(\Closure|Database|null $resolver): void
    {
        static::$_databaseResolver = $resolver;
    }

    /**
     * Resolves and returns the active database instance.
     *
     * If the underlying resolver is a Closure, it executes it to retrieve 
     * the Database instance. If it's already a Database instance, it returns 
     * it directly.
     *
     * @return Database The resolved database instance.
     * @throws RuntimeException If the database resolver has not been initialized.
     */
    public static function getDatabase(): Database
    {
        if (!static::$_databaseResolver) {
            throw new RuntimeException("Database instance not initialized. Call " . static::class . "::setDatabaseResolver() before using ORM operations.");
        }

        return (static::$_databaseResolver instanceof \Closure) ? (static::$_databaseResolver)() : static::$_databaseResolver;
    }

    /* 
     * -----------------------------------------------------------------------------------------------------------------
     *                                 PROPERTY DECLARATIONS & STATIC CACHES
     * -----------------------------------------------------------------------------------------------------------------
     *
     * These properties include all instance attributes, state flags, and static caches used
     * to store model definitions and event hooks. They serve as the backbone to track the
     * model's internal state and metadata, and optimize lookups across instances.
     *
     * - $_definitions: caches model definitions per class for efficient reuse.
     * - $_hooks: stores event hooks by class and event.
     * - $_persisted: tracks if instance is saved in DB.
     * - $_originals: snapshot of original attribute values.
     * - $_attributes: current attribute values corresponding to columns.
     * - $_extras: any additional properties set on the model outside of columns.
     */

    /**
     * Cached model definitions for each model class.
     * 
     * @var array<string, ModelDefinition>
     */
    protected static array $_definitions = [];

    /**
     * Registered hooks for model events, grouped by class and event name.
     * 
     * @var array<string, array<string, array<int, array{callback: callable, priority: int}>>>
     */
    protected static array $_hooks = [];

    /**
     * Indicates whether the model instance exists in the database.
     * 
     * @var bool
     */
    protected bool $_persisted = false;

    /**
     * Indicates whether the model instance has been marked as deleted.
     * 
     * This is used to track soft-deleted models or instances that are logically removed
     * but not physically deleted from the database.
     *
     * @var bool
     */
    protected bool $_isDeleted = false;

    /**
     * The original attribute values when the model was loaded or saved.
     * 
     * @var array<string, mixed>
     */
    protected array $_originals = [];

    /**
     * The current attribute values of the model.
     * 
     * @var array<string, mixed>
     */
    protected array $_attributes = [];

    /**
     * The extra values sets to the Model but not defined
     * 
     * @var array<string, mixed>
     */
    protected array $_extras = [];

    /**
     * Indicates whether the model uses a manually assigned primary key.
     *
     * If set to true, the model requires that the primary key be explicitly set
     * before creation (e.g., for UUIDs or externally generated IDs).
     * If false (default), the ORM assumes the primary key is auto-incremented
     * and should not be manually set during creation unless safe mode is disabled.
     *
     * @var bool
     */
    public static bool $selfAssignPrimaryKey = false;

    /**
     * Indicates whether manual assignment of the primary key is protected.
     *
     * When true (default), assigning a primary key manually on non selfAssignPrimaryKey models
     * is not allowed. This helps prevent unintended modification of primary keys.
     * Use {@see disablePrimaryKeyProtection()} to override this behavior intentionally.
     *
     * @var bool
     */
    protected bool $primaryKeyProtectionEnabled = true;

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 MODEL DEFINITION & METADATA HELPERS (INTERNAL API)
     * -----------------------------------------------------------------------------------------------------------------
     *
     * These static methods provide access to the model’s schema definitions and metadata, such as columns and computed properties.
     * They are intended primarily for internal ORM usage to retrieve and cache model structure information,
     * allowing the framework to validate and handle properties dynamically.
     * 
     * _hasColumn(), _getColumn(), _hasComputed(), _getComputed(), getDefinition(), getDatabase()
     */

    /**
     * Check if the model has a column with the given name.
     *
     */
    public static function _hasColumn(string $name): bool
    {
        return static::getDefinition()->hasColumn($name);
    }

    /**
     * Get the column definition for a given name.
     *
     */
    public static function _getColumn(string $name): ColumnDefinition|null
    {
        return static::getDefinition()->getColumn($name);
    }

    /**
     * Check if the model has a computed property with the given name.
     *
     */
    public static function _hasComputed(string $name): bool
    {
        return static::getDefinition()->hasComputed($name);
    }

    /**
     * Get the computed property definition for a given name.
     *
     */
    public static function _getComputed(string $name): ComputedDefinition|null
    {
        return static::getDefinition()->getComputed($name);
    }

    /**
     * Get the primary key **column name** (as it exists in the database).
     *
     * @return string The name of the primary key column.
     */
    public static function _getPrimaryKeyColumn(): string
    {
        return static::getDefinition()->getPrimary()->name;
    }

    /**
     * Get the **property name** corresponding to the primary key in the model class.
     *
     * @return string The name of the primary key property in the model.
     */
    public static function _getPrimaryKeyProperty(): string
    {
        return static::getDefinition()->getPrimary()->property;
    }

    /**
     * Get the current value of the model’s primary key.
     *
     * This will return null if the key has not yet been assigned (e.g., for new models).
     *
     * @return mixed The value of the primary key property.
     */
    public function getPrimaryKeyValue(): mixed
    {
        $primary = static::_getPrimaryKeyProperty();
        return $this->{$primary};
    }

    /**
     * Returns the model definition for the current model class,
     * bootstrapping it if it's not already cached.
     *
     * @return ModelDefinition
     * @throws MissingComputedResolverException
     */
    public static function getDefinition(): ModelDefinition
    {
        $class = static::class;
        if (!isset(static::$_definitions[$class]))
            static::$_definitions[$class] = ModelBooter::boot($class);
        return static::$_definitions[$class];
    }

    /**
     * Disables the protection that prevents manual assignment of the primary key
     * on auto-increment models.
     *
     * After calling this method, it is allowed to override or set the primary key manually.
     *
     * @return void
     */
    public function disablePrimaryKeyProtection(): void
    {
        $this->primaryKeyProtectionEnabled = false;
    }

    /**
     * Enables the protection that prevents manual assignment of the primary key
     * on auto-increment models.
     *
     * This method reinstates the default safeguard against overriding the primary key.
     *
     * @return void
     */
    public function enablePrimaryKeyProtection(): void
    {
        $this->primaryKeyProtectionEnabled = true;
    }

    /**
     * Checks whether the protection against manual primary key assignment is enabled.
     *
     * @return bool True if the primary key protection is enabled, false otherwise.
     */
    public function isPrimaryKeyProtectionEnabled(): bool
    {
        return $this->primaryKeyProtectionEnabled;
    }


    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 CONSTRUCTION & ATTRIBUTE INITIALIZATION
     * -----------------------------------------------------------------------------------------------------------------
     *
     * Methods related to creating and initializing model instances live here.
     * This includes constructors, bootstrapping routines to reset internal state,
     * hydration from storage or raw data, and merging/filling attributes.
     * These methods ensure the model instance is prepared with proper attribute values,
     * and manage tracking of original vs. modified states.
     * 
     * __construct(), boot(), hydrateOriginals(), fill(), merge(), consumeFromStorage(), newUnsavedInstance(), factory()
     */

    /**
     * Constructs a new model instance
     */
    public function __construct()
    {
        $this->boot();
    }

    /**
     * Initializes the model by unsetting all column and computed property names
     * from the definition to ensure a clean slate.
     *
     * This prevents magic properties from conflicting with internal tracking logic.
     *
     * @return void
     * @throws MissingComputedResolverException
     */
    protected function boot(): void
    {
        $definition = static::getDefinition();
        foreach (array_keys($definition->columns) as $column)
            unset($this->$column);
        foreach (array_keys($definition->computeds) as $computed)
            unset($this->$computed);
    }

    /**
     * Sync originals with the attributes. After this `isDirty`
     * will return false
     */
    private function hydrateOriginals(): void
    {
        $this->_originals = [];
        $this->_originals = array_merge($this->_originals, $this->_attributes);
    }

    /**
     * Set bulk attributes on the model instance.
     * 
     * @param \stdClass|array $values
     * @param bool $allowExtraProperties
     * @return static
     */
    protected function fill(\stdClass|array $values, bool $allowExtraProperties): static
    {
        $this->_attributes = [];
        $this->merge($values, $allowExtraProperties);
        return $this;
    }

    /**
     * Merge values into the model properties.
     *
     * @param \stdClass|array $values Input values to merge (array or \stdClass).
     * @param bool $allowExtraProperties Whether to allow properties not defined in the model.
     * @return static Returns the current model instance for method chaining.
     * @throws InvalidArgumentException Throws if a property is undefined and extras are disallowed.
     * @internal Internal method, not intended for public API use.
     */
    public function merge(\stdClass|array $values, bool $allowExtraProperties = false): static
    {
        // Get the short class name for error messages or logging.
        $model = Utils::classBasename(static::class);

        // Convert \stdClass object to array for easier iteration.
        if ($values instanceof \stdClass) {
            $values = (array) $values;
        }

        // Iterate through each key-value pair to merge into the model.
        foreach ($values as $key => $value) {

            // If the key corresponds to a defined database column,
            // assign the value to the internal attributes array.
            if (static::_hasColumn($key)) {
                $column = static::_getColumn($key);
                $this->{$column->property} = $value;
                continue;
            }

            // If the key corresponds to a computed property,
            // ignore it since it's derived, not set directly.
            if (static::_hasComputed($key)) {
                continue;
            }

            // If the property physically exists on the object,
            // assign the value directly to the property.
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
                continue;
            }

            // If extra properties are not allowed and the key
            // doesn't match any known property, throw an exception.
            if (!$allowExtraProperties) {
                throw new InvalidArgumentException("Cannot define \"{$key}\" on \"{$model}\" model, since it is not defined as a model property.");
            }

            // Otherwise, store the extra property in the _extras array.
            $this->_extras[$key] = $value;
        }

        // Return the current instance to support method chaining.
        return $this;
    }

    /**
     * Create an instance of the model from a database query result.
     *
     * This static factory method takes a result object or array from a database query,
     * consumes its data to populate the model, hydrates the original attributes for
     * change tracking, and marks the instance as persisted.
     *
     * @param \stdClass|array $result  The raw query result to initialize the model with.
     * @return static                 A fully-hydrated, persisted instance of the model.
     */
    protected static function createFromQueryResult(\stdClass|array $result): static
    {
        $instance = new static();

        $instance->_consumeQueryResult($result);
        $instance->hydrateOriginals();

        $instance->_persisted = true;

        return $instance;
    }

    /**
     * Consumes and maps raw query result data into model attributes.
     *
     * Iterates over the result data, determines the corresponding model column for each
     * key, applies any defined consumer or transformer logic, and sets the attribute value.
     *
     * @param \stdClass|array $result  The raw data to be mapped into the model.
     * @return void
     */
    public function _consumeQueryResult(\stdClass|array $result): void
    {
        $result = (array) $result;
        foreach ($result as $key => $value) {
            $column = static::_getColumn($key);
            if ($column) {
                $value = $result[$key];

                if ($column->consume) {
                    $value = $this->{$column->consume}($value);
                } else if ($column->transformer) {
                    $value = $column->transformer->consume($value);
                }

                $this->setAttribute($column->property, $value);
                continue;
            }
        }
    }

    /**
     * Prepares attribute values for storage by applying per-column transformations.
     *
     * If a column defines a `prepare` method, it will be called on the value.
     * Otherwise, if a transformer is defined, its `prepare()` method will be used.
     *
     * @param array $attributes The raw attribute values to process.
     * @return array The transformed values ready for storage.
     */
    private function _prepareForQuery(array $attributes): array
    {
        return array_reduce(array_keys($attributes), function (array $result, $key) use ($attributes): array {
            if (!static::_hasColumn($key))
                return $result;
            $column = static::_getColumn($key);
            $value = $attributes[$key];
            if ($column->prepare) {
                $value = $this->{$column->prepare}($value);
            } else if ($column->transformer) {
                $value = $column->transformer->prepare($value);
            }
            $result[$column->name] = $value;
            return $result;
        }, []);
    }

    /**
     * Creates and returns a new model instance representing a fresh, unsaved record.
     *
     * This method fills the model with provided attributes, disallows any extra
     * (undefined) properties, and leaves the instance marked as non-persisted.
     *
     * @param \stdClass|array $attributes The attribute values to populate the model with.
     * @return static A new model instance not yet persisted to the database.
     */
    protected static function newUnsavedInstance(\stdClass|array $attributes): static
    {
        $instance = new static();
        $instance->fill($attributes, allowExtraProperties: false);
        return $instance;
    }

    /**
     * Creates a new model instance using the given attributes.
     *
     * This is a convenience method equivalent to calling `newUnsavedInstance()`,
     * intended for test factories or dynamic instantiation.
     *
     * @param array $attributes The initial attributes for the model.
     * @return static A new unsaved model instance.
     */
    public static function factory(array $attributes = []): static
    {
        return static::newUnsavedInstance($attributes);
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 STATE AND ATTRIBUTE MANAGEMENT
     * -----------------------------------------------------------------------------------------------------------------
     *
     * Methods to check and retrieve the state of model attributes, such as tracking changes (dirty checking).
     */


    /**
     * Determine if the model has been persisted to the database.
     *
     * @return bool True if the model exists in the database; false if it's new.
     */
    public function isPersisted(): bool
    {
        return $this->_persisted;
    }

    /**
     * Determine if the model is new (not yet persisted).
     *
     * @return bool True if the model has not been saved; false otherwise.
     */
    public function isNew(): bool
    {
        return !$this->_persisted;
    }

    /**
     * Check if the model has been marked as deleted (soft delete).
     *
     * @return bool True if the model is logically deleted; false otherwise.
     */
    public function isDeleted(): bool
    {
        return $this->_isDeleted;
    }

    /**
     * Ensures that the model instance has not been marked as deleted.
     *
     * Throws a RuntimeException if the model is considered deleted,
     * preventing operations on soft-deleted or logically removed records.
     *
     * @throws RuntimeException If the model is marked as deleted.
     * @return void
     */
    private function _ensureIsntDeleted(): void
    {
        if ($this->isDeleted()) {
            throw new RuntimeException("Cannot perform operation on a deleted model.");
        }
    }

    /**
     * Get the attributes that have been modified since the model was loaded or saved.
     *
     * @return array An associative array of changed attributes.
     */
    public function getDirty(): array
    {
        if (!$this->isPersisted()) {
            return $this->_attributes;
        }

        return array_reduce(array_keys($this->_attributes), function (array $result, $key): array {
            $value = $this->_attributes[$key];
            $original = $this->_originals[$key];

            if ($value != $original) {
                $result[$key] = $value;
            }

            return $result;
        }, []);
    }

    /**
     * Determine if the model has unsaved changes.
     *
     * @param string|null $field Optional. Check if a specific field is dirty.
     * @return bool True if the model (or the given field) has unsaved changes.
     */
    public function isDirty(?string $field = null): bool
    {
        if ($field) {
            return in_array($field, array_keys($this->getDirty()));
        }

        return count($this->getDirty()) > 0;
    }


    public function setAttribute(string $key, mixed $value): void
    {
        $this->_attributes[$key] = $value;
    }

    /**
     * Forcefully sets the original value of a model attribute.
     *
     * This method overrides the original value used for dirty tracking and update operations.
     * Use with caution, as improper usage may cause inconsistencies in model state or database updates.
     *
     * @param string $key   The column name whose original value should be forced.
     * @param mixed  $value The value to set as the original.
     *
     * @return void
     */
    public function forceOriginal(string $key, mixed $value): void
    {
        $column = static::_getColumn($key);
        if ($column) {
            $this->_originals[$column->property] = $value;
        }
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 EVENT HOOKS MANAGEMENT
     * -----------------------------------------------------------------------------------------------------------------
     *
     * This section handles the registration and triggering of event hooks or callbacks.
     * Hooks allow injection of custom logic around model lifecycle events, such as before/after saving or deleting.
     * Hooks are prioritized and executed in order, providing flexible extensibility for models.
     * 
     * registerHookOn(), registerHook(), fireEvent()
     */

    /**
     * Registers a hook callback on a specific model class for a given event.
     * Hooks are stored per class and sorted by priority (descending).
     *
     * @internal This method is intended for internal use by the ORM framework.
     *
     * @param string   $class     The fully qualified class name to register the hook on.
     * @param string   $event     The event name (e.g. 'beforeSave', 'afterDelete').
     * @param callable $callback  A callback to execute when the event is triggered.
     * @param int      $priority  Determines execution order (higher = earlier).
     * @return void
     */
    public static function registerHookOn(string $class, string $event, callable $callback, int $priority = 0): void
    {
        static::$_hooks[$class][$event][] = [
            "callback" => $callback,
            "priority" => $priority
        ];
        usort(static::$_hooks[$class][$event], fn($a, $b): int => $b['priority'] <=> $a['priority']);
    }

    /**
     * Registers a hook callback on the current model class for a given event.
     * This is a shorthand for `registerHookOn()` using the static model class.
     *
     * @param string   $event     The event name (e.g. 'beforeSave', 'onCreate').
     * @param callable $callback  A callback to execute when the event is triggered.
     * @param int      $priority  Execution priority (higher runs earlier).
     * @return void
     */
    public static function registerHook(string $event, callable $callback, int $priority = 0): void
    {
        static::registerHookOn(static::class, $event, $callback, $priority);
    }

    /**
     * Triggers all registered hook callbacks for the specified event on this model instance.
     *
     * Hooks are executed in order of descending priority (highest first).
     * Each hook callback receives this model instance as its sole argument.
     *
     * @param string $event The event name to trigger (e.g. 'beforeSave', 'afterCreate').
     * @return void
     */
    protected function fireEvent(string $event): void
    {
        $hooks = static::$_hooks[static::class][$event] ?? [];

        foreach ($hooks as $hook) {
            $callback = $hook['callback'];
            $callback($this);
        }
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 PERSISTENCE OPERATIONS (CREATE, READ, UPDATE, DELETE)
     * -----------------------------------------------------------------------------------------------------------------
     *
     * Contains all methods responsible for database interaction and persistence management.
     * This includes static methods to find, create, and retrieve models, instance methods to save and delete,
     * and query builder accessors. It’s the core CRUD interface that connects the model with the data layer.
     */

    /**
     * Create and persist a new model instance with the given attributes.
     *
     * @param array $data Attributes for the new model.
     * @return static The newly created, persisted model instance.
     */
    public static function create(array $data): static
    {
        $instance = static::newUnsavedInstance($data);
        $instance->save();
        return $instance;
    }

    /**
     * Find a model instance by its primary key.
     *
     * @param int $id The primary key value.
     * @return static|null The model instance if found, or null otherwise.
     */
    public static function find(int $id): static|null
    {
        $data = static::query()->where('id', $id)->first();
        return $data ? static::createFromQueryResult($data) : null;
    }

    /**
     * Find a model instance by a specific column and value.
     *
     * @param string $column The column name to query.
     * @param string $value The value to search for.
     * @return static|null The model instance if found, or null otherwise.
     */
    public static function findBy(string $column, string $value): static|null
    {
        $data = static::query()->where($column, $value)->first();
        return $data ? static::createFromQueryResult($data) : null;
    }

    /**
     * Saves the current model to the database.
     *
     * Handles both creation of new records and updating of existing ones.
     * Automatically triggers model lifecycle hooks (before/after create/save/update),
     * prepares attributes for storage, and synchronizes the model state after persistence.
     *
     * @return static Returns the current model instance after persistence.
     *
     * @throws RuntimeException If attempting to update without a primary key or if the update fails.
     */
    public function save(): static
    {
        $this->_ensureIsntDeleted();
        if ($this->isNew()) {
            // Trigger pre-insert lifecycle hooks
            $this->fireEvent('beforeCreate');
            $this->fireEvent('beforeSave');

            // For self-assigned primary key models, ensure the primary key is set
            if (static::$selfAssignPrimaryKey && $this->getPrimaryKeyValue() === null) {
                throw new RuntimeException('Primary key must be set manually for self-assigned models.');
            }

            // Prepare attributes for insertion
            $createPayload = $this->_prepareForQuery($this->_attributes);

            // Insert record into the database
            $id = static::query()->insert($createPayload);

            // Set the primary key property if not already set
            if ($this->getPrimaryKeyValue() === null) {
                $this->setAttribute(static::_getPrimaryKeyProperty(), $id);
            }

            // Synchronize original values and update persistence state
            $this->hydrateOriginals();
            $this->_persisted = true;

            // Trigger post-insert lifecycle hooks
            $this->fireEvent('afterCreate');
            $this->fireEvent('afterSave');

            return $this;
        }

        // If no changes detected, return early
        if (!$this->isDirty()) {
            return $this;
        }

        $this->fireEvent('beforeUpdate');
        $this->fireEvent('beforeSave');

        // Fetch the original primary key value for the update WHERE clause
        $originalPrimaryKeyValue = $this->_originals[$this->_getPrimaryKeyProperty()] ?? null;

        // Cannot update without a valid original primary key
        if ($originalPrimaryKeyValue === null) {
            throw new RuntimeException("Cannot update model without primary key.");
        }

        // Prepare only the changed attributes for update
        $updatePayload = $this->_prepareForQuery($this->getDirty());

        // Perform the update query if there's data to update
        if (count($updatePayload) > 0) {
            $stmt = static::query()
                ->where(static::_getPrimaryKeyColumn(), $originalPrimaryKeyValue)
                ->update($updatePayload);

            if (!($stmt instanceof PDOStatement) || !$stmt->rowCount()) {
                throw new RuntimeException("Failed to update model");
            }
        }

        // Synchronize original values after successful update
        $this->hydrateOriginals();

        // Trigger post-update lifecycle hooks
        $this->fireEvent('afterUpdate');
        $this->fireEvent('afterSave');

        return $this;
    }

    public function delete()
    {
        $this->_ensureIsntDeleted();
        if ($this->isNew()) {
            throw new RuntimeException("Cannot delete a model that has not been persisted.");
        }

        $this->fireEvent('beforeDelete');

        $primaryKeyValue = $this->getPrimaryKeyValue();

        if ($primaryKeyValue === null) {
            throw new RuntimeException("Cannot delete model without primary key.");
        }

        $stmt = static::query()
            ->where(static::_getPrimaryKeyColumn(), $primaryKeyValue)
            ->delete();

        if (!($stmt instanceof PDOStatement) || !$stmt->rowCount()) {
            throw new RuntimeException("Failed to delete model");
        }

        $this->_isDeleted = true;

        $this->fireEvent('afterDelete');
    }

    /**
     * Get a new query builder instance for the model's table.
     *
     * @return QueryBuilderHandler A query builder instance scoped to the model's table.
     */
    public static function query(): QueryBuilderHandler
    {
        // return Database::table(static::getDefinition()->getTable());
        $table = static::getDefinition()->getTable();
        return static::getDatabase()->getTableQuery($table);
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *                                 MAGIC PROPERTY ACCESSORS
     * -----------------------------------------------------------------------------------------------------------------
     *
     * Defines dynamic property access via __get and __set magic methods.
     * These allow transparent reading and writing of model attributes, computed properties,
     * and extras, enabling flexible and concise syntax for model interaction.
     * 
     * __get(), __set()
     */

    /**
     * Magic getter to access model properties dynamically.
     *
     * @param string $key The property name being accessed.
     * @return mixed|null Returns computed value, column value, or extra property, or null if not found.
     */
    public function __get(string $key): mixed
    {
        // If the key corresponds to a computed property,
        // resolve and return the computed value via its resolver method.
        if (static::_hasComputed($key)) {
            $resolver = static::_getComputed($key)->resolver;
            return $this->$resolver();
        }

        // If the key corresponds to a defined column,
        // return the stored attribute value or null if not set.
        if (static::_hasColumn($key)) {
            return $this->_attributes[$key] ?? null;
        }

        // Otherwise, return the value from extras if present, or null.
        return $this->_extras[$key] ?? null;
    }

    /**
     * Magic setter to assign values to model properties dynamically.
     *
     * @param string $key The property name being set.
     * @param mixed $value The value to assign.
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        if (static::_hasColumn($key)) {
            $column = static::_getColumn($key);
            if (
                $column->isPrimary
                && !static::$selfAssignPrimaryKey
                && $this->primaryKeyProtectionEnabled
            ) {
                // Prevent assignment
                // TODO: Add logs/warn
                return;
            }
            $this->_attributes[$column->property] = $value;
        }
    }
}
