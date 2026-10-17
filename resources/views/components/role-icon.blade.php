@props(['role' => 'UNKNOWN', 'size' => 'h-5 w-5'])
@php($paths = [
'TOP' => 'M4 4h16v4H8v12H4V4Zm6 10 6-6 4 4-6 6-4-4Z',
'JUNGLE' => 'M11 21C4 18 2 12 3 4c5 3 8 8 8 17Zm2 0c0-9 3-14 8-17 1 8-1 14-8 17ZM12 3l3 7-3 5-3-5 3-7Z',
'MIDDLE' => 'M3 17 17 3h4v4L7 21H3v-4Zm0-14h8L3 11V3Zm18 10v8h-8l8-8Z',
'BOTTOM' => 'M20 20H4v-4h12V4h4v16ZM14 10l-6 6-4-4 6-6 4 4Z',
'UTILITY' => 'm12 3 3 5-3 6-3-6 3-5ZM2 8l7 4 2 8-6-5-3-7Zm20 0-3 7-6 5 2-8 7-4Z',
'UNKNOWN' => 'm12 2 10 10-10 10L2 12 12 2Zm0 5-5 5 5 5 5-5-5-5Z'])
<svg class="{{ $size }} shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $paths[$role] ?? $paths['UNKNOWN'] }}"/></svg>
