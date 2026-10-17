<?php

namespace App\Policies;

use App\Models\Conta;
use App\Models\User;

class ContaPolicy
{
    public function view(User $user, Conta $conta): bool
    {
        return $conta->user_id === $user->id;
    }

    public function update(User $user, Conta $conta): bool
    {
        return $this->view($user, $conta);
    }

    public function delete(User $user, Conta $conta): bool
    {
        return $this->view($user, $conta);
    }
}
