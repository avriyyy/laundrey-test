<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTrack;
use Illuminate\Http\JsonResponse;

class TrackController extends Controller
{
    public function publicTrack(string $invoice_number): JsonResponse
    {
        $order = Order::where('invoice_number', $invoice_number)
            ->with(['customer', 'service', 'tracks.updater'])
            ->first();

        if ($order === null) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Resi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'sukses' => true,
            'data' => new OrderResource($order),
        ]);
    }

    public function store(StoreTrackRequest $request, Order $order): JsonResponse
    {
        if ($order->tenant_id !== $request->user()->tenant_id) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Sumber daya tidak ditemukan',
            ], 404);
        }

        $data = $request->validated();

        if ($order->current_status === 'Completed') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Order sudah selesai, status tidak bisa diubah',
            ], 422);
        }

        if ($data['status'] === 'Completed' && $request->user()->role !== 'tenant') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Hanya operator tenant yang bisa menyelesaikan order',
            ], 403);
        }

        OrderTrack::create([
            'order_id' => $order->id,
            'updated_by' => $request->user()->id,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $order->update(['current_status' => $data['status']]);

        if ($data['status'] === 'Completed' && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid']);
        }

        $order->load(['customer', 'service', 'tracks.updater']);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Status berhasil diperbarui ke '.$data['status'],
            'data' => new OrderResource($order),
        ], 201);
    }
}
