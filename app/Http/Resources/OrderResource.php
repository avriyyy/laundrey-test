<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'weight_or_qty' => (float) $this->weight_or_qty,
            'total_price' => (float) $this->total_price,
            'discount_percent' => (float) $this->discount_percent,
            'promo' => $this->whenLoaded('promo', function () {
                return $this->promo ? [
                    'id' => $this->promo->id,
                    'code' => $this->promo->code,
                    'percent' => (int) $this->promo->percent,
                ] : null;
            }),
            'payment_status' => $this->payment_status,
            'current_status' => $this->current_status,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'service_name' => $this->service->service_name,
                    'price_per_unit' => (float) $this->service->price_per_unit,
                    'unit_type' => $this->service->unit_type,
                ];
            }),
            'tracks' => OrderTrackResource::collection($this->whenLoaded('tracks')),
            'dibuat_pada' => $this->created_at->toIso8601String(),
        ];
    }
}
