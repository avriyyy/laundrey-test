@extends('layouts.app')
@section('title', 'Promos - Laundrey')
@section('breadcrumb', 'Promos')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Discounts</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Promos.</h1>
</div>
<a href="{{ route('promos.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Add promo</a>
</div>
<div class="mt-6 overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Name</th><th class="px-4 py-2.5">Off</th><th class="px-4 py-2.5">Min qty</th><th class="px-4 py-2.5">Services</th><th class="px-4 py-2.5">Active</th><th class="px-5 py-2.5 text-right">Actions</th></tr></thead>
<tbody>
@forelse($promos as $p)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-medium">{{ $p->name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">{{ $p->percent }}%</td>
<td class="px-4 py-3 font-mono text-[13px] tabular-nums">{{ $p->min_qty }}{{ $p->min_unit }}+</td>
<td class="px-4 py-3 text-[13px] text-ink-2">{{ $p->services->pluck('service_name')->join(', ') ?: '—' }}</td>
<td class="px-4 py-3 font-mono text-[11px] font-bold uppercase tracking-widest {{ $p->active ? 'text-emerald-700' : 'text-muted' }}">{{ $p->active ? 'Yes' : 'No' }}</td>
<td class="px-5 py-3 text-right">
<a href="{{ route('promos.edit', $p) }}" class="mr-3 font-mono text-[11px] uppercase tracking-widest text-ink-2 hover:text-ink">Edit</a>
<form method="POST" action="{{ route('promos.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete this promo?')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-5 py-8 text-center text-[13px] text-muted">No promos yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
<div class="mt-4 text-[13px]">{{ $promos->links() }}</div>
@endsection
