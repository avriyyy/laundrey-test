<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $kueri = Service::query();

        if ($request->filled('cari')) {
            $kueri->where('service_name', 'like', '%'.$request->query('cari').'%');
        }

        $kueri->orderBy('service_name');

        $perHalaman = min($request->integer('per_halaman', 10), 100);

        return ServiceResource::collection($kueri->paginate($perHalaman));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil dibuat',
            'data' => new ServiceResource($service),
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'sukses' => true,
            'data' => new ServiceResource($service),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil diperbarui',
            'data' => new ServiceResource($service),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->orders()->exists()) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Layanan dipakai transaksi, tidak bisa dihapus',
            ], 422);
        }

        $service->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil dihapus',
        ]);
    }
}
