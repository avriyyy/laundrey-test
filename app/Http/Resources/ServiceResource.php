<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_name' => $this->service_name,
            'price_per_unit' => (float) $this->price_per_unit,
            'unit_type' => $this->unit_type,
            'estimated_hours' => $this->estimated_hours,
            'dibuat_pada' => $this->created_at->toIso8601String(),
        ];
    }
}
