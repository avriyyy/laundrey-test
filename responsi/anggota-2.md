# anggota-2 — bahtiar: Services, Orders, Tracking & Struk

**CRUD milikmu (backend + halaman): Services, Orders** (+ tracking & struk).

**Branch:** `bahtiar` (buat setelah `pika` merge: `git checkout main && git pull && git checkout -b bahtiar`; merge via PR setelah checklist hijau).

## Langkah 1 — Migration + model + invoice generator

`Order::STATUSES` + `generateInvoiceNumber()` (urut harian + anti-tabrakan). `php artisan migrate`. **Commit:** `feat: service order track`

**Path:** `database/migrations/2026_10_06_054615_create_services_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 100);
            $table->decimal('price_per_unit', 12, 2);
            $table->string('unit_type', 20)->default('kg');
            $table->integer('estimated_hours')->default(24);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
```

**Path:** `database/migrations/2026_10_06_054616_create_orders_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->decimal('weight_or_qty', 8, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('current_status', 20)->default('Received');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

**Path:** `database/migrations/2026_10_06_054617_create_order_tracks_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracks');
    }
};
```

**Path:** `app/Models/Service.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_name',
        'price_per_unit',
        'unit_type',
        'estimated_hours',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
            'estimated_hours' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function promos(): BelongsToMany
    {
        return $this->belongsToMany(Promo::class, 'promo_service')->withTimestamps();
    }

    public function promoFor(float $qty): ?Promo
    {
        return $this->promos->first(fn ($promo) => $promo->isValidFor($this->id, $qty, $this->unit_type));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

**Path:** `app/Models/Order.php`

```php
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
```

**Path:** `app/Models/OrderTrack.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'updated_by',
        'status',
        'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

## Langkah 2 — Factory, seeder, request, resource

Lengkapi seeder 8 order di `LaundreySeeder`. **Cek:** `migrate:fresh --seed`. **Commit:** `feat: factory seed request resource` → push.

**Path:** `database/factories/ServiceFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::first()?->id ?? Tenant::factory()->create()->id,
            'service_name' => fake()->randomElement(['Cuci Kering Reguler', 'Cuci Setrika Express', 'Setrika Saja', 'Cuci Karpet', 'Cuci Sepatu']),
            'price_per_unit' => fake()->randomElement([8000, 10000, 12000, 15000, 25000]),
            'unit_type' => fake()->randomElement(['kg', 'pcs']),
            'estimated_hours' => fake()->randomElement([6, 12, 24, 48]),
        ];
    }
}
```

**Path:** `database/factories/OrderFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::first() ?? Tenant::factory()->create();
        $service = Service::where('tenant_id', $tenant->id)->inRandomOrder()->first()
            ?? Service::factory()->create(['tenant_id' => $tenant->id]);
        $weight = fake()->randomFloat(1, 1, 10);

        return [
            'invoice_number' => $tenant->prefix.'-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'service_id' => $service->id,
            'weight_or_qty' => $weight,
            'total_price' => $weight * (float) $service->price_per_unit,
            'payment_status' => fake()->randomElement(['unpaid', 'paid']),
            'current_status' => fake()->randomElement(['Received', 'Washing', 'Drying', 'Ironing', 'Ready']),
        ];
    }
}
```

**Path:** `database/factories/OrderTrackFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderTrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'updated_by' => User::factory(),
            'status' => fake()->randomElement(['Received', 'Washing', 'Drying', 'Ironing', 'Ready']),
            'notes' => fake()->sentence(),
        ];
    }
}
```

**Path:** `app/Http/Requests/StoreServiceRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['required', 'string', 'max:100'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'string', 'in:kg,pcs'],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ];
    }
}
```

**Path:** `app/Http/Requests/UpdateServiceRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['sometimes', 'string', 'max:100'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'unit_type' => ['sometimes', 'string', 'in:kg,pcs'],
            'estimated_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
        ];
    }
}
```

**Path:** `app/Http/Requests/StoreOrderRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['required_without:user_id', 'nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'weight_or_qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'Layanan tidak ditemukan',
            'user_id.exists' => 'Pelanggan tidak ditemukan',
            'customer_name.required_without' => 'Pilih pelanggan atau isi nama walk-in',
        ];
    }
}
```

**Path:** `app/Http/Requests/UpdateOrderRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight_or_qty' => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ];
    }
}
```

**Path:** `app/Http/Requests/StoreTrackRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status harus salah satu: '.implode(', ', Order::STATUSES),
        ];
    }
}
```

**Path:** `app/Http/Resources/ServiceResource.php`

```php
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
```

**Path:** `app/Http/Resources/OrderResource.php`

```php
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
                    'name' => $this->promo->name,
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
```

**Path:** `app/Http/Resources/OrderTrackResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'notes' => $this->notes,
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'role' => $this->updater->role,
                ];
            }),
            'dibuat_pada' => $this->created_at->toIso8601String(),
        ];
    }
}
```

## Langkah 3 — API order, service, track

Scope tenant везде, walk-in + promo otomatis, transaction. **Cek curl.** **Commit:** `feat: API order service track` → push, kabari yudha.

**Path:** `app/Http/Controllers/Api/V1/ServiceController.php`

```php
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
        $kueri = Service::query()->where('tenant_id', $request->user()->tenant_id);

        if ($request->filled('cari')) {
            $kueri->where('service_name', 'like', '%'.$request->query('cari').'%');
        }

        $kueri->orderBy('service_name');

        $perHalaman = min($request->integer('per_halaman', 10), 100);

        return ServiceResource::collection($kueri->paginate($perHalaman));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated() + ['tenant_id' => $request->user()->tenant_id]);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil dibuat',
            'data' => new ServiceResource($service),
        ], 201);
    }

    public function show(Request $request, int $service): JsonResponse
    {
        $item = Service::where('tenant_id', $request->user()->tenant_id)->findOrFail($service);

        return response()->json([
            'sukses' => true,
            'data' => new ServiceResource($item),
        ]);
    }

    public function update(UpdateServiceRequest $request, int $service): JsonResponse
    {
        $item = Service::where('tenant_id', $request->user()->tenant_id)->findOrFail($service);
        $item->update($request->validated());

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil diperbarui',
            'data' => new ServiceResource($item),
        ]);
    }

    public function destroy(Request $request, int $service): JsonResponse
    {
        $item = Service::where('tenant_id', $request->user()->tenant_id)->findOrFail($service);

        if ($item->orders()->exists()) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Layanan dipakai transaksi, tidak bisa dihapus',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Layanan berhasil dihapus',
        ]);
    }
}
```

**Path:** `app/Http/Controllers/Api/V1/OrderController.php`

```php
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
```

**Path:** `app/Http/Controllers/Api/V1/TrackController.php`

```php
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
```

## Langkah 4 — Halaman service, order, operasi

Dropdown operasi default tahap berikut; estimasi + hint promo live. **Commit:** `feat: halaman service order operasi` → push.

**Path:** `app/Http/Controllers/Web/ServiceWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceWebController extends Controller
{
    public function index(): View
    {
        $services = Service::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('orders')->orderBy('service_name')->paginate(10);

        return view('services.index', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:100'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'in:kg,pcs'],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
        ]);

        $promoIds = $this->scopedPromoIds($data['promo_id'] ?? null);
        unset($data['promo_id']);

        $service = Service::create($data + ['tenant_id' => auth()->user()->tenant_id]);
        $service->promos()->sync($promoIds);

        return redirect()->route('services.index')->with('sukses', 'Service added.');
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $item = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($service);

        $data = $request->validate([
            'service_name' => ['sometimes', 'string', 'max:100'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'unit_type' => ['sometimes', 'in:kg,pcs'],
            'estimated_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
        ]);

        $promoIds = $this->scopedPromoIds($data['promo_id'] ?? null);
        unset($data['promo_id']);

        $item->update($data);
        $item->promos()->sync($promoIds);

        return redirect()->route('services.index')->with('sukses', 'Service updated.');
    }

    /** @return array<int> */
    private function scopedPromoIds(mixed $promoId): array
    {
        if (empty($promoId)) {
            return [];
        }

        $exists = Promo::where('tenant_id', auth()->user()->tenant_id)->whereKey($promoId)->exists();

        return $exists ? [(int) $promoId] : [];
    }

    public function destroy(int $service): RedirectResponse
    {
        $item = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($service);

        if ($item->orders()->exists()) {
            return back()->withErrors(['service' => 'Service is used by orders and cannot be deleted']);
        }

        $item->delete();

        return back()->with('sukses', 'Service deleted.');
    }

    public function create(): View
    {
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('services.create', compact('promos'));
    }

    public function show(int $service): RedirectResponse
    {
        return redirect()->route('services.index');
    }

    public function edit(int $service): View
    {
        $service = Service::where('tenant_id', auth()->user()->tenant_id)->with('promos')->findOrFail($service);
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('services.edit', compact('service', 'promos'));
    }
}
```

**Path:** `app/Http/Controllers/Web/OrderWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrack;
use App\Models\Promo;
use App\Models\Service;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OrderWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Order::query()->with(['customer', 'service'])
            ->where('tenant_id', auth()->user()->tenant_id);

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

        $orders = $kueri->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(): View
    {
        $tenantId = auth()->user()->tenant_id;
        $services = Service::where('tenant_id', $tenantId)->orderBy('service_name')->get();
        $customers = User::where('tenant_id', $tenantId)->where('role', 'pelanggan')->orderBy('name')->get();
        $promos = Promo::where('tenant_id', $tenantId)->with('services:id')->orderBy('name')->get(['id', 'name', 'percent', 'min_qty', 'min_unit', 'active', 'starts_at', 'ends_at']);
        $promoOptions = $promos->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'percent' => $p->percent,
            'min_qty' => (float) $p->min_qty, 'min_unit' => $p->min_unit, 'active' => (bool) $p->active,
            'services' => $p->services->pluck('id')->toArray(),
        ])->toJson();

        return view('orders.create', compact('services', 'customers', 'promoOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['required_without:user_id', 'nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'exists:services,id'],
            'weight_or_qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'in:unpaid,paid'],
        ]);

        $service = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($data['service_id']);
        $customer = $this->resolveCustomer(auth()->user()->tenant_id, $data);
        $service->load('promos');
        $promo = $service->promoFor((float) $data['weight_or_qty']);

        DB::transaction(function () use ($data, $service, $customer, $promo, &$invoiceId) {
            $gross = (float) $data['weight_or_qty'] * (float) $service->price_per_unit;
            $discount = $promo ? (float) $promo->percent : 0;

            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber(auth()->user()->tenant->prefix, auth()->user()->tenant_id),
                'tenant_id' => auth()->user()->tenant_id,
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
                'updated_by' => auth()->id(),
                'status' => 'Received',
                'notes' => 'Order received at counter',
            ]);

            $invoiceId = $order->id;
        });

        return redirect()->route('orders.show', $invoiceId)->with('sukses', 'Order recorded.');
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
            'email' => null,
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => $phone,
        ]);
    }

    public function show(int $order): View
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'promo', 'tracks.updater']);

        return view('orders.show', compact('order'));
    }

    public function update(Request $request, int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);

        $data = $request->validate([
            'payment_status' => ['sometimes', 'in:unpaid,paid'],
            'weight_or_qty' => ['sometimes', 'numeric', 'min:0.1'],
        ]);

        if (isset($data['weight_or_qty'])) {
            $data['total_price'] = (float) $data['weight_or_qty'] * (float) $order->service->price_per_unit;
        }

        $order->update($data);

        return back()->with('sukses', 'Order updated.');
    }

    public function edit(int $order): RedirectResponse
    {
        return redirect()->route('orders.show', $order);
    }

    public function destroy(int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->delete();

        return redirect()->route('orders.index')->with('sukses', 'Order deleted.');
    }

    public function invoice(int $order): View
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'tenant', 'tracks']);

        return view('orders.invoice', compact('order'));
    }

    public function invoicePdf(int $order)
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);
        $order->load(['customer', 'service', 'tenant', 'tracks']);

        return Pdf::loadView('orders.invoice', compact('order'))
            ->setPaper('a5', 'portrait')
            ->download($order->invoice_number.'.pdf');
    }
}
```

**Path:** `app/Http/Controllers/Web/OperationWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Order::query()->with(['customer', 'service'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereNotIn('current_status', ['Completed']);

        if ($request->filled('cari')) {
            $kueri->where('invoice_number', 'like', '%'.$request->query('cari').'%');
        }

        $orders = $kueri->orderBy('created_at')->paginate(12)->withQueryString();

        return view('operations.index', compact('orders'));
    }

    public function updateStatus(Request $request, int $order): RedirectResponse
    {
        $order = Order::where('tenant_id', auth()->user()->tenant_id)->findOrFail($order);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($order->current_status === 'Completed') {
            return back()->withErrors(['status' => 'Order is already completed']);
        }

        if ($data['status'] === 'Completed' && auth()->user()->role !== 'tenant') {
            return back()->withErrors(['status' => 'Only a tenant operator can complete orders']);
        }

        OrderTrack::create([
            'order_id' => $order->id,
            'updated_by' => auth()->id(),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $order->update(['current_status' => $data['status']]);

        if ($data['status'] === 'Completed' && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid']);
        }

        return back()->with('sukses', 'Status updated to '.$data['status']);
    }
}
```

**Path:** `resources/views/services/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Services & pricing - Laundrey')
@section('breadcrumb', 'Services & pricing')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Manage rates</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Price board.</h1>
</div>
<a href="{{ route('services.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Add service</a>
</div>
<div class="mt-6 overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Service</th><th class="px-4 py-2.5">Price</th><th class="px-4 py-2.5">Turnaround</th><th class="px-4 py-2.5">Orders</th><th class="px-5 py-2.5 text-right">Actions</th></tr></thead>
<tbody>
@forelse($services as $s)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-medium">{{ $s->service_name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}<span class="font-normal text-muted">/{{ $s->unit_type }}</span></td>
<td class="px-4 py-3 text-ink-2">{{ $s->estimated_hours }} hrs</td>
<td class="px-4 py-3 font-mono text-[13px] tabular-nums">{{ $s->orders_count }}</td>
<td class="px-5 py-3 text-right">
<a href="{{ route('services.edit', $s) }}" class="mr-3 font-mono text-[11px] uppercase tracking-widest text-ink-2 hover:text-ink">Edit</a>
<form method="POST" action="{{ route('services.destroy', $s) }}" class="inline" onsubmit="return confirm('Delete Service? This action cannot be undone.')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form>
</td>
</tr>
@empty
<tr><td colspan="5" class="px-5 py-8 text-center text-[13px] text-muted">No services yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
<div class="mt-4 text-[13px]">{{ $services->links() }}</div>
@endsection
```

**Path:** `resources/views/services/form.blade.php`

```blade
<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Service name</label><input name="service_name" value="{{ old('service_name', $service->service_name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-3 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Price Rp</label><input name="price_per_unit" type="number" min="0" value="{{ old('price_per_unit', $service->price_per_unit ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Unit</label><select name="unit_type" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"><option value="kg" @selected(old('unit_type', $service->unit_type ?? '') === 'kg')>per kg</option><option value="pcs" @selected(old('unit_type', $service->unit_type ?? '') === 'pcs')>per pcs</option></select></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Hours</label><input name="estimated_hours" type="number" min="1" max="720" value="{{ old('estimated_hours', $service->estimated_hours ?? 24) }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Promo (optional)</label><select name="promo_id" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- none -</option>@foreach($promos as $p)<option value="{{ $p->id }}" @selected((string) old('promo_id', isset($service) ? ($service->promos->first()?->id ?? '') : '') === (string) $p->id)>{{ $p->name }} -{{ $p->percent }}% (min {{ $p->min_qty }}{{ $p->min_unit }})</option>@endforeach</select></div>
</div>
```

**Path:** `resources/views/services/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Add service - Laundrey')
@section('breadcrumb', 'Services / Add')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New rate</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Add service.</h1>
<form method="POST" action="{{ route('services.store') }}" class="mt-8">@csrf
@include('services.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save service</button><a href="{{ route('services.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/services/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Edit service - Laundrey')
@section('breadcrumb', 'Services / Edit')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Change rate</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Edit service.</h1>
<form method="POST" action="{{ route('services.update', $service) }}" class="mt-8">@csrf @method('PUT')
@include('services.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save changes</button><a href="{{ route('services.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/orders/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Orders - Laundrey')
@section('breadcrumb', 'Orders')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $orders->total() }} transactions</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Order book.</h1>
</div>
<a href="{{ route('orders.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record order</a>
</div>
<form method="GET" class="mt-6 flex flex-col gap-2 sm:flex-row">
<input name="cari" placeholder="Find receipt / name…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none">
<select name="status" class="h-10 rounded-md border border-line-strong bg-white px-3 text-sm sm:max-w-44"><option value="">All stages</option>@foreach(['Received','Washing','Drying','Ironing','Ready','Completed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Filter</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($orders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }} · {{ $o->created_at->format('d M Y') }}</span></span>
<span class="hidden md:block"><x-payment-badge :status="$o->payment_status" /></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No matching records.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
```

**Path:** `resources/views/orders/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Record order - Laundrey')
@section('breadcrumb', 'Orders / Record')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New transaction</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Record order.</h1>
<div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
<form method="POST" action="{{ route('orders.store') }}" class="flex flex-col gap-5">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Customer (registered)</label><select id="customerSelect" name="user_id" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- walk-in / new -</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->customerCode() }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>@endforeach</select></div>
<div id="walkinFields" class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Walk-in name</label><input id="walkinName" name="customer_name" value="{{ old('customer_name') }}" placeholder="e.g. Sinta" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Walk-in phone</label><input id="walkinPhone" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="08…" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
<div id="lookupBox" class="hidden border border-line bg-white px-3 py-2"></div>
<p class="text-xs text-muted">Pick a registered customer, or leave walk-in and type a name. Typing checks records live - a match reuses it, otherwise a new record is created.</p>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Service</label><select id="serviceSelect" name="service_id" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- pick -</option>@foreach($services as $s)<option value="{{ $s->id }}" data-price="{{ $s->price_per_unit }}" data-unit="{{ $s->unit_type }}">{{ $s->service_name }} — Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}/{{ $s->unit_type }}</option>@endforeach</select></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Weight / qty</label><input id="weightInput" type="number" step="0.1" min="0.1" name="weight_or_qty" placeholder="3.5" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Promo</label><div class="flex h-11 items-center rounded-md border border-line bg-paper px-3 text-sm text-ink-2"><span id="promoHint">Auto by service + weight</span></div></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Paid</label><select name="payment_status" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
</div>
<div class="flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save order</button><a href="{{ route('orders.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
<aside class="h-fit border border-line bg-white lg:sticky lg:top-20">
<div class="border-b border-dashed border-line-strong px-5 py-4">
<p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Estimate</p>
<p id="totalPreview" class="mt-1 font-mono text-2xl font-bold tabular-nums">Rp0</p>
<p id="calcPreview" class="mt-0.5 font-mono text-xs text-muted">-</p>
</div>
<p class="px-5 py-3 font-mono text-[11px] leading-relaxed text-muted">Receipt issued automatically.<br>Initial Received log recorded.</p>
</aside>
</div>
<script>
const cust = document.getElementById('customerSelect');
const walkin = document.getElementById('walkinFields');
const svc = document.getElementById('serviceSelect');
const w = document.getElementById('weightInput');
const total = document.getElementById('totalPreview');
const calc = document.getElementById('calcPreview');
function fmt(n) { return 'Rp' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function update() {
  const opt = svc.selectedOptions[0];
  const price = opt && opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
  const weight = parseFloat(w.value) || 0;
  const sid = svc.value ? parseInt(svc.value) : 0;
  const unit = opt && opt.dataset.unit ? opt.dataset.unit : '';
  const promo = findPromo(sid, weight, unit);
  const gross = price * weight;
  if (price > 0 && weight > 0) {
    if (promo) {
      total.textContent = fmt(gross * (1 - promo.percent / 100));
      calc.textContent = `${weight} ${unit} x ${fmt(price)} - ${promo.percent}% ${promo.name}`;
      document.getElementById('promoHint').textContent = `${promo.name} -${promo.percent}% applied`;
    } else {
      total.textContent = fmt(gross);
      calc.textContent = `${weight} ${unit} x ${fmt(price)}`;
      document.getElementById('promoHint').textContent = 'No promo for this weight';
    }
  } else {
    total.textContent = 'Rp0';
    calc.textContent = '-';
    document.getElementById('promoHint').textContent = 'Auto by service + weight';
  }
}
const promos = {!! $promoOptions !!};
function findPromo(sid, weight, unit) {
  if (!sid || !(weight > 0)) return null;
  return promos.find((p) => p.active && p.services.includes(sid) && p.min_unit === unit && weight >= p.min_qty) || null;
}
svc.addEventListener('change', update);
w.addEventListener('input', update);
function toggleWalkin() { walkin.style.display = cust.value ? 'none' : ''; }
cust.addEventListener('change', toggleWalkin);
toggleWalkin();
const wn = document.getElementById('walkinName');
const wp = document.getElementById('walkinPhone');
const lb = document.getElementById('lookupBox');
let timer = null;
async function lookup() {
  const q = (wp.value || wn.value || '').trim();
  if (cust.value || q.length < 2) { lb.classList.add('hidden'); lb.innerHTML = ''; return; }
  try {
    const r = await fetch('{{ route('customers.lookup') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
    const j = await r.json();
    if (!j.data.length) {
      lb.classList.remove('hidden');
      lb.innerHTML = '<p class="py-1 font-mono text-xs text-muted">No match - a new customer will be created.</p>';
      return;
    }
    lb.classList.remove('hidden');
    lb.innerHTML = j.data.map(m =>
      `<button type="button" data-id="${m.id}" class="flex w-full items-center justify-between gap-2 py-1.5 text-left"><span class="text-[13px]"><b>${m.name}</b> <span class="font-mono text-[11px] text-muted">${m.code}${m.phone ? ' · ' + m.phone : ''} · ${m.orders} loads</span></span><span class="shrink-0 font-mono text-[11px] font-bold uppercase tracking-widest text-primary">Use</span></button>`
    ).join('');
    lb.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
      cust.value = b.dataset.id;
      toggleWalkin();
      lb.classList.add('hidden');
    }));
  } catch (e) {}
}
wn.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 350); });
wp.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 350); });
</script>
@endsection
```

**Path:** `resources/views/orders/show.blade.php`

```blade
@extends('layouts.app')
@section('title', $order->invoice_number.' - Laundrey')
@section('breadcrumb', 'Orders / Detail')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $order->invoice_number }} · {{ $order->customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $order->customer->name }}</h1>
<p class="mt-1 text-sm text-ink-2">{{ $order->service->service_name }} · {{ $order->weight_or_qty }} {{ $order->service->unit_type }} · in {{ $order->created_at->format('d M Y H:i') }}@if($order->promo) · <span class="font-mono font-bold">{{ $order->promo->name }} -{{ $order->discount_percent }}%</span>@endif</p>
</div>
<div class="flex flex-col items-end gap-1.5"><x-status-badge :status="$order->current_status" /><x-payment-badge :status="$order->payment_status" /></div>
</div>
<div class="mt-4 flex flex-wrap justify-end gap-2">
<a href="{{ route('orders.invoice', $order) }}" class="h-9 rounded-md bg-ink px-4 text-[13px] font-semibold leading-8 text-white hover:bg-black">Print invoice</a>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Total</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Stage</p><p class="mt-0.5 font-display text-xl font-bold">{{ $order->current_status }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Admin</p>
<div class="mt-1.5 flex flex-wrap gap-1.5">
<form method="POST" action="{{ route('orders.update', $order) }}">@csrf @method('PUT')
<input type="hidden" name="payment_status" value="{{ $order->payment_status === 'paid' ? 'unpaid' : 'paid' }}">
<button class="h-8 rounded-md border border-ink px-2.5 text-xs font-medium hover:bg-ink hover:text-white">{{ $order->payment_status === 'paid' ? 'Set unpaid' : 'Mark paid' }}</button></form>
<form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Delete this order?')">@csrf @method('DELETE')<button class="h-8 rounded-md px-2.5 text-xs text-muted hover:text-red-600">Delete</button></form>
</div></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Journey log</h2>
<div class="mt-3 border-t border-ink">
@foreach($order->tracks as $t)
<div class="flex items-baseline gap-4 border-b border-line py-3">
<span class="w-32 shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M, H:i') }}</span>
<span class="flex-1 text-sm"><b class="font-mono text-xs font-bold uppercase tracking-widest">{{ $t->status }}</b>@if($t->notes)<span class="text-ink-2"> - {{ $t->notes }}</span>@endif</span>
<span class="hidden font-mono text-xs text-muted sm:block">{{ $t->updater->name ?? '' }}</span>
</div>
@endforeach
</div>
@endsection
```

**Path:** `resources/views/dashboard/admin.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Dashboard - Laundrey')
@section('breadcrumb', 'Dashboard')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ now()->format('l, d F Y') }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Today at a glance.</h1>
</div>
<a href="{{ route('orders.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record order</a>
</div>

