@props(['status'])
@php($ink = [
    'Received' => 'text-sky-700',
    'Washing' => 'text-amber-700',
    'Drying' => 'text-amber-700',
    'Ironing' => 'text-orange-700',
    'Ready' => 'text-emerald-700',
    'Completed' => 'text-emerald-700',
])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-mono text-[11px] font-bold uppercase tracking-widest '.($ink[$status] ?? 'text-ink-2')]) }}><span class="size-1.5 rounded-full bg-current"></span>{{ $status }}</span>
