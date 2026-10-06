@extends('layouts.app')
@section('title', $order->invoice_number.' - Laundrey')
@section('breadcrumb', 'My laundry / Detail')
@section('wide', true)
@section('content')
<a href="{{ route('dashboard') }}" class="mb-5 inline-block font-mono text-xs font-bold uppercase tracking-widest text-muted hover:text-ink">← Back to my laundry</a>
<p class="font-mono text-xs font-bold tracking-wide">{{ $order->invoice_number }} · {{ $order->customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $order->service->service_name }}</h1>
<p class="mt-1 text-sm text-ink-2">{{ $order->weight_or_qty }} {{ $order->service->unit_type }} · in {{ $order->created_at->format('d M Y H:i') }} · <span class="font-mono font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</span></p>

<nav class="mt-6 flex gap-1 border-b border-ink text-sm font-medium">
<a href="{{ route('my-orders.show', [$order, 'tab' => 'detail']) }}" class="px-4 py-2.5 {{ $tab === 'detail' ? 'border-b-2 border-ink font-semibold' : 'text-ink-2 hover:text-ink' }}">Detail</a>
<a href="{{ route('my-orders.show', [$order, 'tab' => 'tracking']) }}" class="px-4 py-2.5 {{ $tab === 'tracking' ? 'border-b-2 border-ink font-semibold' : 'text-ink-2 hover:text-ink' }}">Tracking</a>
</nav>

@if($tab === 'detail')
<div class="mt-6 grid grid-cols-2 gap-px border border-line bg-line sm:grid-cols-4">
<div class="bg-white px-4 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Stage</p><p class="mt-1"><x-status-badge :status="$order->current_status" /></p></div>
<div class="bg-white px-4 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Bill</p><p class="mt-1"><x-payment-badge :status="$order->payment_status" /></p></div>
<div class="bg-white px-4 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Weight</p><p class="mt-1 font-mono text-sm font-bold tabular-nums">{{ $order->weight_or_qty }} {{ $order->service->unit_type }}</p></div>
<div class="bg-white px-4 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Total</p><p class="mt-1 font-mono text-sm font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</p></div>
</div>
<p class="mt-4 text-[13px] text-ink-2">Mention this receipt number at the counter if anything looks off.</p>
@endif

@if($tab === 'tracking')
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
@php($idx = array_search($order->current_status, $steps))
<div class="mt-6 border border-line bg-white">
<div class="border-b border-dashed border-line-strong px-5 py-3">
<div class="flex items-center gap-1.5">
@foreach($steps as $i => $s)
<span title="{{ $s }}" class="h-1.5 flex-1 rounded-full {{ $i <= $idx ? 'bg-ink' : 'bg-line' }}"></span>
@endforeach
</div>
<p class="mt-2 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Step {{ $idx + 1 }}/6 - {{ $order->current_status }}</p>
</div>
<div class="px-5 py-2">
@foreach($order->tracks as $t)
<div class="flex items-baseline gap-4 border-b border-line py-2.5 last:border-0">
<span class="w-28 shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M, H:i') }}</span>
<span class="flex-1 text-sm"><b class="font-mono text-[11px] font-bold uppercase tracking-widest">{{ $t->status }}</b>@if($t->notes)<span class="text-ink-2"> - {{ $t->notes }}</span>@endif</span>
</div>
@endforeach
</div>
</div>
@endif
@endsection
