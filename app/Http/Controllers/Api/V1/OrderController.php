<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $kueri = Order::query()->with(['customer', 'service'])
            ->where('tenant_id', $request->user()->tenant_id);

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

        if ($request->filled('payment_status')) {
            $kueri->where('payment_status', $request->query('payment_status'));
        }

        $kueri->orderBy('created_at', 'desc');

        $perHalaman = min($request->integer('per_halaman', 10), 100);

        return OrderResource::collection($kueri->paginate($perHalaman));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();
        $service = Service::where('tenant_id', $tenantId)->findOrFail($data['service_id']);
        $customer = $this->resolveCustomer($tenantId, $data);

        $order = DB::transaction(function () use ($data, $request, $service, $customer, $tenantId) {
            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber($request->user()->tenant->prefix, $tenantId),
                'tenant_id' => $tenantId,
                'user_id' => $customer->id,
                'service_id' => $data['service_id'],
                'weight_or_qty' => $data['weight_or_qty'],
                'total_price' => (float) $data['weight_or_qty'] * (float) $service->price_per_unit,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'current_status' => 'Received',
            ]);

            OrderTrack::create([
                'order_id' => $order->id,
                'updated_by' => $request->user()->id,
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);

            return $order;
        });

        $order->load(['customer', 'service', 'tracks']);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Order berhasil dibuat',
            'data' => new OrderResource($order),
        ], 201);
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
            'email' => 'walkin-'.now()->format('YmdHis').'-'.str()->random(6).'@laundrey.local',
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => $phone,
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $item = Order::where('tenant_id', $request->user()->tenant_id)->findOrFail($order);
        $item->load(['customer', 'service', 'tracks.updater']);

        return response()->json([
            'sukses' => true,
            'data' => new OrderResource($item),
        ]);
    }

    public function update(UpdateOrderRequest $request, int $order): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $item = Order::where('tenant_id', $tenantId)->findOrFail($order);
        $data = $request->validated();

        if (isset($data['service_id']) || isset($data['weight_or_qty'])) {
            $serviceId = $data['service_id'] ?? $item->service_id;
            $weight = (float) ($data['weight_or_qty'] ?? $item->weight_or_qty);
            $service = Service::where('tenant_id', $tenantId)->findOrFail($serviceId);
            $data['service_id'] = $serviceId;
            $data['total_price'] = $weight * (float) $service->price_per_unit;
        }

        $item->update($data);
        $item->load(['customer', 'service', 'tracks']);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Order berhasil diperbarui',
            'data' => new OrderResource($item),
        ]);
    }
}
