@extends('layouts.app')
@section('title', $tenant->name.' - Laundrey')
@section('breadcrumb', 'Tenants / Detail')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $tenant->prefix }} · since {{ $tenant->created_at->format('d M Y') }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $tenant->name }}</h1>
<p class="mt-1 text-sm text-ink-2">Admins: {{ $admins->pluck('email')->join(', ') ?: 'none' }}</p>
</div>
<div class="flex gap-2">
<a href="{{ route('admin.tenants.edit', $tenant) }}" class="h-9 rounded-md border border-ink px-3 text-[13px] font-medium leading-8 hover:bg-ink hover:text-white">Edit</a>
<form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this shop and ALL its data?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete shop</button></form>
</div>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Orders</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $tenant->orders_count }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Revenue</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($revenue, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Services</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $tenant->services_count }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Recent orders</h2>
<div class="mt-3 border-t border-ink">
@forelse($orders as $o)
<div class="flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $o->customer->name }}</span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</div>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No orders yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
