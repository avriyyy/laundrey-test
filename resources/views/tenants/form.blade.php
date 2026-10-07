<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name</label><input name="name" value="{{ old('name', $tenant->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Prefix (3 letters)</label><input name="prefix" value="{{ old('prefix', $tenant->prefix ?? '') }}" required maxlength="3" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone</label><input name="phone" value="{{ old('phone', $tenant->phone ?? '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Address</label><textarea name="address" rows="2" class="w-full rounded-md border border-line-strong bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none">{{ old('address', $tenant->address ?? '') }}</textarea></div>
@if(! isset($tenant->id))
<div class="border-t border-line pt-4">
<p class="mb-3 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Login account</p>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name</label><input name="admin_name" value="{{ old('admin_name') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email</label><input name="admin_email" type="email" value="{{ old('admin_email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
<div class="mt-4"><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password (min 8)</label><input name="admin_password" type="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
@endif
</div>
