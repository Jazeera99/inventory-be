<?php

/**
 * Helps Intelephense to know the type of `auth()->user()` and `auth()->id()`.
 * https://github.com/bmewburn/vscode-intelephense/issues/3125
 */

namespace Illuminate\Contracts\Auth;

interface Factory
{
    /**
     * Get the currently authenticated user.
     *
     * @return \App\Models\User|null
     */
    public function user();

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id();

    /**
     * Check if the user is authenticated.
     *
     * @return bool
     */
    public function check();

    /**
     * Try to authenticate a user using the given credentials.
     *
     * @return bool
     */
    public function attempt($credentials, $remember = false);
}
