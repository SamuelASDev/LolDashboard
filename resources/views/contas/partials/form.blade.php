@php $editing = isset($conta); $locked = $editing && $conta->puuid; @endphp
<div>
    <label for="nick" class="label">Apelido para identificação</label>
    <input id="nick" name="nick" class="field" value="{{ old('nick', $conta->nick ?? '') }}" maxlength="80" required autocomplete="off" placeholder="Ex.: Conta principal" @error('nick') aria-invalid="true" aria-describedby="nick-error" @enderror>
    @error('nick')<p id="nick-error" class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
</div>
<div>
    <label for="riot_id" class="label">Riot ID</label>
    <input id="riot_id" name="riot_id" class="field" value="{{ old('riot_id', $conta->riot_id ?? '') }}" maxlength="100" required placeholder="Nome#BR1" autocomplete="off" @readonly($locked) aria-describedby="identity-help" @error('riot_id') aria-invalid="true" @enderror>
    <p id="identity-help" class="mt-2 text-xs leading-relaxed text-slate-500">{{ $locked ? 'Identidade confirmada por PUUID. Renomeações são atualizadas ao sincronizar. Para outro jogador ou região, crie um cadastro separado.' : 'Informe o nome e a tag. A identidade será confirmada na primeira sincronização.' }}</p>
    @error('riot_id')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
</div>
<div>
    <label for="regiao" class="label">Região</label>
    @if($locked)<input type="hidden" name="regiao" value="{{ $conta->regiao }}">@endif
    <select id="regiao" name="regiao" class="field" required @disabled($locked) @error('regiao') aria-invalid="true" @enderror>
        <option value="">Selecione uma região</option>
        @foreach(\App\Enums\RiotRegion::cases() as $region)
            <option value="{{ $region->value }}" @selected(old('regiao', $conta->regiao ?? '') === $region->value)>{{ $region->label() }} ({{ strtoupper($region->value) }})</option>
        @endforeach
    </select>
    @error('regiao')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
</div>
<div class="flex flex-wrap justify-between gap-3 border-t border-slate-800 pt-5"><a class="btn-secondary" href="{{ route('dashboard') }}">Cancelar</a><button type="submit" class="btn-primary">{{ $editing ? 'Salvar alterações' : 'Adicionar conta' }}</button></div>
