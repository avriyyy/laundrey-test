<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWebController extends Controller
{
    public function index(): View
    {
        $services = Service::withCount('orders')->orderBy('service_name')->paginate(10);

        return view('services.index', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:100'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'in:kg,pcs'],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        Service::create($data);

        return back()->with('sukses', 'Service added.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['sometimes', 'string', 'max:100'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'unit_type' => ['sometimes', 'in:kg,pcs'],
            'estimated_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
        ]);

        $service->update($data);

        return back()->with('sukses', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->orders()->exists()) {
            return back()->withErrors(['service' => 'Service is used by orders and cannot be deleted']);
        }

        $service->delete();

        return back()->with('sukses', 'Service deleted.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('services.index');
    }

    public function show(Service $service): RedirectResponse
    {
        return redirect()->route('services.index');
    }

    public function edit(Service $service): RedirectResponse
    {
        return redirect()->route('services.index');
    }
}
