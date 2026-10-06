@extends('layouts.app')
@section('title', 'Dashboard - Laundrey')
@section('breadcrumb', 'Dashboard')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ now()->format('l, d F Y') }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Today at a glance.</h1>
</div>
<a href="{{ route('orders.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record order</a>
</div>

<div class="mt-8 grid grid-cols-2 border-y border-ink py-1 xl:grid-cols-4">
<div class="border-b border-line px-1 py-4 xl:border-b-0"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Taken in</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $totalOrders }}</p><p class="mt-0.5 text-xs text-muted">{{ $totalServices }} active services</p></div>
<div class="border-b border-line px-1 py-4 xl:border-b-0 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">In progress</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $processing }}</p><p class="mt-0.5 text-xs text-muted">Not ready yet</p></div>
<div class="px-1 py-4"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Ready for pickup</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $ready }}</p><p class="mt-0.5 text-xs text-muted">Notify customers</p></div>
<div class="px-1 py-4 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Cash in</p><p class="mt-1 font-display text-2xl font-bold tabular-nums tracking-tight md:text-3xl">Rp{{ number_format($revenue, 0, ',', '.') }}</p><p class="mt-0.5 text-xs text-muted">From paid orders</p></div>
</div>

<div class="mt-10 grid grid-cols-1 gap-10 xl:grid-cols-3">
<div class="xl:col-span-2">
<div class="flex items-baseline justify-between">
<h2 class="font-display text-xl font-bold tracking-tight">Recent orders</h2>
<a href="{{ route('orders.index') }}" class="text-[13px] font-medium text-primary hover:underline">All orders →</a>
</div>
<div class="mt-4 border-t border-ink">
@forelse($recentOrders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No orders yet.</p>
@endforelse
</div>
</div>
<div>
<h2 class="font-display text-xl font-bold tracking-tight">Waiting for pickup</h2>
<div class="mt-4 border-t border-ink">
@forelse($readyOrders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-baseline justify-between gap-3 border-b border-line py-3">
<span class="min-w-0"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block font-mono text-xs text-muted">{{ $o->invoice_number }}</span></span>
<span class="shrink-0 font-mono text-[11px] font-bold uppercase tracking-widest text-emerald-700">Pick up →</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">Empty. Everything is picked up.</p>
@endforelse
</div>
</div>
</div>
@endsection
