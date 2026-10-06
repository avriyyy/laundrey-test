@extends('layouts.app')
@section('title', $order->invoice_number.' - Laundrey')
@section('breadcrumb', 'Orders / Detail')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $order->invoice_number }} · {{ $order->customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $order->customer->name }}</h1>
<p class="mt-1 text-sm text-ink-2">{{ $order->service->service_name }} · {{ $order->weight_or_qty }} {{ $order->service->unit_type }} · in {{ $order->created_at->format('d M Y H:i') }}</p>
</div>
<div class="flex flex-col items-end gap-1.5"><x-status-badge :status="$order->current_status" /><x-payment-badge :status="$order->payment_status" /></div>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Total</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Stage</p><p class="mt-0.5 font-display text-xl font-bold">{{ $order->current_status }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Admin</p>
<div class="mt-1.5 flex flex-wrap gap-1.5">
<form method="POST" action="{{ route('orders.update', $order) }}">@csrf @method('PUT')
<input type="hidden" name="payment_status" value="{{ $order->payment_status === 'paid' ? 'unpaid' : 'paid' }}">
<button class="h-8 rounded-md border border-ink px-2.5 text-xs font-medium hover:bg-ink hover:text-white">{{ $order->payment_status === 'paid' ? 'Set unpaid' : 'Mark paid' }}</button></form>
<form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Delete this order?')">@csrf @method('DELETE')<button class="h-8 rounded-md px-2.5 text-xs text-muted hover:text-red-600">Delete</button></form>
</div></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Journey log</h2>
<div class="mt-3 border-t border-ink">
@foreach($order->tracks as $t)
<div class="flex items-baseline gap-4 border-b border-line py-3">
<span class="w-32 shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M, H:i') }}</span>
<span class="flex-1 text-sm"><b class="font-mono text-xs font-bold uppercase tracking-widest">{{ $t->status }}</b>@if($t->notes)<span class="text-ink-2"> - {{ $t->notes }}</span>@endif</span>
<span class="hidden font-mono text-xs text-muted sm:block">{{ $t->updater->name ?? '' }}</span>
</div>
@endforeach
</div>
@endsection
