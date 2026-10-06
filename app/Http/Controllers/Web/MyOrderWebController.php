<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyOrderWebController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        if ($order->user_id !== auth()->id()) {
            abort(403, 'This order is not yours.');
        }

        $order->load(['service', 'customer', 'tracks']);

        $tab = $request->query('tab') === 'tracking' ? 'tracking' : 'detail';

        return view('my-orders.show', compact('order', 'tab'));
    }
}
