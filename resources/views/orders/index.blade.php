@extends('layouts.app')
@section('title', 'Orders - Laundrey')
@section('breadcrumb', 'Orders')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $orders->total() }} transactions</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Order book.</h1>
</div>
<a href="{{ route('orders.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record order</a>
</div>
<form method="GET" class="mt-6 flex flex-col gap-2 sm:flex-row">
<input name="cari" placeholder="Find receipt / name…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none">
<select name="status" class="h-10 rounded-md border border-line-strong bg-white px-3 text-sm sm:max-w-44"><option value="">All stages</option>@foreach(['Received','Washing','Drying','Ironing','Ready','Completed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Filter</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($orders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }} · {{ $o->created_at->format('d M Y') }}</span></span>
<span class="hidden md:block"><x-payment-badge :status="$o->payment_status" /></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No matching records.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
