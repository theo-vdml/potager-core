<?php

namespace Potager\Auth\Guards;

use InvalidArgumentException;
use Potager\Auth\Contracts\AuthGuard;
use Potager\Auth\Contracts\UserProvider;
use RuntimeException;

/**
 * SessionGuard handles user authentication using PHP sessions.
 * It implements the AuthGuard interface to provide session-based authentication.
 */
class SessionGuard implements AuthGuard
{
    /**
     * @param string $sessionKey The key used to store user information in the session.
     * @param UserProvider|null $userProvider The user provider used to retrieve and store user information.
     */
    public function __construct(
        private string $sessionKey = 'auth_user',
        private ?UserProvider $userProvider = null
    ) {
        // Ensure the session is started when the guard is instantiated.
        if (session_status() === PHP_SESSION_NONE) {
            throw new RuntimeException("Session must be started to use Session guard");
        }
    }

    /**
     * Log in a user by storing their information in the session.
     *
     * @param mixed $user The user information to store.
     * @throws InvalidArgumentException If the user information is invalid.
     */
    public function login($user): void
    {
        if ($this->userProvider !== null) {
            $key = $this->userProvider->getKey($user);
        } elseif (is_string($user) || is_numeric($user)) {
            $key = $user;
        } else {
            throw new InvalidArgumentException('User must be a string or numeric value or you must provide a UserProvider.');
        }

        $_SESSION[$this->sessionKey] = $key;
    }

    /**
     * Log out the current user by removing their information from the session.
     */
    public function logout(): void
    {
        unset($_SESSION[$this->sessionKey]);
    }

    /**
     * Retrieve the currently authenticated user.
     *
     * @return mixed|null The authenticated user or null if no user is authenticated.
     */
    public function user()
    {
        if (!isset($_SESSION[$this->sessionKey])) {
            return null;
        }

        $key = $_SESSION[$this->sessionKey];

        if ($this->userProvider !== null) {
            return $this->userProvider->getOriginal($key);
        }

        return $key;
    }
}
