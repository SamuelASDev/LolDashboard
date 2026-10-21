@props(['name'])
<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('overview') <path d="m12 3 9 9-9 9-9-9Z"/><path d="m12 8 4 4-4 4-4-4Z"/> @break
        @case('players') <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 5"/> @break
        @case('notebook') <path d="M5 3h14v18H5zM9 3v18m3-12h4m-4 4h4"/> @break
        @case('add') <path d="m12 3 9 9-9 9-9-9Zm0 5v8m-4-4h8"/> @break
        @case('profile') <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/> @break
        @case('seasons') <path d="M8 3h8v7a4 4 0 0 1-8 0ZM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 2v7m-4 0h8"/> @break
    @endswitch
</svg>
