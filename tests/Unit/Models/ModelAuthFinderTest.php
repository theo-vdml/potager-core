<?php

use Potager\Test\Models\ModelWithAuthFinder as User;


beforeEach(function () {
    $pdo = $this->pdo();

    $pdo->exec('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            password TEXT NOT NULL
        )
    ');
});

test('Model with auth finder can be created', function () {
    $user = new User();
    $user->email = 'test@mail.net';
    $user->password = 'password123';
    $user->save();

    expect($user->id)->toBeGreaterThan(0);
});

test('Model with auth finder hash password on save', function () {
    $user = new User();
    $user->email = 'test@mail.net';
    $user->password = 'password123';
    $user->save();

    expect($user->id)->toBeGreaterThan(0);
    expect($user->password)->not->toBe('password123');
    expect($user->isPasswordValid('password123'))->toBeTrue();
});

test('Model can be found using identifier', function () {
    $user = new User();
    $user->email = 'lookup@test.com';
    $user->password = 'secret';
    $user->save();

    $found = User::findForAuth('lookup@test.com');
    expect($found)->not->toBeNull();
    expect($found->id)->toEqual($user->id);
});

test('verifyCredentials returns user on correct credentials', function () {
    $user = new User();
    $user->email = 'auth@test.com';
    $user->password = 'topsecret';
    $user->save();

    $verified = User::verifyCredentials('auth@test.com', 'topsecret');
    expect($verified)->not->toBeNull();
    expect($verified->id)->toEqual($user->id);
});


test('verifyCredentials returns null on invalid password', function () {
    $user = new User();
    $user->email = 'fail@test.com';
    $user->password = 'rightpass';
    $user->save();

    $verified = User::verifyCredentials('fail@test.com', 'wrongpass');
    expect($verified)->toBeNull();
});

test('verifyCredentials returns null on unknown identifier', function () {
    $verified = User::verifyCredentials('nonexistent@test.com', 'any');
    expect($verified)->toBeNull();
});

test('attempt returns user if credentials are valid', function () {
    $user = new User();
    $user->email = 'login@test.com';
    $user->password = 'tryme';
    $user->save();

    $logged = User::attempt('login@test.com', 'tryme');
    expect($logged)->not->toBeNull();
    expect($logged->id)->toEqual($user->id);
});


test('password is rehashed if changed and saved', function () {
    $user = new User();
    $user->email = 'rehash@test.com';
    $user->password = 'initial123';
    $user->save();

    $firstHash = $user->password;

    // Change the password and save again
    $user->password = 'newpassword456';
    $user->save();

    $newHash = $user->password;

    expect($newHash)->not->toBe($firstHash);
    expect(User::verifyPlainPasswordAgainstHash('newpassword456', $newHash))->toBeTrue();
});

test('password is not rehashed if saved without changing password', function () {
    $user = new User();
    $user->email = 'unchanged@test.com';
    $user->password = 'staticpass';
    $user->save();

    $originalHash = $user->password;

    // Save again without touching password
    $user->email = 'still@test.com'; // change something else
    $user->save();

    expect($user->password)->toBe($originalHash);
});

test('credential verification timing is roughly constant', function () {
    $email = 'timing@test.com';
    $password = 'secure123';

    $user = new User();
    $user->email = $email;
    $user->password = $password;
    $user->save();

    $measure = function (string $identifier, string $password): float {
        $start = microtime(true);
        User::verifyCredentials($identifier, $password);
        return microtime(true) - $start;
    };

    $iterations = 8;
    $validTimes = [];
    $wrongPasswordTimes = [];
    $noUserTimes = [];

    for ($i = 0; $i < $iterations; $i++) {
        $validTimes[] = $measure($email, $password);
        $wrongPasswordTimes[] = $measure($email, 'wrongpassword');
        $noUserTimes[] = $measure('notfound@test.com', 'any');
    }

    $averageValidTime = array_sum($validTimes) / $iterations;
    $averageWrongPasswordTime = array_sum($wrongPasswordTimes) / $iterations;
    $averageNoUserTime = array_sum($noUserTimes) / $iterations;

    $times = [
        'averageValidTime' => $averageValidTime,
        'averageWrongPasswordTime' => $averageWrongPasswordTime,
        'averageNoUserTime' => $averageNoUserTime,
    ];

    // foreach ($times as $label => $value) {
    //     echo "{$label}: " . round($value * 1000, 3) . " ms\n";
    // }

    // Timing differences should be small
    $max = max($times);
    $min = min($times);

    expect($max - $min)->toBeLessThan(0.15); // 150 ms tolerance
})->repeat(5);
