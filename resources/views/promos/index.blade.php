@extends('layouts.app')
@section('title', 'Promos - Laundrey')
@section('breadcrumb', 'Promos')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Discounts</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Promos.</h1>
<div class="mt-8 border border-line bg-white">
<div class="border-b border-line px-5 py-4">
<p class="text-sm font-semibold">Add promo</p>
<form method="POST" action="{{ route('promos.store') }}">@csrf
<div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_2fr_1fr_1fr_1fr]">
<input name="code" placeholder="CODE" required class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm uppercase focus:border-ink focus:bg-white focus:outline-none">
<input name="name" placeholder="Promo name" required class="h-10 rounded-md border border-line bg-paper px-3 text-sm focus:border-ink focus:bg-white focus:outline-none">
<input name="percent" type="number" min="1" max="100" placeholder="%" required class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm focus:border-ink focus:bg-white focus:outline-none">
<input name="starts_at" type="date" class="h-10 rounded-md border border-line bg-paper px-3 text-sm">
<input name="ends_at" type="date" class="h-10 rounded-md border border-line bg-paper px-3 text-sm">
</div>
<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
@foreach($services as $s)
<label class="flex items-center gap-1.5 text-[13px]"><input type="checkbox" name="service_ids[]" value="{{ $s->id }}" class="accent-black">{{ $s->service_name }}</label>
@endforeach
</div>
<button class="mt-3 h-10 rounded-md bg-ink px-4 text-sm font-semibold text-white hover:bg-black">Save</button>
</form>
</div>
<div class="overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Code</th><th class="px-4 py-2.5">Name</th><th class="px-4 py-2.5">Off</th><th class="px-4 py-2.5">Services</th><th class="px-4 py-2.5">Active</th><th class="px-5 py-2.5 text-right">Action</th></tr></thead>
<tbody>
@forelse($promos as $p)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-mono text-[13px] font-bold">{{ $p->code }}</td>
<td class="px-4 py-3">{{ $p->name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">{{ $p->percent }}%</td>
<td class="px-4 py-3 text-[13px] text-ink-2">{{ $p->services->pluck('service_name')->join(', ') ?: '—' }}</td>
<td class="px-4 py-3 font-mono text-[11px] font-bold uppercase tracking-widest {{ $p->active ? 'text-emerald-700' : 'text-muted' }}">{{ $p->active ? 'Yes' : 'No' }}</td>
<td class="px-5 py-3 text-right"><form method="POST" action="{{ route('promos.destroy', $p) }}" onsubmit="return confirm('Delete this promo?')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form></td>
</tr>
@empty
<tr><td colspan="6" class="px-5 py-8 text-center text-[13px] text-muted">No promos yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
</div>
<div class="mt-4 text-[13px]">{{ $promos->links() }}</div>
@endsection
