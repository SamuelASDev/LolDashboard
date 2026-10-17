@props(['kind' => 'champions', 'id' => null, 'name' => '', 'size' => 'h-9 w-9'])
@php($asset = $kind === 'champions' ? \App\Support\RiotAssets::champion($id, $name) : \App\Support\RiotAssets::entry($kind, $id))
@if($asset)
<img src="{{ $asset['url'] }}" alt="{{ $asset['name'] }}" title="{{ $asset['name'] }}" width="36" height="36" loading="lazy" class="{{ $size }} shrink-0 rounded-md bg-slate-800 object-cover" onerror="this.style.visibility='hidden'">
@else
<span title="{{ $name ?: ($id ? 'ID '.$id : 'Espaço vazio') }}" class="{{ $size }} inline-grid shrink-0 place-items-center rounded-md bg-slate-800 text-[10px] text-slate-400">{{ $name ? mb_substr($name, 0, 2) : '·' }}</span>
@endif
