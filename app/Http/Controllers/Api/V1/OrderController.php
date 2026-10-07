<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $service = Service::where('tenant_id', $tenantId)->with('promos')->findOrFail($data['service_id']);
        $customer = $this->resolveCustomer($tenantId, $data);
        $weight = (float) $data['weight_or_qty'];
        $promo = $service->promoFor($weight);

        $order = DB::transaction(function () use ($data, $request, $service, $customer, $tenantId, $promo) {
            $gross = (float) $data['weight_or_qty'] * (float) $service->price_per_unit;
            $discount = $promo ? (float) $promo->percent : 0;

            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber($request->user()->tenant->prefix, $tenantId),
                'tenant_id' => $tenantId,
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
                'updated_by' => $request->user()->id,
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);

            return $order;
        });

        $order->load(['customer', 'service', 'promo', 'tracks']);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Order berhasil dibuat',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $item = Order::where('tenant_id', $request->user()->tenant_id)->findOrFail($order);
        $item->load(['customer', 'service', 'promo', 'tracks.updater']);

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
        $item->load(['customer', 'service', 'promo', 'tracks']);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Order berhasil diperbarui',
            'data' => new OrderResource($item),
        ]);
    }
}
