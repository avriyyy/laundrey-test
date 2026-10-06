<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $kueri = Promo::query()->with('services')->where('tenant_id', $request->user()->tenant_id);

        if ($request->filled('cari')) {
            $kueri->where(function ($sub) {
                $sub->where('code', 'like', '%'.$request->query('cari').'%')
                    ->orWhere('name', 'like', '%'.$request->query('cari').'%');
            });
        }

        $kueri->orderBy('code');

        $perHalaman = min($request->integer('per_halaman', 10), 100);

        return PromoResource::collection($kueri->paginate($perHalaman));
    }

    public function store(StorePromoRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $exists = Promo::where('tenant_id', $tenantId)->where('code', $data['code'])->exists();

        if ($exists) {
            return response()->json(['sukses' => false, 'pesan' => 'Promo code already used'], 422);
        }

        $serviceIds = Service::where('tenant_id', $tenantId)
            ->whereIn('id', $data['service_ids'] ?? [])
            ->pluck('id');

        $promo = Promo::create($data + ['tenant_id' => $tenantId]);
        $promo->services()->sync($serviceIds);
        $promo->load('services');

        return response()->json([
            'sukses' => true,
            'pesan' => 'Promo berhasil dibuat',
            'data' => new PromoResource($promo),
        ], 201);
    }

    public function show(Request $request, int $promo): JsonResponse
    {
        $item = Promo::where('tenant_id', $request->user()->tenant_id)->with('services')->findOrFail($promo);

        return response()->json(['sukses' => true, 'data' => new PromoResource($item)]);
    }

    public function destroy(Request $request, int $promo): JsonResponse
    {
        $item = Promo::where('tenant_id', $request->user()->tenant_id)->findOrFail($promo);
        $item->delete();

        return response()->json(['sukses' => true, 'pesan' => 'Promo berhasil dihapus']);
    }
}
