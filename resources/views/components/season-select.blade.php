@props(['season', 'seasons'])
<label class="text-xs text-slate-400">Temporada
<select name="season" class="field mt-1.5">
@foreach($seasons as $option)<option value="{{ $option->id }}" @selected($season->id === $option->id)>{{ $option->name }}{{ $option->status === 'active' ? ' · Atual' : '' }}</option>@endforeach
</select>
</label>
