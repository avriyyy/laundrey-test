<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === 'pelanggan') {
            $orders = Order::with(['service', 'tracks'])->where('user_id', $user->id)->orderBy('created_at', 'desc')->paginate(10);
            $activeCount = Order::where('user_id', $user->id)->whereNotIn('current_status', ['Completed'])->count();
            $readyCount = Order::where('user_id', $user->id)->where('current_status', 'Ready')->count();
            $doneCount = Order::where('user_id', $user->id)->where('current_status', 'Completed')->count();
            $totalSpent = (float) Order::where('user_id', $user->id)->where('payment_status', 'paid')->sum('total_price');

            return view('dashboard.pelanggan', compact('orders', 'activeCount', 'readyCount', 'doneCount', 'totalSpent'));
        }

        $totalOrders = Order::count();
        $processing = Order::whereNotIn('current_status', ['Ready', 'Completed'])->count();
        $ready = Order::where('current_status', 'Ready')->count();
        $revenue = (float) Order::where('payment_status', 'paid')->sum('total_price');
        $recentOrders = Order::with(['customer', 'service'])->orderBy('created_at', 'desc')->limit(8)->get();
        $readyOrders = Order::with(['customer', 'service'])->where('current_status', 'Ready')->orderBy('updated_at')->limit(5)->get();
        $totalServices = Service::count();

        return view('dashboard.admin', compact('totalOrders', 'processing', 'ready', 'revenue', 'recentOrders', 'readyOrders', 'totalServices'));
    }
}
