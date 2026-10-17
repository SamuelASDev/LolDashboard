<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePersonalWorkspace
{
    public function note(User $user, string $table, string $key, string $identity, ?string $body, array $extra = []): void
    {
        abort_unless(in_array($table, ['player_notes', 'match_notes'], true), 404);
        $query = DB::table($table)->where('user_id', $user->id)->where($key, $identity);
        if (trim($body ?? '') === '') {
            $query->delete();

            return;
        }
        DB::table($table)->upsert([array_merge($extra, [
            'user_id' => $user->id, $key => $identity, 'body' => $body,
            'created_at' => now(), 'updated_at' => now(),
        ])], ['user_id', $key], array_merge(['body', 'updated_at'], array_keys($extra)));
    }

    public function remove(User $user, string $table, int $id): void
    {
        abort_unless(in_array($table, ['filter_favorites', 'personal_goals'], true), 404);
        $query = DB::table($table)->where('user_id', $user->id)->where('id', $id);
        abort_unless($query->exists(), 404);
        $query->delete();
    }

    public function create(User $user, string $table, array $data): void
    {
        abort_unless(in_array($table, ['filter_favorites', 'personal_goals'], true), 404);
        DB::transaction(function () use ($user, $table, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $limit = config('workspace.'.($table === 'filter_favorites' ? 'max_favorites' : 'max_goals'));
            if (DB::table($table)->where('user_id', $user->id)->count() >= $limit) {
                throw ValidationException::withMessages(['name' => 'Limite de itens atingido. Remova um item antes de continuar.']);
            }
            DB::table($table)->insert($data + ['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
