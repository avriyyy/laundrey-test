<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Tenant::query()->withCount(['users', 'services', 'orders']);

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('prefix', 'like', '%'.$kataKunci.'%');
            });
        }

        $tenants = $kueri->orderBy('name')->paginate(12)->withQueryString();
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');

        return view('tenants.index', compact('tenants', 'totalOrders', 'totalRevenue'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->loadCount(['users', 'services', 'orders']);
        $orders = Order::with(['customer', 'service'])->where('tenant_id', $tenant->id)->orderBy('created_at', 'desc')->paginate(10);
        $revenue = (float) Order::where('tenant_id', $tenant->id)->where('payment_status', 'paid')->sum('total_price');
        $admins = $tenant->users()->where('role', 'admin')->get(['id', 'name', 'email']);

        return view('tenants.show', compact('tenant', 'orders', 'revenue', 'admins'));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('sukses', 'Tenant deleted with all its data.');
    }
}
