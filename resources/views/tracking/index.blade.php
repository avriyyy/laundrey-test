@extends('layouts.app')
@section('title', 'Laundrey - laundry tracking made simple')
@section('breadcrumb', 'Laundrey')
@section('content')
{{-- Hero --}}
<div class="grid grid-cols-1 gap-10 md:grid-cols-2">
<div class="flex flex-col">
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Laundrey · Laundry tracking</p>
<h1 class="mt-3 font-display text-5xl font-bold leading-[1.02] tracking-tight md:text-6xl">Know exactly where your laundry is.</h1>
<p class="mt-5 max-w-md text-base leading-relaxed text-ink-2">Drop off your clothes, keep the receipt, and follow every stage online - from intake to ready for pickup. No calls, no guessing.</p>
@if(isset($order) && $order && $order->tenant)
<div class="relative mt-6 flex max-w-md flex-1 flex-col justify-center border border-line bg-white px-6 py-8">
<p class="absolute left-6 top-4 font-mono text-[11px] uppercase tracking-[0.22em] text-muted">This receipt belongs to</p>
<div class="text-center">
<p class="font-display text-3xl font-bold tracking-tight md:text-4xl">{{ $order->tenant->name }}<span class="text-primary">.</span></p>
<p class="mt-2 font-mono text-xs text-muted">{{ $order->tenant->prefix }} receipts · {{ $order->invoice_number }}</p>
</div>
</div>
@endif
</div>
<div>
<div class="border border-line bg-white">
<div class="border-b border-line px-5 py-3 font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Track a receipt</div>
<div class="px-5 py-5">
<form method="POST" action="{{ route('track.search') }}" class="flex flex-col gap-2 sm:flex-row">@csrf
<input name="invoice_number" value="{{ old('invoice_number', $order->invoice_number ?? ($prefill ?? '')) }}" placeholder="INV-20261006-001" required spellcheck="false"
class="h-12 flex-1 rounded-md border border-line-strong bg-white px-4 font-mono text-sm tracking-wide placeholder:text-muted focus:border-ink focus:outline-none">
<button class="h-12 shrink-0 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Track →</button>
</form>
@if(isset($order) && $order)
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
@php($idx = array_search($order->current_status, $steps))
<div class="mt-5 border border-line">
<div class="border-b border-dashed border-line-strong px-5 py-4">
<div class="flex items-baseline justify-between gap-3">
<p class="font-mono text-sm font-bold tracking-wide">{{ $order->invoice_number }}</p>
<x-status-badge :status="$order->current_status" />
</div>
<p class="mt-1 text-[13px] text-ink-2">{{ $order->customer->name }} · {{ $order->service->service_name }} · {{ $order->weight_or_qty }} {{ $order->service->unit_type }}</p>
<div class="mt-2 flex items-baseline justify-between">
<p class="font-mono text-lg font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</p>
<x-payment-badge :status="$order->payment_status" />
</div>
</div>
<div class="px-5 py-4">
@foreach($steps as $i => $s)
@php($t = $order->tracks->firstWhere('status', $s))
<div class="flex items-baseline gap-4 border-b border-line py-2.5 last:border-0">
<span class="w-6 shrink-0 font-mono text-xs text-muted">{{ sprintf('%02d', $i + 1) }}</span>
<span class="flex-1 text-sm {{ $i <= $idx ? 'font-semibold' : 'text-muted' }}">{{ $s }}</span>
<span class="font-mono text-xs text-muted">{{ $t ? $t->created_at->format('d M, H:i') : '--' }}</span>
</div>
@endforeach
</div>
<div class="border-t border-dashed border-line-strong px-5 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">* digital receipt - keep your receipt number</div>
</div>
@endif
</div>
</div>
</div>
</div>

<div class="mt-16 border-t border-ink"></div>

{{-- How it works --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 01 - How it works</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Three steps,<br>done.</h2>
</div>
<div class="border-t border-ink">
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">01</span><div><p class="font-semibold">Drop off and take the receipt</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">The admin records the laundry, weighs it, and issues a receipt number.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">02</span><div><p class="font-semibold">Watch it on this page</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Every stage change is logged by the admin and shows up here.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">03</span><div><p class="font-semibold">Pick up when Ready</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Come with your receipt, settle the bill if unpaid, take it home.</p></div></div>
</div>
</div>

{{-- Start here --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div class="border-t border-ink md:order-1 order-2">
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">01</span><div><p class="font-semibold">Register the shop</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Pick a 3-letter receipt code at <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">/register</a>.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">02</span><div><p class="font-semibold">Record the basics</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Add services, customer files, then daily orders.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">03</span><div><p class="font-semibold">Move stages</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Advance Received to Completed from Operations.</p></div></div>
</div>
<div class="md:order-2">
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 02 - Start here</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Open<br>a shop.</h2>
</div>
</div>

{{-- FAQ --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 03 - Questions</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Asked<br>often.</h2>
</div>
<div class="border-t border-ink">
<div class="border-b border-line py-4"><p class="font-semibold">Do I need an account to track?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">No. The receipt number is enough - tracking here is public and needs no login.</p></div>
<div class="border-b border-line py-4"><p class="font-semibold">What if I lose my receipt number?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Mention your name at the counter. Every load is filed under your customer ID.</p></div>
<div class="border-b border-line py-4"><p class="font-semibold">Can I pay later?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Yes. Unpaid loads can be settled at pickup - the receipt shows the bill status.</p></div>
</div>
</div>

{{-- CTA --}}
@guest
<div class="mt-16 border border-ink bg-white px-6 py-8 text-center md:px-10 md:py-10">
<div class="mx-auto max-w-xl">
<div>
<h2 class="font-display text-2xl font-bold tracking-tight md:text-3xl">Something wrong?</h2>
<p class="mt-2 text-sm leading-relaxed text-ink-2">Wrong status, missing load, or a billing question - report it straight to our email.</p>
</div>
<div class="mt-6 flex justify-center gap-2">
<a href="mailto:help@laundrey.test?subject=Problem%20report&body=Hello%20Laundrey%2C%20I%20want%20to%20report%20a%20problem%3A%20" class="h-11 rounded-md bg-ink px-6 text-sm font-semibold leading-10 text-white hover:bg-black">Report via Email</a>
</div>
</div>
</div>
@endguest
@endsection
