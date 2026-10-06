@extends('layouts.app')
@section('title', 'Record order - Laundrey')
@section('breadcrumb', 'Orders / Record')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New transaction</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Record order.</h1>
<div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
<form method="POST" action="{{ route('orders.store') }}" class="flex flex-col gap-5">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Customer (registered)</label><select id="customerSelect" name="user_id" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- walk-in / new -</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->customerCode() }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>@endforeach</select></div>
<div id="walkinFields" class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Walk-in name</label><input id="walkinName" name="customer_name" value="{{ old('customer_name') }}" placeholder="e.g. Sinta" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Walk-in phone</label><input id="walkinPhone" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="08…" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
<div id="lookupBox" class="hidden border border-line bg-white px-3 py-2"></div>
<p class="text-xs text-muted">Pick a registered customer, or leave walk-in and type a name. Typing checks the file live - a match reuses it, otherwise a new file is created.</p>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Service</label><select id="serviceSelect" name="service_id" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- pick -</option>@foreach($services as $s)<option value="{{ $s->id }}" data-price="{{ $s->price_per_unit }}" data-unit="{{ $s->unit_type }}">{{ $s->service_name }} — Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}/{{ $s->unit_type }}</option>@endforeach</select></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Weight / qty</label><input id="weightInput" type="number" step="0.1" min="0.1" name="weight_or_qty" placeholder="3.5" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Promo code (optional)</label><input name="promo_code" value="{{ old('promo_code') }}" placeholder="e.g. HEMAT10" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Paid</label><select name="payment_status" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
</div>
<div class="flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save order</button><a href="{{ route('orders.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
<aside class="h-fit border border-line bg-white lg:sticky lg:top-20">
<div class="border-b border-dashed border-line-strong px-5 py-4">
<p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Estimate</p>
<p id="totalPreview" class="mt-1 font-mono text-2xl font-bold tabular-nums">Rp0</p>
<p id="calcPreview" class="mt-0.5 font-mono text-xs text-muted">-</p>
</div>
<p class="px-5 py-3 font-mono text-[11px] leading-relaxed text-muted">Receipt issued automatically.<br>Initial Received log recorded.</p>
</aside>
</div>
<script>
const cust = document.getElementById('customerSelect');
const walkin = document.getElementById('walkinFields');
const svc = document.getElementById('serviceSelect');
const w = document.getElementById('weightInput');
const total = document.getElementById('totalPreview');
const calc = document.getElementById('calcPreview');
function fmt(n) { return 'Rp' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function update() {
  const opt = svc.selectedOptions[0];
  const price = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
  const weight = parseFloat(w.value) || 0;
  if (price > 0 && weight > 0) {
    total.textContent = fmt(price * weight);
    calc.textContent = weight + ' ' + opt.dataset.unit + ' x ' + fmt(price);
  } else { total.textContent = 'Rp0'; calc.textContent = '-'; }
}
svc.addEventListener('change', update);
w.addEventListener('input', update);
function toggleWalkin() { walkin.style.display = cust.value ? 'none' : ''; }
cust.addEventListener('change', toggleWalkin);
toggleWalkin();
const wn = document.getElementById('walkinName');
const wp = document.getElementById('walkinPhone');
const lb = document.getElementById('lookupBox');
let timer = null;
async function lookup() {
  const q = (wp.value || wn.value || '').trim();
  if (cust.value || q.length < 2) { lb.classList.add('hidden'); lb.innerHTML = ''; return; }
  try {
    const r = await fetch('{{ route('customers.lookup') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
    const j = await r.json();
    if (!j.data.length) {
      lb.classList.remove('hidden');
      lb.innerHTML = '<p class="py-1 font-mono text-xs text-muted">No file found - a new customer will be created.</p>';
      return;
    }
    lb.classList.remove('hidden');
    lb.innerHTML = j.data.map(m =>
      `<button type="button" data-id="${m.id}" class="flex w-full items-center justify-between gap-2 py-1.5 text-left"><span class="text-[13px]"><b>${m.name}</b> <span class="font-mono text-[11px] text-muted">${m.code}${m.phone ? ' · ' + m.phone : ''} · ${m.orders} loads</span></span><span class="shrink-0 font-mono text-[11px] font-bold uppercase tracking-widest text-primary">Use</span></button>`
    ).join('');
    lb.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
      cust.value = b.dataset.id;
      toggleWalkin();
      lb.classList.add('hidden');
    }));
  } catch (e) {}
}
wn.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 350); });
wp.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 350); });
</script>
@endsection
