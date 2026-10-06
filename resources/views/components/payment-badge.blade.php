@props(['status'])
<span {{ $attributes->merge(['class' => 'font-mono text-[11px] font-bold uppercase tracking-widest '.($status === 'paid' ? 'text-emerald-700' : 'text-amber-700')]) }}>{{ $status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
