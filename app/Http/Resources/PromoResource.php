<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'percent' => (int) $this->percent,
            'min_qty' => (float) $this->min_qty,
            'active' => (bool) $this->active,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
