@extends('layouts.app')
@section('title', 'Edit tenant - Laundrey')
@section('breadcrumb', 'Tenants / Edit')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $tenant->prefix }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Edit tenant.</h1>
<form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="mt-8">@csrf @method('PUT')
@include('tenants.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save changes</button><a href="{{ route('admin.tenants.show', $tenant) }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
