<?php

use Potager\Limpid\Attributes\Column;
use Potager\Limpid\Attributes\Computed;
use Potager\Limpid\Model;

class User extends Model
{
    #[Column]
    public ?int $id;

    #[Column]
    public string $firstName;

    #[Column('name')]
    public string $lastName;

    #[Computed]
    public string $fullName;

    #[Computed(resolver: 'sayGreating')]
    public string $greating;

    public function computeFullName(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    public function sayGreating(): string
    {
        return "Hello {$this->firstName}!";
    }
}

beforeEach(function () {
    $pdo = $this->pdo();

    // Create a sample database
    $pdo->exec('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            name TEXT NOT NULL
        )
    ');

    // Create dummy data
    $pdo->exec("
        INSERT INTO users (first_name, name) VALUES
        ('Alice','Doe'),
        ('Bob','Doe'),
        ('Charlie','Doe')
    ");
});

test('Model computed propery is accessible and resolved correctly', function () {
    $user = User::find(1);
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->fullName)->toBe('Alice Doe');
});

test('Model computed propery with custom resolver is accessible and resolved correctly', function () {
    $user = User::find(1);
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->greating)->toBe('Hello Alice!');
});

test('Model computed property fullName is correct for different users', function () {
    $user1 = User::find(1);
    $user2 = User::find(2);
    $user3 = User::find(3);

    expect($user1->fullName)->toBe('Alice Doe')
        ->and($user2->fullName)->toBe('Bob Doe')
        ->and($user3->fullName)->toBe('Charlie Doe');
});

test('Model computed property greating is correct for different users', function () {
    $user1 = User::find(1);
    $user2 = User::find(2);
    $user3 = User::find(3);

    expect($user1->greating)->toBe('Hello Alice!')
        ->and($user2->greating)->toBe('Hello Bob!')
        ->and($user3->greating)->toBe('Hello Charlie!');
});;

test('Model computed properties return correct types', function () {
    $user = User::find(1);

    expect($user->fullName)->toBeString()
        ->and($user->greating)->toBeString();
});

test('Model computed properties are read-only', function () {
    $user = User::find(1);

    // Attempt to modify computed properties directly
    $user->fullName = 'New Name';
    $user->greating = 'New Greeting';

    // Ensure computed properties have not been modified
    expect($user->fullName)->toBe('Alice Doe')
        ->and($user->greating)->toBe('Hello Alice!');
});
