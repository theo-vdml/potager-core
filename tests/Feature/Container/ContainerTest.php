<?php

use Potager\Container\Container;
use Potager\Container\Exceptions\ContainerException;
use Potager\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;

class Foo {}
class Bar
{
    public function __construct(public Foo $foo) {}
}
class Baz
{
    public function handle(Bar $bar): string
    {
        return 'Handled';
    }
}
class NeedsScalar
{
    public function __construct(public string $value) {}
}
class WithDefault
{
    public function __construct(public string $value = 'default') {}
}
class NeedsBoth
{
    public function __construct(public Foo $foo, public string $name = 'Jane') {}
}
class NeedsContainer
{
    public function __construct(public ContainerInterface $container) {}
}
class StaticHandler
{
    public static function staticMethod(Foo $foo): string
    {
        return 'Static OK';
    }
}
abstract class AbstractClass {}
class FactoryClass
{
    public function create(Foo $foo)
    {
        return new Bar($foo);
    }
}
class StaticFactory
{
    public static function make(Foo $foo)
    {
        return new Bar($foo);
    }
}

describe('Container basic compliance and interface', function () {
    it('implements ContainerInterface', function () {
        $container = new Container();
        expect($container)->toBeInstanceOf(ContainerInterface::class);
    });
});

describe('Binding and resolving services', function () {
    it('binds and resolves a non-singleton service', function () {
        $container = new Container();
        $container->bind('foo', fn() => new Foo());

        $foo1 = $container->make('foo');
        $foo2 = $container->make('foo');

        expect($foo1)->toBeInstanceOf(Foo::class)
            ->and($foo1)->not()->toBe($foo2);
    });

    it('binds and resolves a singleton service', function () {
        $container = new Container();
        $container->singleton('foo', fn() => new Foo());

        $foo1 = $container->make('foo');
        $foo2 = $container->make('foo');

        expect($foo1)->toBeInstanceOf(Foo::class)
            ->and($foo1)->toBe($foo2);
    });

    it('resolves a registered instance directly', function () {
        $container = new Container();
        $foo = new Foo();

        $container->instance('foo', $foo);

        expect($container->make('foo'))->toBe($foo);
    });
});

describe('Conditional bindings (IfNotExists variants)', function () {
    it('supports bindIfNotExists and avoids override', function () {
        $container = new Container();

        $container->bindIfNotExists('foo', fn() => 'first');
        $container->bindIfNotExists('foo', fn() => 'second');

        expect($container->make('foo'))->toBe('first');
    });

    it('supports singletonIfNotExists and avoids override', function () {
        $container = new Container();

        $container->singletonIfNotExists('foo', fn() => new stdClass());
        $container->singletonIfNotExists('foo', fn() => throw new Exception('Should not be called'));

        expect($container->make('foo'))->toBeInstanceOf(stdClass::class);
    });

    it('supports instanceIfNotExists and avoids override', function () {
        $container = new Container();

        $container->instanceIfNotExists('foo', new Foo());
        $container->instanceIfNotExists('foo', new Bar(new Foo()));

        expect($container->make('foo'))->toBeInstanceOf(Foo::class);
    });
});

describe('Calling factories', function () {
    it('calls factory with ReflectionMethod (array callable)', function () {
        $container = new Container();
        $factory = new FactoryClass();

        $container->bind('bar', [$factory, 'create']);
        $bar = $container->make('bar');

        expect($bar)->toBeInstanceOf(Bar::class)
            ->and($bar->foo)->toBeInstanceOf(Foo::class);
    });

    it('calls factory with ReflectionMethod (static string callable)', function () {
        $container = new Container();

        $container->bind('bar', StaticFactory::class . '::make');
        $bar = $container->make('bar');

        expect($bar)->toBeInstanceOf(Bar::class);
    });
});

