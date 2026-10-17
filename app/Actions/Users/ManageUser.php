<?php

namespace App\Actions\Users;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageUser
{
    public function changeRole(User $actor, User $target, string $role): void
    {
        abort_unless($actor->role === 'admin', 403);
        abort_unless(in_array($role, ['user', 'mod'], true), 422);
        DB::transaction(function () use ($actor, $target, $role) {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_if($target->id === $actor->id || $target->role === 'admin', 403, 'Administradores não podem ser alterados por este painel.');
            $old = $target->role;
            $target->forceFill(['role' => $role])->save();
            AuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $target->id,
                'action' => 'user.role_changed', 'metadata' => ['from' => $old, 'to' => $role]]);
        });
    }

    public function delete(User $actor, User $target, bool $selfService = false): void
    {
        abort_unless($selfService ? $actor->id === $target->id : $actor->role === 'admin', 403);
        DB::transaction(function () use ($actor, $target, $selfService) {
            // Same lock ordering for profile deletion and privileged mutations.
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $selfService) {
                abort_if($target->id === $actor->id || $target->role === 'admin', 403);
            }
            if ($target->role === 'admin' && $admins->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'O último administrador não pode ser excluído.']);
            }
            if ($target->contas()->whereHas('syncRuns', fn ($q) => $q->whereNotNull('active_conta_id'))->exists()) {
                throw ValidationException::withMessages(['account' => 'Cancele as sincronizações ativas antes de excluir o usuário.']);
            }
            AuditEvent::create(['actor_id' => $actor->id, 'subject_id' => $target->id,
                'action' => $selfService ? 'user.self_deleted' : 'user.deleted']);
            $target->delete();
        });
    }
}
