<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Order::query()->with(['customer', 'service'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereNotIn('current_status', ['Completed']);

        if ($request->filled('cari')) {
            $kueri->where('invoice_number', 'like', '%'.$request->query('cari').'%');
        }

        $orders = $kueri->orderBy('created_at')->paginate(12)->withQueryString();

        return view('operations.index', compact('orders'));
    }

    public function updateStatus(Request $request, int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($order->current_status === 'Completed') {
            return back()->withErrors(['status' => 'Order is already completed']);
        }

        if ($data['status'] === 'Completed' && auth()->user()->role !== 'admin') {
            return back()->withErrors(['status' => 'Only an admin can complete orders']);
        }

        OrderTrack::create([
            'order_id' => $order->id,
            'updated_by' => auth()->id(),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $order->update(['current_status' => $data['status']]);

        if ($data['status'] === 'Completed' && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid']);
        }

        return back()->with('sukses', 'Status updated to '.$data['status']);
    }
}
