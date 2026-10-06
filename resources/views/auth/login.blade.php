@extends('layouts.app')
@section('title', 'Log in - Laundrey')
@section('content')
<div class="mx-auto max-w-sm py-8 md:py-14">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<h1 class="mt-6 font-display text-3xl font-bold tracking-tight">Log in.</h1>
<p class="mt-1.5 text-sm text-ink-2">Shop and platform accounts sign in here.</p>
<form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4 border-t border-ink pt-6">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password</label><input type="password" name="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<button class="h-11 rounded-md bg-ink text-sm font-semibold text-white hover:bg-black">Log in →</button>
</form>
<p class="mt-5 border-t border-line pt-4 text-[13px] text-ink-2">No account yet? <a href="{{ route('register') }}" class="font-semibold text-ink underline">Register here</a></p>
</div>
@endsection
