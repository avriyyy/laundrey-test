<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoWebController extends Controller
{
    public function index(): View
    {
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)
            ->with('services')->orderBy('code')->paginate(10);
        $services = Service::where('tenant_id', auth()->user()->tenant_id)->orderBy('service_name')->get();

        return view('promos.index', compact('promos', 'services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $exists = Promo::where('tenant_id', auth()->user()->tenant_id)->where('code', $data['code'])->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'Promo code already used'])->onlyInput();
        }

        $serviceIds = Service::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('id', $data['service_ids'] ?? [])
            ->pluck('id');

        $promo = Promo::create($data + ['tenant_id' => auth()->user()->tenant_id]);
        $promo->services()->sync($serviceIds);

        return back()->with('sukses', 'Promo added.');
    }

    public function update(Request $request, int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $data = $this->validated($request, $item->id);

        $exists = Promo::where('tenant_id', auth()->user()->tenant_id)
            ->where('code', $data['code'])->where('id', '!=', $item->id)->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'Promo code already used'])->onlyInput();
        }

        $serviceIds = Service::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('id', $data['service_ids'] ?? [])
            ->pluck('id');

        $item->update($data);
        $item->services()->sync($serviceIds);

        return back()->with('sukses', 'Promo updated.');
    }

    public function destroy(int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $item->delete();

        return back()->with('sukses', 'Promo deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignore = null): array
    {
        if ($request->input('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'percent' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('promos.index');
    }

    public function show(int $promo): RedirectResponse
    {
        return redirect()->route('promos.index');
    }

    public function edit(int $promo): RedirectResponse
    {
        return redirect()->route('promos.index');
    }
}
