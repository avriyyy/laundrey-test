@extends('layouts.app')
@section('title', $customer->name.' - Laundrey')
@section('breadcrumb', 'Customers / File')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $customer->name }}</h1>
<p class="mt-1 font-mono text-[13px] text-ink-2">{{ $customer->phone ?? 'no phone' }} · {{ $customer->email ?? 'no email' }}</p>
</div>
<div class="flex gap-2">
<a href="{{ route('customers.edit', $customer) }}" class="h-9 rounded-md border border-ink px-3 text-[13px] font-medium leading-8 hover:bg-ink hover:text-white">Edit</a>
<form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete</button></form>
</div>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Loads</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $orders->total() }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Paid total</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($spent, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Unpaid</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($unpaid, 0, ',', '.') }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Order history</h2>
<div class="mt-3 border-t border-ink">
@forelse($orders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->service->service_name }} · {{ $o->weight_or_qty }} {{ $o->service->unit_type }}</span><span class="block text-xs text-muted">{{ $o->created_at->format('d M Y') }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="hidden md:block"><x-payment-badge :status="$o->payment_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No loads yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
