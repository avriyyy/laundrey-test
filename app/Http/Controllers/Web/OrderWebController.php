<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Order::query()->with(['customer', 'service']);

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('invoice_number', 'like', '%'.$kataKunci.'%')
                    ->orWhereHas('customer', function ($q) use ($kataKunci) {
                        $q->where('name', 'like', '%'.$kataKunci.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $kueri->where('current_status', $request->query('status'));
        }

        $orders = $kueri->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(): View
    {
        $services = Service::orderBy('service_name')->get();
        $customers = User::where('role', 'pelanggan')->orderBy('name')->get();

        return view('orders.create', compact('services', 'customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['required_without:user_id', 'nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'exists:services,id'],
            'weight_or_qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'in:unpaid,paid'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $customer = $this->resolveCustomer($data);

        DB::transaction(function () use ($data, $service, $customer) {
            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber(),
                'user_id' => $customer->id,
                'service_id' => $data['service_id'],
                'weight_or_qty' => $data['weight_or_qty'],
                'total_price' => (float) $data['weight_or_qty'] * (float) $service->price_per_unit,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'current_status' => 'Received',
            ]);

            OrderTrack::create([
                'order_id' => $order->id,
                'updated_by' => auth()->id(),
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);
        });

        return redirect()->route('orders.index')->with('sukses', 'Order recorded.');
    }

    private function resolveCustomer(array $data): User
    {
        if (! empty($data['user_id'])) {
            return User::findOrFail($data['user_id']);
        }

        $phone = $data['customer_phone'] ?? null;

        if ($phone) {
            $existing = User::where('role', 'pelanggan')->where('phone', $phone)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return User::create([
            'name' => $data['customer_name'],
            'email' => 'walkin-'.now()->format('YmdHis').'-'.str()->random(6).'@laundrey.local',
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => $phone,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'service', 'tracks.updater']);

        return view('orders.show', compact('order'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['sometimes', 'in:unpaid,paid'],
            'weight_or_qty' => ['sometimes', 'numeric', 'min:0.1'],
        ]);

        if (isset($data['weight_or_qty'])) {
            $data['total_price'] = (float) $data['weight_or_qty'] * (float) $order->service->price_per_unit;
        }

        $order->update($data);

        return back()->with('sukses', 'Order updated.');
    }

    public function edit(Order $order): RedirectResponse
    {
        return redirect()->route('orders.show', $order);
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('orders.index')->with('sukses', 'Order deleted.');
    }
}
