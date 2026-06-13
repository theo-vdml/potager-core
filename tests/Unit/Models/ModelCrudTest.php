<?php

use Potager\Test\Models\User;

beforeEach(function () {
    $pdo = $this->pdo();

    // Create a sample database
    $pdo->exec('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL
        )
    ');

    // Create dummy data
    $pdo->exec("
        INSERT INTO users (first_name, name, email) VALUES
        ('Alice','Doe', 'alice@doe.com'),
        ('Bob','Doe', 'bob@doe.com'),
        ('Charlie','Doe', 'charlie@doe.com')
    ");
});


describe('Model Creation', function () {
    test('with column names', function () {
        $user = User::create([
            'first_name' => "John",
            'name' => "Doe",
            'email' => "john@doe.com"
        ]);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0)
            ->and($user->isPersisted())->toBeTrue();
    });

    test('with property names', function () {
        $user = User::create([
            'firstName' => "John",
            'lastName' => "Doe",
            'email' => "john@doe.com"
        ]);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0)
            ->and($user->isPersisted())->toBeTrue();
    });

    test('as a draft', function () {
        $user = new User();
        $user->firstName = "John";
        $user->lastName = "Doe";
        $user->email = "john@doe.com";

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeNull()
            ->and($user->isPersisted())->toBeFalse();
    });

    test('by saving a draft', function () {
        $user = new User();
        $user->firstName = "John";
        $user->lastName = "Doe";
        $user->email = "john@doe.com";
        $user->save();

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0)
            ->and($user->isPersisted())->toBeTrue();
    });
});

describe('Model retrieval', function () {
    test('using primary', function () {
        $user = User::find(1);
        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBe(1);
    });

    test('not found using primary', function () {
        $user = User::find(999);
        expect($user)->toBeNull();
    });

    test('using custom column', function () {
        $user = User::findBy('email', 'bob@doe.com');
        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBe(2);
    });

    test('not found using custom column', function () {
        $user = User::findBy('email', 'not@existing.com');
        expect($user)->toBeNull();
    });
});

describe('Model Updating', function () {
    test('with dirty state', function () {
        $user = User::findBy('email', 'alice@doe.com');
        $user->email = "alice.doe@mail.net";
        expect($user->email)->toBe("alice.doe@mail.net")
            ->and($user->isDirty())->toBeTrue()
            ->and(array_keys($user->getDirty()))->toBe(['email']);
    });

    test('perfom saving', function () {
        $user = User::findBy('email', 'alice@doe.com');
        $user->email = "alice.doe@mail.net";
        $user->save();
        expect($user->isDirty())->toBeFalse()
            ->and($user->email)->toBe("alice.doe@mail.net");
        $user = User::findBy('email', 'alice.doe@mail.net');
        expect($user->id)->toBe(1);
    });


    test('update primary key', function () {
        $user = new User();
        $user->disablePrimaryKeyProtection();
        $user->id = 99;
        $user->firstName = 'Manual';
        $user->lastName = 'ID';
        $user->email = 'manual@id.com';

        $user->save();

        $fetched = User::find(99);
        expect($fetched)->not->toBeNull()
            ->and($fetched->email)->toBe('manual@id.com');
    });

    test('with primary key protection', function () {
        $user = User::find(1);
        $user->id = 99;
        expect($user->id)->toBe(1);
        $user->disablePrimaryKeyProtection();
        $user->id = 99;
        expect($user->id)->toBe(99);
    });

    describe('Exception Handling', function () {
        test('missing primary key', function () {
            $user = User::find(1);
            $user->forceOriginal('id', null);
            $user->email = "changed@email.com";
            $user->save();
        })->throws(RuntimeException::class, "Cannot update model without primary key.");

        test('0 affected rows', function () {
            $user = User::find(1);
            $user->email = 'updated@email.com';
            $user->forceOriginal('id', 999);
            $user->save();
        })->throws(RuntimeException::class, "Failed to update model");
    });
});

describe('Model Deletion', function () {
    test('delete retrieved', function () {
        $user = User::findBy('email', 'alice@doe.com');
        $user->delete();
        $deletedUser = User::find($user->id);
        expect($user->isDeleted())->toBeTrue();
        expect($deletedUser)->toBeNull();
    });

    test('delete created', function () {
        $user = User::create(['firstName' => "John", 'lastName' => "Doe", 'email' => "john@doe.com"]);
        $user->delete();
        $deletedUser = User::find($user->id);
        expect($user->isDeleted())->toBeTrue();
        expect($deletedUser)->toBeNull();
    });

    describe('Exception Handling', function () {
        test('already deleted', function () {
            $user = User::findBy('email', 'alice@doe.com');
            $user->delete();
            $user->delete();
        })->throws(RuntimeException::class, 'Cannot perform operation on a deleted model.');

        test('non-existent model', function () {
            $user = new User();
            $user->id = 999; // Non-existent ID
            $user->delete();
        })->throws(RuntimeException::class, 'Cannot delete a model that has not been persisted.');

        test('missing primary key', function () {
            $user = new User();
            $user->delete();
        })->throws(RuntimeException::class, "Cannot delete a model that has not been persisted.");
    });
});
