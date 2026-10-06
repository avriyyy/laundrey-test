<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['Received', 'Washing', 'Drying', 'Ironing', 'Ready', 'Completed'];

    protected $fillable = [
        'invoice_number',
        'tenant_id',
        'user_id',
        'service_id',
        'promo_id',
        'weight_or_qty',
        'total_price',
        'discount_percent',
        'payment_status',
        'current_status',
    ];

    protected function casts(): array
    {
        return [
            'weight_or_qty' => 'decimal:2',
            'total_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(OrderTrack::class)->orderBy('created_at');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generateInvoiceNumber(string $prefix, int $tenantId): string
    {
        $urutan = Order::where('tenant_id', $tenantId)->whereDate('created_at', today())->count() + 1;

        do {
            $invoice = $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
            $urutan++;
        } while (Order::where('invoice_number', $invoice)->exists());

        return $invoice;
    }
}
