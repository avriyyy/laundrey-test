@extends('layouts.app')
@section('title', 'Tenants - Laundrey')
@section('breadcrumb', 'Tenants')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $tenants->total() }} shops · {{ $totalOrders }} orders platform-wide</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Tenants.</h1>
</div>
<a href="{{ route('admin.tenants.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Add tenant</a>
</div>
<form method="GET" class="mt-6 flex gap-2">
<input name="cari" placeholder="Find shop or prefix…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none">
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($tenants as $t)
<a href="{{ route('admin.tenants.show', $t) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-20 shrink-0 font-mono text-xs font-bold">{{ $t->prefix }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $t->name }}</span><span class="block truncate text-xs text-muted">{{ $t->orders_count }} orders · {{ $t->services_count }} services · {{ $t->users_count }} users</span></span>
<span class="shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M Y') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No shops match.</p>
@endforelse
</div>
<div class="mt-4 grid grid-cols-2 gap-4 text-[13px] text-ink-2">
<p>Platform revenue (paid): <b class="font-mono tabular-nums">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</b></p>
</div>
<div class="mt-2 text-[13px]">{{ $tenants->links() }}</div>
@endsection