<div class="mt-8 grid grid-cols-2 border-y border-ink py-1 xl:grid-cols-4">
<div class="border-b border-line px-1 py-4 xl:border-b-0"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Taken in</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $totalOrders }}</p><p class="mt-0.5 text-xs text-muted">{{ $totalServices }} active services</p></div>
<div class="border-b border-line px-1 py-4 xl:border-b-0 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">In progress</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $processing }}</p><p class="mt-0.5 text-xs text-muted">Not ready yet</p></div>
<div class="px-1 py-4"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Ready for pickup</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $ready }}</p><p class="mt-0.5 text-xs text-muted">Notify customers</p></div>
<div class="px-1 py-4 xl:border-l xl:border-line xl:pl-6"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Cash in</p><p class="mt-1 font-display text-2xl font-bold tabular-nums tracking-tight md:text-3xl">Rp{{ number_format($revenue, 0, ',', '.') }}</p><p class="mt-0.5 text-xs text-muted">From paid orders</p></div>
</div>

<div class="mt-10 grid grid-cols-1 gap-10 xl:grid-cols-3">
<div class="xl:col-span-2">
<div class="flex items-baseline justify-between">
<h2 class="font-display text-xl font-bold tracking-tight">Recent orders</h2>
<a href="{{ route('orders.index') }}" class="text-[13px] font-medium text-primary hover:underline">All orders →</a>
</div>
<div class="mt-4 border-t border-ink">
@forelse($recentOrders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No orders yet.</p>
@endforelse
</div>
</div>
<div>
<h2 class="font-display text-xl font-bold tracking-tight">Waiting for pickup</h2>
<div class="mt-4 border-t border-ink">
@forelse($readyOrders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-baseline justify-between gap-3 border-b border-line py-3">
<span class="min-w-0"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->customer->name }} <span class="font-mono text-[10px] font-normal text-muted">{{ $o->customer->customerCode() }}</span></span><span class="block font-mono text-xs text-muted">{{ $o->invoice_number }}</span></span>
<span class="shrink-0 font-mono text-[11px] font-bold uppercase tracking-widest text-emerald-700">Pick up →</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">Empty. Everything is picked up.</p>
@endforelse
</div>
</div>
</div>
@endsection
```

**Path:** `resources/views/operations/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Operations - Laundrey')
@section('breadcrumb', 'Operations')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Queue of {{ $orders->total() }} loads</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Work the longest wait first.</h1>
</div>
<form method="GET" class="flex gap-2">
<input name="cari" placeholder="Find receipt…" value="{{ request('cari') }}" class="h-10 w-48 rounded-md border border-line-strong bg-white px-3 font-mono text-xs focus:border-ink focus:outline-none">
<button class="h-10 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
</div>
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
<div class="mt-8 border-t border-ink">
@forelse($orders as $o)
@php($idx = array_search($o->current_status, $steps))
<div class="grid grid-cols-1 gap-4 border-b border-line py-5 lg:grid-cols-[1fr_280px] lg:gap-8">
<div>
<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
<span class="font-mono text-sm font-bold tracking-wide">{{ $o->invoice_number }}</span>
<x-status-badge :status="$o->current_status" />
</div>
<p class="mt-1 text-sm text-ink-2">{{ $o->customer->name }} · {{ $o->service->service_name }} · {{ $o->weight_or_qty }} {{ $o->service->unit_type }} · in {{ $o->created_at->format('d M H:i') }}</p>
<div class="mt-3 flex items-center gap-1">
@foreach($steps as $i => $s)
<span title="{{ $s }}" class="h-1.5 flex-1 rounded-full {{ $i <= $idx ? 'bg-ink' : 'bg-line' }}"></span>
@endforeach
</div>
<p class="mt-1.5 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Step {{ $idx + 1 }}/6 - {{ $o->current_status }}</p>
</div>
<form method="POST" action="{{ route('operations.status', $o) }}" class="flex flex-col gap-2 lg:justify-center">@csrf
<div class="flex gap-2">
<select name="status" required class="h-10 flex-1 rounded-md border border-line-strong bg-white px-2.5 text-[13px] focus:border-ink focus:outline-none">@foreach(['Washing','Drying','Ironing','Ready','Completed'] as $s)<option value="{{ $s }}" @selected($s === $steps[min($idx + 1, 5)])>→ {{ $s }}</option>@endforeach</select>
<button class="h-10 shrink-0 rounded-md bg-ink px-4 text-[13px] font-semibold text-white hover:bg-black">Update</button>
</div>
<input name="notes" placeholder="Note (optional)" class="h-9 rounded-md border border-line bg-white px-2.5 text-[13px] placeholder:text-muted focus:border-ink focus:outline-none">
</form>
</div>
@empty
<p class="border-b border-line py-10 text-center text-[13px] text-muted">Queue empty. All laundry done.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
```

## Langkah 5 — Struk + QR

File mandiri + QR ke landing (`?invoice=` prefill milik yudha — koordinasikan). Method `invoice()` scope tenant. **Commit:** `feat: struk + QR` → push.

**Path:** `resources/views/orders/invoice.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->invoice_number }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1917; padding: 24px; }
  .head { border-bottom: 2px solid #1c1917; padding-bottom: 12px; margin-bottom: 16px; }
  .head h1 { font-size: 22px; }
  .head p { color: #57534e; font-size: 11px; }
  .meta { width: 100%; margin-bottom: 16px; }
  .meta td { vertical-align: top; padding: 2px 0; }
  .mono { font-family: DejaVu Sans Mono, monospace; }
  table.items { width: 100%; border-collapse: collapse; margin: 12px 0; table-layout: fixed; }
  table.items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #57534e; border-bottom: 1px solid #1c1917; padding: 6px 8px; }
  table.items td { border-bottom: 1px solid #e6e3dc; padding: 8px; vertical-align: top; }
  table.items th:nth-child(1), table.items td:nth-child(1) { width: 40%; }
  table.items th:nth-child(2), table.items td:nth-child(2) { width: 20%; }
  table.items th:nth-child(3), table.items td:nth-child(3) { width: 20%; }
  table.items th:nth-child(4), table.items td:nth-child(4) { width: 20%; }
  .right { text-align: right; }
  .total { font-size: 16px; font-weight: bold; }
  .foot { margin-top: 16px; border-top: 1px dashed #999; padding-top: 10px; font-size: 10px; color: #57534e; }
  .actions { margin-bottom: 16px; }
  .actions a { display: inline-block; border: 1px solid #1c1917; padding: 8px 16px; font-size: 12px; text-decoration: none; color: #1c1917; margin-right: 8px; }
  @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="actions">
<a href="#" onclick="window.print(); return false;">Print</a>
</div>
<div class="head">
<h1>{{ $order->tenant->name }}.</h1>
<p>{{ $order->tenant->phone ?? '' }}{{ $order->tenant->address ? ' · '.$order->tenant->address : '' }}</p>
</div>
<table class="meta">
<tr>
<td><strong>INVOICE</strong><br><span class="mono">{{ $order->invoice_number }}</span><br>{{ $order->created_at->format('d M Y H:i') }}</td>
<td class="right">{{ $order->customer->name }}<br><span class="mono">{{ $order->customer->customerCode() }}</span><br>{{ $order->customer->phone ?? '' }}</td>
</tr>
</table>
<table class="items">
<thead><tr><th>Service</th><th>Weight</th><th class="right">Unit price</th><th class="right">Total</th></tr></thead>
<tbody>
<tr>
<td>{{ $order->service->service_name }}</td>
<td class="mono">{{ $order->weight_or_qty }} {{ $order->service->unit_type }}</td>
<td class="right mono">Rp{{ number_format($order->service->price_per_unit, 0, ',', '.') }}</td>
<td class="right mono">Rp{{ number_format($order->total_price, 0, ',', '.') }}@if($order->discount_percent > 0)<br><small>-{{ $order->discount_percent }}% {{ $order->promo->name ?? '' }}</small>@endif</td>
</tr>
</tbody>
</table>
<table class="meta">
<tr><td>Status: <strong>{{ $order->current_status }}</strong></td><td class="right total">Rp{{ number_format($order->total_price, 0, ',', '.') }} <small>({{ $order->payment_status }})</small></td></tr>
</table>
<div class="foot">Track this receipt online with the invoice number. Thank you.</div>
<table style="width:100%; margin-top:16px;">
<tr>
<td style="vertical-align:middle;">
{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(110)->generate(route('home', ['invoice' => $order->invoice_number])) !!}
</td>
<td style="vertical-align:middle; padding-left:12px; font-size:11px; color:#57534e;">
<strong>Scan to track</strong><br>
Point your camera here to open the tracking page with this receipt number filled in.
</td>
</tr>
</table>
</body>
</html>
```

## Checklist serah terima

- [ ] CRUD service/order, tracking, isolasi tenant, promo otomatis, `pint` bersih
