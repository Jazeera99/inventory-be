<?php

namespace Tests;

use App\Models\User;
use Closure;

class AssertAuthPermission
{
    public function __construct(
        protected Closure $authenticate,
        protected Closure $do
    ) {}

    public function allow(User $user): self
    {
        ($this->authenticate)($user);
        ($this->do)()->assertSuccessful();

        return $this;
    }

    public function forbid(User $user): self
    {
        ($this->authenticate)($user);
        // Standard Laravel: 403 Forbidden
        ($this->do)()->assertStatus(403);

        return $this;
    }
}
