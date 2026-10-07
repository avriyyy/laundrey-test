<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Promo;
use App\Models\Service;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Order::query()->with(['customer', 'service'])
            ->where('tenant_id', auth()->user()->tenant_id);

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
        $tenantId = auth()->user()->tenant_id;
        $services = Service::where('tenant_id', $tenantId)->orderBy('service_name')->get();
        $customers = User::where('tenant_id', $tenantId)->where('role', 'pelanggan')->orderBy('name')->get();
        $promos = Promo::where('tenant_id', $tenantId)->with('services:id')->orderBy('name')->get(['id', 'name', 'percent', 'min_qty', 'min_unit', 'active', 'starts_at', 'ends_at']);
        $promoOptions = $promos->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'percent' => $p->percent,
            'min_qty' => (float) $p->min_qty, 'min_unit' => $p->min_unit, 'active' => (bool) $p->active,
            'services' => $p->services->pluck('id')->toArray(),
        ])->toJson();

        return view('orders.create', compact('services', 'customers', 'promoOptions'));
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

        $service = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($data['service_id']);
        $customer = $this->resolveCustomer(auth()->user()->tenant_id, $data);
        $service->load('promos');
        $promo = $service->promoFor((float) $data['weight_or_qty']);

        DB::transaction(function () use ($data, $service, $customer, $promo, &$invoiceId) {
            $gross = (float) $data['weight_or_qty'] * (float) $service->price_per_unit;
            $discount = $promo ? (float) $promo->percent : 0;

            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber(auth()->user()->tenant->prefix, auth()->user()->tenant_id),
                'tenant_id' => auth()->user()->tenant_id,
                'user_id' => $customer->id,
                'service_id' => $data['service_id'],
                'promo_id' => $promo?->id,
                'weight_or_qty' => $data['weight_or_qty'],
                'total_price' => $gross * (1 - $discount / 100),
                'discount_percent' => $discount,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'current_status' => 'Received',
            ]);

            OrderTrack::create([
                'order_id' => $order->id,
                'updated_by' => auth()->id(),
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);

            $invoiceId = $order->id;
        });

        return redirect()->route('orders.show', $invoiceId)->with('sukses', 'Order recorded.');
    }

    private function resolveCustomer(int $tenantId, array $data): User
    {
        if (! empty($data['user_id'])) {
            return User::where('tenant_id', $tenantId)->where('role', 'pelanggan')->findOrFail($data['user_id']);
        }

        $phone = $data['customer_phone'] ?? null;

        if ($phone) {
            $existing = User::where('tenant_id', $tenantId)->where('role', 'pelanggan')->where('phone', $phone)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return User::create([
            'tenant_id' => $tenantId,
            'name' => $data['customer_name'],
            'email' => null,
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => $phone,
        ]);
    }

    public function show(int $order): View
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'promo', 'tracks.updater']);

        return view('orders.show', compact('order'));
    }

    public function update(Request $request, int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);

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

    public function edit(int $order): RedirectResponse
    {
        return redirect()->route('orders.show', $order);
    }

    public function destroy(int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->delete();

        return redirect()->route('orders.index')->with('sukses', 'Order deleted.');
    }

    public function invoice(int $order): View
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'tenant', 'tracks']);

        return view('orders.invoice', compact('order'));
    }

    public function invoicePdf(int $order)
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'tenant', 'tracks']);

        return Pdf::loadView('orders.invoice', compact('order'))
            ->setPaper('a5', 'portrait')
            ->download($order->invoice_number.'.pdf');
    }
}
