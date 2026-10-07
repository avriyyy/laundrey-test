<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Promo name</label><input name="name" value="{{ old('name', $promo->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-3 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Discount %</label><input name="percent" type="number" min="1" max="100" value="{{ old('percent', $promo->percent ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Min qty</label><input name="min_qty" type="number" step="0.1" min="0" value="{{ old('min_qty', $promo->min_qty ?? 0) }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Min unit</label><select name="min_unit" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"><option value="kg" @selected(old('min_unit', $promo->min_unit ?? 'kg') === 'kg')>kg</option><option value="pcs" @selected(old('min_unit', $promo->min_unit ?? 'kg') === 'pcs')>pcs</option></select></div>
</div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Starts</label><input name="starts_at" type="date" value="{{ old('starts_at', isset($promo) && $promo->starts_at ? $promo->starts_at->format('Y-m-d') : '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Ends</label><input name="ends_at" type="date" value="{{ old('ends_at', isset($promo) && $promo->ends_at ? $promo->ends_at->format('Y-m-d') : '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"></div>
</div>
<p class="text-xs text-muted">Attach this promo to services from the service form. It applies automatically when the weight meets the minimum.</p>
</div>
