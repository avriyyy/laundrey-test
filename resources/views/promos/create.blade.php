@extends('layouts.app')
@section('title', 'Add promo - Laundrey')
@section('breadcrumb', 'Promos / Add')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New rule</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Add promo.</h1>
<form method="POST" action="{{ route('promos.store') }}" class="mt-8">@csrf
@include('promos.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save promo</button><a href="{{ route('promos.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
