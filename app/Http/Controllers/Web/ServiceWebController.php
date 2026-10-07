<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWebController extends Controller
{
    public function index(): View
    {
        $services = Service::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('orders')->orderBy('service_name')->paginate(10);

        return view('services.index', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:100'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'in:kg,pcs'],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
        ]);

        $promoIds = $this->scopedPromoIds($data['promo_id'] ?? null);
        unset($data['promo_id']);

        $service = Service::create($data + ['tenant_id' => auth()->user()->tenant_id]);
        $service->promos()->sync($promoIds);

        return redirect()->route('services.index')->with('sukses', 'Service added.');
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $item = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($service);

        $data = $request->validate([
            'service_name' => ['sometimes', 'string', 'max:100'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'unit_type' => ['sometimes', 'in:kg,pcs'],
            'estimated_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
        ]);

        $promoIds = $this->scopedPromoIds($data['promo_id'] ?? null);
        unset($data['promo_id']);

        $item->update($data);
        $item->promos()->sync($promoIds);

        return redirect()->route('services.index')->with('sukses', 'Service updated.');
    }

    /** @return array<int> */
    private function scopedPromoIds(mixed $promoId): array
    {
        if (empty($promoId)) {
            return [];
        }

        $exists = Promo::where('tenant_id', auth()->user()->tenant_id)->whereKey($promoId)->exists();

        return $exists ? [(int) $promoId] : [];
    }

    public function destroy(int $service): RedirectResponse
    {
        $item = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($service);

        if ($item->orders()->exists()) {
            return back()->withErrors(['service' => 'Service is used by orders and cannot be deleted']);
        }

        $item->delete();

        return back()->with('sukses', 'Service deleted.');
    }

    public function create(): View
    {
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('services.create', compact('promos'));
    }

    public function show(int $service): RedirectResponse
    {
        return redirect()->route('services.index');
    }

    public function edit(int $service): View
    {
        $service = Service::where('tenant_id', auth()->user()->tenant_id)->with('promos')->findOrFail($service);
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('services.edit', compact('service', 'promos'));
    }
}
