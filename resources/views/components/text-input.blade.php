@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-slate-950 border-slate-800 text-slate-200 placeholder-slate-600 focus:border-amber-500 focus:ring-amber-500/30 rounded-xl shadow-sm transition duration-200']) }}>