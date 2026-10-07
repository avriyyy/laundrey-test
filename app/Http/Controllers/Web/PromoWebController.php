<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoWebController extends Controller
{
    public function index(): View
    {
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)
            ->with('services')->orderBy('name')->paginate(10);

        return view('promos.index', compact('promos'));
    }

    public function create(): View
    {
        return view('promos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Promo::create($data + ['tenant_id' => auth()->user()->tenant_id]);

        return redirect()->route('promos.index')->with('sukses', 'Promo added.');
    }

    public function edit(int $promo): View
    {
        $promo = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);

        return view('promos.edit', compact('promo'));
    }

    public function update(Request $request, int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $data = $this->validated($request);

        $item->update($data);

        return redirect()->route('promos.index')->with('sukses', 'Promo updated.');
    }

    public function destroy(int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $item->delete();

        return back()->with('sukses', 'Promo deleted.');
    }

    public function show(int $promo): RedirectResponse
    {
        return redirect()->route('promos.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'percent' => ['required', 'integer', 'min:1', 'max:100'],
            'min_qty' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_unit' => ['required', 'string', 'in:kg,pcs'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
