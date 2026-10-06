@extends('layouts.app')
@section('title', 'Services & pricing - Laundrey')
@section('breadcrumb', 'Services & pricing')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Manage rates</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Price board.</h1>
<div class="mt-8 border border-line bg-white">
<div class="border-b border-line px-5 py-4">
<p class="text-sm font-semibold">Add service</p>
<form method="POST" action="{{ route('services.store') }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">@csrf
<input name="service_name" placeholder="Service name" required class="h-10 rounded-md border border-line bg-paper px-3 text-sm focus:border-ink focus:bg-white focus:outline-none">
<input name="price_per_unit" type="number" min="0" placeholder="Price Rp" required class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm focus:border-ink focus:bg-white focus:outline-none">
<select name="unit_type" class="h-10 rounded-md border border-line bg-paper px-3 text-sm"><option value="kg">per kg</option><option value="pcs">per pcs</option></select>
<input name="estimated_hours" type="number" min="1" value="24" title="Estimated hours" class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm">
<button class="h-10 whitespace-nowrap rounded-md bg-ink px-4 text-sm font-semibold text-white hover:bg-black">Save</button>
</form>
</div>
<div class="overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Service</th><th class="px-4 py-2.5">Price</th><th class="px-4 py-2.5">Turnaround</th><th class="px-4 py-2.5">Orders</th><th class="px-5 py-2.5 text-right">Action</th></tr></thead>
<tbody>
@forelse($services as $s)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-medium">{{ $s->service_name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}<span class="font-normal text-muted">/{{ $s->unit_type }}</span></td>
<td class="px-4 py-3 text-ink-2">{{ $s->estimated_hours }} hrs</td>
<td class="px-4 py-3 font-mono text-[13px] tabular-nums">{{ $s->orders_count }}</td>
<td class="px-5 py-3 text-right"><form method="POST" action="{{ route('services.destroy', $s) }}" onsubmit="return confirm('Delete Service? This action cannot be undone.')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form></td>
</tr>
@empty
<tr><td colspan="5" class="px-5 py-8 text-center text-[13px] text-muted">No services yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
</div>
<div class="mt-4 text-[13px]">{{ $services->links() }}</div>
@endsection
