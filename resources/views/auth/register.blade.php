@extends('layouts.app')
@section('title', 'Sign up - Laundrey')
@section('content')
<div class="mx-auto max-w-sm py-8 md:py-14">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<h1 class="mt-6 font-display text-3xl font-bold tracking-tight">Sign up.</h1>
<p class="mt-1.5 text-sm text-ink-2">A customer account to follow all your laundry.</p>
<form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-4 border-t border-ink pt-6">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Name</label><input name="name" value="{{ old('name') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone</label><input name="phone" value="{{ old('phone') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-2 gap-3">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password</label><input type="password" name="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Repeat</label><input type="password" name="password_confirmation" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
<button class="h-11 rounded-md bg-ink text-sm font-semibold text-white hover:bg-black">Create account →</button>
</form>
</div>
@endsection
