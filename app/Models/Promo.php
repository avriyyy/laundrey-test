<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'percent',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'promo_service')->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isValidFor(int $serviceId): bool
    {
        if (! $this->active) {
            return false;
        }

        $today = today();

        if ($this->starts_at && $today->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $today->gt($this->ends_at)) {
            return false;
        }

        return $this->services()->where('services.id', $serviceId)->exists();
    }
}
