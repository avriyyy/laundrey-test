@extends('layouts.app')
@section('title', 'My laundry - Laundrey')
@section('breadcrumb', 'My laundry')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Customer <span class="font-bold text-ink">{{ auth()->user()->customerCode() }}</span> · {{ auth()->user()->phone ?? 'no phone on file' }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Hi, {{ explode(' ', auth()->user()->name)[0] }}.</h1>
<p class="mt-1.5 max-w-lg text-[15px] text-ink-2">Open a load for detail, tracking, and admin chat. Show your customer ID at the counter if names collide.</p>
</div>
</div>

<div class="mt-8 grid grid-cols-2 border-y border-ink py-1 xl:grid-cols-4">
<div class="border-b border-line px-1 py-4 xl:border-b-0"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Active</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $activeCount }}</p><p class="mt-0.5 text-xs text-muted">Not completed</p></div>
<div class="border-b border-line px-1 py-4 xl:border-b-0 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Ready</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $readyCount }}</p><p class="mt-0.5 text-xs text-muted">Come pick up</p></div>
<div class="px-1 py-4"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Done</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $doneCount }}</p><p class="mt-0.5 text-xs text-muted">Completed loads</p></div>
<div class="px-1 py-4 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Paid total</p><p class="mt-1 font-display text-2xl font-bold tabular-nums tracking-tight md:text-3xl">Rp{{ number_format($totalSpent, 0, ',', '.') }}</p><p class="mt-0.5 text-xs text-muted">Settled with us</p></div>
</div>

<h2 class="mt-10 font-display text-xl font-bold tracking-tight">My loads</h2>
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
<div class="mt-4 border-t border-ink">
@forelse($orders as $o)
@php($idx = array_search($o->current_status, $steps))
<a href="{{ route('my-orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-4">
<span class="w-32 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->service->service_name }} · {{ $o->weight_or_qty }} {{ $o->service->unit_type }}</span>
<span class="mt-1.5 flex items-center gap-1">@foreach($steps as $i => $s)<span title="{{ $s }}" class="h-1 flex-1 rounded-full {{ $i <= $idx ? 'bg-ink' : 'bg-line' }}"></span>@endforeach</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-20 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">Nothing recorded under your name yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
