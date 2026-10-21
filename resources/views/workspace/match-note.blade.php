<details class="panel p-4" @if($matchNotes->has($partida->match_id)) open @endif>
    <summary class="cursor-pointer text-xs text-cyan-200">{{ $matchNotes->has($partida->match_id) ? 'Partida com anotação' : 'Anotar esta partida' }}</summary>
    <form class="mt-3" method="POST" action="{{ route('matches.note', $partida->id) }}">@csrf @method('PUT')
        <label class="sr-only" for="match-note-{{ $partida->id }}">Anotação da partida</label>
        <textarea class="field" id="match-note-{{ $partida->id }}" name="body" maxlength="5000" rows="2">{{ $matchNotes->get($partida->match_id) }}</textarea>
        <button class="btn-secondary mt-2">Salvar anotação da partida</button>
    </form>
</details>
