@extends('layouts.app')
@section('title', 'Register laundry - Laundrey')
@section('content')
<div class="mx-auto max-w-sm py-8 md:py-14">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<h1 class="mt-6 font-display text-3xl font-bold tracking-tight">Register.</h1>
<p class="mt-1.5 text-sm text-ink-2">One account per shop. Your receipts carry your own 3-letter code.</p>
<form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-4 border-t border-ink pt-6">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name <span class="text-red-600">*</span></label><input name="laundry_name" value="{{ old('laundry_name') }}" placeholder="e.g. Quick Wash Purwokerto" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Receipt prefix (3 capital letters) <span class="text-red-600">*</span></label><input name="prefix" value="{{ old('prefix') }}" placeholder="e.g. QWP" required maxlength="3" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div class="border-t border-line pt-4"><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name <span class="text-red-600">*</span></label><input name="name" value="{{ old('name') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email <span class="text-red-600">*</span></label><input type="email" name="email" value="{{ old('email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone <span class="normal-case tracking-normal">(optional)</span></label><input name="phone" value="{{ old('phone') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-2 gap-3">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password <span class="text-red-600">*</span></label><input type="password" name="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Repeat <span class="text-red-600">*</span></label><input type="password" name="password_confirmation" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
<button class="h-11 rounded-md bg-ink text-sm font-semibold text-white hover:bg-black">Register shop →</button>
</form>
<p class="mt-5 border-t border-line pt-4 text-[13px] text-ink-2">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-ink underline">Log in here</a></p>
</div>
@endsection
