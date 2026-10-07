@extends('layouts.app')
@section('title', 'Customers - Laundrey')
@section('breadcrumb', 'Customers')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Customer detail</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Customers.</h1>
</div>
<a href="{{ route('customers.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record customer</a>
</div>
<form method="GET" class="mt-6 flex gap-2">
<input name="cari" placeholder="Find name, phone, or ID…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none">
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($customers as $c)
<a href="{{ route('customers.show', $c) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-24 shrink-0 font-mono text-xs font-bold">{{ $c->customerCode() }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $c->name }}</span><span class="block truncate font-mono text-xs text-muted">{{ $c->phone ?? 'no phone' }} · {{ $c->orders_count }} loads</span></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($c->spent_sum ?? 0, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No customers found. <a href="{{ route('customers.create') }}" class="font-medium text-primary">Record the first one →</a></p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $customers->links() }}</div>
@endsection
