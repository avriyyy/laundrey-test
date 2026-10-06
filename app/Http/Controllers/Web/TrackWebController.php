<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackWebController extends Controller
{
    public function index(): View
    {
        return view('tracking.index', array_merge(['order' => null, 'prefill' => request()->query('invoice', '')], $this->extras()));
    }

    public function track(Request $request): View
    {
        $data = $request->validate(['invoice_number' => ['required', 'string', 'max:30']]);

        $order = Order::where('invoice_number', $data['invoice_number'])
            ->with(['customer', 'service', 'tenant', 'tracks.updater'])
            ->first();

        if ($order === null) {
            return view('tracking.index', array_merge(['order' => null], $this->extras()))
                ->withErrors(['invoice_number' => 'Receipt not found']);
        }

        return view('tracking.index', array_merge(compact('order'), $this->extras()));
    }

    /** @return array<string, mixed> */
    private function extras(): array
    {
        return [];
    }
}
