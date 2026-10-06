@extends('layouts.app')
@section('title', 'Platform - Laundrey')
@section('breadcrumb', 'Platform')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Platform overview</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">All shops.</h1>
</div>
<a href="{{ route('admin.tenants.index') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">Manage tenants</a>
</div>

<div class="mt-8 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-4"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Shops</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $tenantCount }}</p></div>
<div class="border-l border-line px-1 py-4 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Orders</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $totalOrders }}</p></div>
<div class="border-l border-line px-1 py-4 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Revenue</p><p class="mt-1 font-display text-2xl font-bold tabular-nums tracking-tight md:text-3xl">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Shops</h2>
<div class="mt-3 border-t border-ink">
@forelse($tenants as $t)
<a href="{{ route('admin.tenants.show', $t) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-20 shrink-0 font-mono text-xs font-bold">{{ $t->prefix }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $t->name }}</span><span class="block text-xs text-muted">{{ $t->orders_count }} orders · {{ $t->users_count }} users</span></span>
<span class="shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M Y') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No shops registered yet.</p>
@endforelse
</div>
@endsection
