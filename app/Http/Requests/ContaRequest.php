<?php

namespace App\Http\Requests;

use App\Enums\RiotRegion;
use App\Models\Conta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nick' => is_string($this->nick) ? trim($this->nick) : $this->nick,
            'riot_id' => is_string($this->riot_id) ? trim($this->riot_id) : $this->riot_id,
            'regiao' => is_string($this->regiao) ? strtolower(trim($this->regiao)) : $this->regiao,
        ]);
    }

    public function rules(): array
    {
        return [
            'nick' => ['required', 'string', 'max:80'],
            'riot_id' => ['required', 'string', 'max:100', 'regex:/^[^#<>\x00-\x1F]{1,80}#[\pL\pN]{2,10}$/u'],
            'regiao' => ['required', Rule::enum(RiotRegion::class)],
        ];
    }

    public function messages(): array
    {
        return ['riot_id.regex' => 'Informe um Riot ID válido no formato Nome#TAG.',
            'regiao.enum' => 'Selecione uma região disponível.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $current = $this->route('conta');
            $id = $current instanceof Conta ? $current->id : $current;
            $conta = $id ? $this->user()->contas()->find($id) : null;
            if ($conta?->syncRuns()->whereNotNull('active_conta_id')->exists()) {
                $validator->errors()->add('riot_id', 'Aguarde a sincronização terminar antes de editar.');
            }
            if ($conta?->puuid && ($conta->riot_id !== $this->riot_id || $conta->regiao !== $this->regiao)) {
                $validator->errors()->add('riot_id', 'A identidade já foi confirmada. Cadastre outra conta para trocar jogador ou região. Renomeações são atualizadas pela sincronização.');
            }
            $duplicate = $this->user()->contas()->when($id, fn ($query) => $query->whereKeyNot($id))
                ->where('regiao', $this->regiao)->get(['riot_id'])
                ->contains(fn ($item) => mb_strtolower($item->riot_id) === mb_strtolower($this->riot_id));
            if ($duplicate) {
                $validator->errors()->add('riot_id', 'Esta conta já está cadastrada nesta região.');
            }
        }];
    }
}
