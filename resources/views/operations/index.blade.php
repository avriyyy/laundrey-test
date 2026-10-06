@extends('layouts.app')
@section('title', 'Operations - Laundrey')
@section('breadcrumb', 'Operations')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Queue of {{ $orders->total() }} loads</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Work the longest wait first.</h1>
</div>
<form method="GET" class="flex gap-2">
<input name="cari" placeholder="Find receipt…" value="{{ request('cari') }}" class="h-10 w-48 rounded-md border border-line-strong bg-white px-3 font-mono text-xs focus:border-ink focus:outline-none">
<button class="h-10 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
</div>
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
<div class="mt-8 border-t border-ink">
@forelse($orders as $o)
@php($idx = array_search($o->current_status, $steps))
<div class="grid grid-cols-1 gap-4 border-b border-line py-5 lg:grid-cols-[1fr_280px] lg:gap-8">
<div>
<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
<span class="font-mono text-sm font-bold tracking-wide">{{ $o->invoice_number }}</span>
<x-status-badge :status="$o->current_status" />
</div>
<p class="mt-1 text-sm text-ink-2">{{ $o->customer->name }} · {{ $o->service->service_name }} · {{ $o->weight_or_qty }} {{ $o->service->unit_type }} · in {{ $o->created_at->format('d M H:i') }}</p>
<div class="mt-3 flex items-center gap-1">
@foreach($steps as $i => $s)
<span title="{{ $s }}" class="h-1.5 flex-1 rounded-full {{ $i <= $idx ? 'bg-ink' : 'bg-line' }}"></span>
@endforeach
</div>
<p class="mt-1.5 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Step {{ $idx + 1 }}/6 - {{ $o->current_status }}</p>
</div>
<form method="POST" action="{{ route('operations.status', $o) }}" class="flex flex-col gap-2 lg:justify-center">@csrf
<div class="flex gap-2">
<select name="status" required class="h-10 flex-1 rounded-md border border-line-strong bg-white px-2.5 text-[13px] focus:border-ink focus:outline-none">@foreach(['Washing','Drying','Ironing','Ready','Completed'] as $s)<option value="{{ $s }}" @selected($s === $steps[min($idx + 1, 5)])>→ {{ $s }}</option>@endforeach</select>
<button class="h-10 shrink-0 rounded-md bg-ink px-4 text-[13px] font-semibold text-white hover:bg-black">Update</button>
</div>
<input name="notes" placeholder="Note (optional)" class="h-9 rounded-md border border-line bg-white px-2.5 text-[13px] placeholder:text-muted focus:border-ink focus:outline-none">
</form>
</div>
@empty
<p class="border-b border-line py-10 text-center text-[13px] text-muted">Queue empty. All laundry done.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