describe('Automatic dependency resolution and injection', function () {
    it('auto-resolves a class with constructor injection', function () {
        $container = new Container();

        $bar = $container->make(Bar::class);

        expect($bar)->toBeInstanceOf(Bar::class)
            ->and($bar->foo)->toBeInstanceOf(Foo::class);
    });

    it('resolves nested dependencies automatically', function () {
        $container = new Container();

        $bar = $container->make(Bar::class);

        expect($bar)->toBeInstanceOf(Bar::class)
            ->and($bar->foo)->toBeInstanceOf(Foo::class);
    });

    it('injects container itself if type hinted', function () {
        $container = new Container();

        $instance = $container->make(NeedsContainer::class);
        expect($instance->container)->toBeInstanceOf(Container::class);
    });
});

describe('Parameter handling during resolution', function () {
    it('passes named parameters to make()', function () {
        $container = new Container();
        $instance = $container->make(NeedsScalar::class, ['value' => 'injected']);
        expect($instance->value)->toBe('injected');
    });

    it('overrides only scalar parameter with make()', function () {
        $container = new Container();

        $obj = $container->make(NeedsBoth::class, ['name' => 'Alice']);
        expect($obj->foo)->toBeInstanceOf(Foo::class)
            ->and($obj->name)->toBe('Alice');
    });

    it('uses default parameter values in make()', function () {
        $container = new Container();
        $instance = $container->make(WithDefault::class);
        expect($instance->value)->toBe('default');
    });

    it('uses default values for unresolvable scalar parameters', function () {
        $container = new Container();
        $instance = $container->make(WithDefault::class);

        expect($instance->value)->toBe('default');
    });
});

describe('Calling closures and methods with injection', function () {
    it('calls closures with resolved dependencies', function () {
        $container = new Container();

        $closure = fn(Foo $foo) => get_class($foo);
        $result = $container->call($closure);

        expect($result)->toBe(Foo::class);
    });

    it('passes named parameters to call()', function () {
        $container = new Container();

        $result = $container->call(function (string $name = 'default') {
            return strtoupper($name);
        }, ['name' => 'pest']);

        expect($result)->toBe('PEST');
    });

    it('calls static class methods with injection', function () {
        $container = new Container();

        $result = $container->call([StaticHandler::class, 'staticMethod']);
        expect($result)->toBe('Static OK');
    });

    it('calls a method with auto-injection', function () {
        $container = new Container();

        $result = $container->call([new Baz(), 'handle']);

        expect($result)->toBe('Handled');
    });

    it('calls static method using "Class::method" string syntax', function () {
        $container = new Container();

        $result = $container->call(StaticHandler::class . '::staticMethod');

        expect($result)->toBe('Static OK');
    });
});

describe('Bindings retrieval and existence checks', function () {
    it('binds a closure and resolves it via make()', function () {
        $container = new Container();
        $container->bind('custom', fn() => new Foo());

        $result = $container->make('custom');

        expect($result)->toBeInstanceOf(Foo::class);
    });

    it('resolves services using get() and checks with has()', function () {
        $container = new Container();
        $container->bind('foo', fn() => new Foo());

        expect($container->has('foo'))->toBeTrue()
            ->and($container->get('foo'))->toBeInstanceOf(Foo::class);
    });
});

describe('Error handling', function () {
    it('throws NotFoundException if service is unknown', function () {
        $container = new Container();

        $container->get('nonexistent');
    })->throws(NotFoundException::class);

    it('throws NotFoundException if no binding or class exists', function () {
        $container = new Container();

        $container->make('unknown_service');
    })->throws(NotFoundException::class);

    it('throw an error if class is not instanciable', function () {
        $container = new Container();

        $abstract = $container->make(AbstractClass::class);
    })->throws(ContainerException::class);

    it('throws ContainerException when factory callable fails', function () {
        $container = new Container();

        $container->bind('failing', fn() => throw new Exception('Factory failure'));

        $container->make('failing');
    })->throws(ContainerException::class, 'Factory failure');

    it('throws ContainerException on unresolved scalar', function () {
        $container = new Container();

        $container->make(NeedsScalar::class);
    })->throws(ContainerException::class);

    it('throws ContainerException if a parameter cannot be resolved', function () {
        $container = new Container();

        $container->make(NeedsScalar::class);
    })->throws(ContainerException::class);
});
