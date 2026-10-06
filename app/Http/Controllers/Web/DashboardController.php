<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->role === 'superadmin') {
            return redirect()->route('admin.dashboard');
        }

        $tenantId = auth()->user()->tenant_id;

        $totalOrders = Order::where('tenant_id', $tenantId)->count();
        $processing = Order::where('tenant_id', $tenantId)->whereNotIn('current_status', ['Ready', 'Completed'])->count();
        $ready = Order::where('tenant_id', $tenantId)->where('current_status', 'Ready')->count();
        $revenue = (float) Order::where('tenant_id', $tenantId)->where('payment_status', 'paid')->sum('total_price');
        $recentOrders = Order::with(['customer', 'service'])->where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->limit(8)->get();
        $readyOrders = Order::with(['customer', 'service'])->where('tenant_id', $tenantId)->where('current_status', 'Ready')->orderBy('updated_at')->limit(5)->get();
        $totalServices = Service::where('tenant_id', $tenantId)->count();

        return view('dashboard.admin', compact('totalOrders', 'processing', 'ready', 'revenue', 'recentOrders', 'readyOrders', 'totalServices'));
    }

    public function platform(): View
    {
        $tenants = Tenant::withCount(['users', 'services', 'orders'])->orderBy('name')->limit(8)->get();
        $tenantCount = Tenant::count();
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');

        return view('dashboard.platform', compact('tenants', 'tenantCount', 'totalOrders', 'totalRevenue'));
    }
}
