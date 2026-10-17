@props(['days' => 30, 'season', 'seasons'])
<form method="GET" class="flex flex-wrap items-center gap-2">
    <x-season-select :season="$season" :seasons="$seasons" />
    <label for="period" class="text-xs font-medium text-slate-400">Período do histórico</label>
    <select id="period" name="days" class="field w-auto" onchange="this.form.submit()">
        @foreach([7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 365 => 'Últimos 365 dias', 0 => 'Toda a temporada'] as $value => $label)
            <option value="{{ $value }}" @selected((int) $days === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn-secondary" type="submit">Aplicar</button>
</form>
