<?php

use Potager\App;
use Potager\Auth\Authenticator;
use Potager\Auth\Guards\SessionGuard;
use Potager\Auth\Providers\LimpidUserProvider;
use Potager\Test\Models\ModelWithAuthFinder as User;


beforeEach(function () {
    $pdo = App::useDatabase()->getPdo();

    $pdo->exec('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            password TEXT NOT NULL
        )
    ');


    $this->auth = new Authenticator([
        'guards' => [
            'web' => fn(): SessionGuard => new SessionGuard(userProvider: new LimpidUserProvider(model: User::class))
        ],
    ]);
});

test('Authenticator can register and retrieve guards', function () {
    $guard = $this->auth->getGuard('web');
    expect($guard)->toBeInstanceOf(SessionGuard::class);
});

test('Authenticator can login a user', function () {
    $user = new User();
    $user->email = 'bob@mail.net';
    $user->password = 'password123';
    $user->save();

    $this->auth->login($user);

    $authenticatedUser = $this->auth->user();
    expect($authenticatedUser)->not->toBeNull();
    expect($authenticatedUser->email)->toBe('bob@mail.net');
});

test('Authenticator can logout a user', function () {
    $user = new User();
    $user->email = 'bob@mail.net';
    $user->password = 'password123';
    $user->save();

    $this->auth->login($user);
    $this->auth->logout();

    expect($this->auth->user())->toBeNull();
});

test('Authenticator can authenticate a user', function () {
    $user = new User();
    $user->email = 'bob@mail.net';
    $user->password = 'password123';
    $user->save();

    $_SESSION['auth_user'] = $user->id; // Simulate session storage

    expect($this->auth->user())->toBeNull();

    $this->auth->authenticate();

    expect($this->auth->user())->toBeInstanceOf(User::class);
    expect($this->auth->user()->email)->toEqual($user->email);
});

test('Authenticator throws exception for unregistered guard', function () {
    $this->auth->getGuard('unregistered_guard');
})->throws(\RuntimeException::class);

test('Authenticator throws exception for no authenticated user', function () {
    $this->auth->getUserOrFail();
})->throws(\RuntimeException::class);

test('Authenticator can check if user is authenticated', function () {
    expect($this->auth->isAuthenticated())->toBeFalse();

    $user = new User();
    $user->email = 'bob@mail.net';
    $user->password = 'password123';
    $user->save();

    $this->auth->login($user);
    expect($this->auth->isAuthenticated())->toBeTrue();
});

test('Authenticator can set default guard', function () {
    $this->auth->setDefaultGuard('web');
    expect($this->auth->getGuardToUse())->toBeInstanceOf(SessionGuard::class);
});

test('Authenticator use the only defined guard if not default set', function () {
    expect($this->auth->getGuardToUse())->toBeInstanceOf(SessionGuard::class);
});

test('Authenticator throws exception if default guard cannot be resolved', function () {
    $this->auth->setDefaultGuard('non_existing_guard', true);

    // Essayer de récupérer ce guard doit lancer une exception
    $this->auth->getGuardToUse();
})->throws(RuntimeException::class);

test('Authenticator can use a specific guard for operations', function () {
    $user = new User();
    $user->email = 'bob@mail.net';
    $user->password = 'password123';
    $user->save();
    $this->auth->use('web')->login($user);
    expect($this->auth->isAuthenticated())->toBeTrue();
});

test('Authenticator hasGuard', function () {
    expect($this->auth->hasGuard('web'))->toBeTrue();
    expect($this->auth->hasGuard('non_existing_guard'))->toBeFalse();
});
