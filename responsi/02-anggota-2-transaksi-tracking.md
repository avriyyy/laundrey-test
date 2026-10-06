# 02 — Anggota 2: Transaksi Order & Tracking

**Peran:** jantung bisnis. **CRUD Orders + OrderTracks**, invoice otomatis, walk-in, search/filter, pagination, tracking publik.

**Prasyarat:** branch `pika` merge ke `main`. Buat branch: `git checkout main && git pull && git checkout -b bahtiar`. Merge ke `main` via PR setelah checklist serah terima di bawah hijau.

---

## Langkah 1 — Migration + model

```bash
php artisan make:migration create_orders_table --no-interaction
php artisan make:migration create_order_tracks_table --no-interaction
php artisan make:model Order --no-interaction
php artisan make:model OrderTrack --no-interaction
```

**Path:** `*_create_orders_table.php` — `up`:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number', 30)->unique();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
    $table->decimal('weight_or_qty', 8, 2);
    $table->decimal('total_price', 12, 2);
    $table->string('payment_status', 20)->default('unpaid');
    $table->string('current_status', 20)->default('Received');
    $table->timestamps();
});
```

**Path:** `*_create_order_tracks_table.php` — `up`:

```php
Schema::create('order_tracks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 20);
    $table->string('notes', 255)->nullable();
    $table->timestamps();
});
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

    protected $fillable = ['invoice_number', 'tenant_id', 'user_id', 'service_id', 'weight_or_qty', 'total_price', 'payment_status', 'current_status'];

    protected function casts(): array
    {
        return ['weight_or_qty' => 'decimal:2', 'total_price' => 'decimal:2'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function tracks(): HasMany { return $this->hasMany(OrderTrack::class)->orderBy('created_at'); }

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

**Fungsi generator:** urut harian per tenant (`PREFIX-YYYYMMDD-001`), loop anti-tabrakan global.

**Path:** `app/Models/OrderTrack.php`

```php
protected $fillable = ['order_id', 'updated_by', 'status', 'notes'];

public function order(): BelongsTo { return $this->belongsTo(Order::class); }
public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
```

**Cek:** `php artisan migrate`
**Commit:** `feat: order, track, generator invoice`

---

## Langkah 2 — Factory + seeder

**Path:** `database/factories/OrderFactory.php`

```php
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
```

**Path:** `database/factories/OrderTrackFactory.php` — `order_id` (factory), `updated_by` (factory user), status acak, notes kalimat.

**Seeder:** tambah ke `LaundreySeeder` — 8 order campur status + paid/unpaid, tiap order 1 track awal `Received` oleh admin.

**Cek:** `php artisan migrate:fresh --seed`
**Commit:** `feat: factory + seed order` → push.

---

## Langkah 3 — Form Request + Resource

```bash
php artisan make:request StoreOrderRequest --no-interaction
php artisan make:request UpdateOrderRequest --no-interaction
php artisan make:request StoreTrackRequest --no-interaction
php artisan make:resource OrderResource --no-interaction
php artisan make:resource OrderTrackResource --no-interaction
```

**Path:** `app/Http/Requests/StoreOrderRequest.php`

```php
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
        'service_id.exists' => 'Service not found',
        'user_id.exists' => 'Customer not found',
        'customer_name.required_without' => 'Pick a customer or type a walk-in name',
    ];
}
```

**Path:** `UpdateOrderRequest.php` — `weight_or_qty`/`payment_status`/`service_id` semua `sometimes`.

**Path:** `StoreTrackRequest.php`

```php
use App\Models\Order;

return [
    'status' => ['required', 'string', 'in:'.implode(',', Order::STATUSES)],
    'notes' => ['nullable', 'string', 'max:255'],
];
// messages: 'status.in' => 'Status harus salah satu: '.implode(', ', Order::STATUSES),
```

**Path:** `app/Http/Resources/OrderTrackResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status,
        'notes' => $this->notes,
        'updated_by' => $this->whenLoaded('updater', fn () => [
            'id' => $this->updater->id, 'name' => $this->updater->name, 'role' => $this->updater->role,
        ]),
        'dibuat_pada' => $this->created_at->toIso8601String(),
    ];
}
```

**Path:** `app/Http/Resources/OrderResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'invoice_number' => $this->invoice_number,
        'weight_or_qty' => (float) $this->weight_or_qty,
        'total_price' => (float) $this->total_price,
        'payment_status' => $this->payment_status,
        'current_status' => $this->current_status,
        'customer' => $this->whenLoaded('customer', fn () => [
            'id' => $this->customer->id, 'name' => $this->customer->name,
            'email' => $this->customer->email, 'phone' => $this->customer->phone,
        ]),
        'service' => $this->whenLoaded('service', fn () => [
            'id' => $this->service->id, 'service_name' => $this->service->service_name,
            'price_per_unit' => (float) $this->service->price_per_unit, 'unit_type' => $this->service->unit_type,
        ]),
        'tracks' => OrderTrackResource::collection($this->whenLoaded('tracks')),
        'dibuat_pada' => $this->created_at->toIso8601String(),
    ];
}
```

**Commit:** `feat: request + resource order track`

---

## Langkah 4 — Controller + route (inti)

```bash
php artisan make:controller Api/V1/OrderController --api --no-interaction
php artisan make:controller Api/V1/TrackController --no-interaction
```

**Path:** `app/Http/Controllers/Api/V1/OrderController.php` (lengkap, copy-paste)

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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$kataKunci.'%'));
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
        $service = Service::where('tenant_id', $tenantId)->findOrFail($data['service_id']);
        $customer = $this->resolveCustomer($tenantId, $data);

        $order = DB::transaction(function () use ($data, $request, $service, $customer, $tenantId) {
            $order = Order::create([
                'invoice_number' => Order::generateInvoiceNumber($request->user()->tenant->prefix, $tenantId),
                'tenant_id' => $tenantId,
                'user_id' => $customer->id,
                'service_id' => $data['service_id'],
                'weight_or_qty' => $data['weight_or_qty'],
                'total_price' => (float) $data['weight_or_qty'] * (float) $service->price_per_unit,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'current_status' => 'Received',
            ]);

            OrderTrack::create([
                'order_id' => $order->id, 'updated_by' => $request->user()->id,
                'status' => 'Received', 'notes' => 'Order received at counter',
            ]);

            return $order;
        });

        $order->load(['customer', 'service', 'tracks']);

        return response()->json(['sukses' => true, 'pesan' => 'Order berhasil dibuat', 'data' => new OrderResource($order)], 201);
    }

    private function resolveCustomer(int $tenantId, array $data): User
    {
        if (! empty($data['user_id'])) {
            return User::where('tenant_id', $tenantId)->where('role', 'pelanggan')->findOrFail($data['user_id']);
        }

        if (! empty($data['customer_phone'])) {
            $existing = User::where('tenant_id', $tenantId)->where('role', 'pelanggan')
                ->where('phone', $data['customer_phone'])->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return User::create([
            'tenant_id' => $tenantId,
            'name' => $data['customer_name'],
            'email' => 'walkin-'.now()->format('YmdHis').'-'.str()->random(6).'@laundrey.local',
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
            'phone' => $data['customer_phone'] ?? null,
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $item = Order::where('tenant_id', $request->user()->tenant_id)->findOrFail($order);
        $item->load(['customer', 'service', 'tracks.updater']);

        return response()->json(['sukses' => true, 'data' => new OrderResource($item)]);
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
        $item->load(['customer', 'service', 'tracks']);

        return response()->json(['sukses' => true, 'pesan' => 'Order berhasil diperbarui', 'data' => new OrderResource($item)]);
    }
}
```

**Fungsi kunci:** semua query scope tenant (ID asing → 404); harga selalu hitung server; walk-in pakai ulang by HP; transaction agar order + track awal atomik.

**Path:** `app/Http/Controllers/Api/V1/TrackController.php` (lengkap)

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
            ->with(['customer', 'service', 'tracks.updater'])->first();

        if ($order === null) {
            return response()->json(['sukses' => false, 'pesan' => 'Resi tidak ditemukan'], 404);
        }

        return response()->json(['sukses' => true, 'data' => new OrderResource($order)]);
    }

    public function store(StoreTrackRequest $request, Order $order): JsonResponse
    {
        if ($order->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['sukses' => false, 'pesan' => 'Sumber daya tidak ditemukan'], 404);
        }

        $data = $request->validated();

        if ($order->current_status === 'Completed') {
            return response()->json(['sukses' => false, 'pesan' => 'Order sudah selesai, status tidak bisa diubah'], 422);
        }

        if ($data['status'] === 'Completed' && $request->user()->role !== 'admin') {
            return response()->json(['sukses' => false, 'pesan' => 'Hanya admin yang bisa menyelesaikan order'], 403);
        }

        OrderTrack::create([
            'order_id' => $order->id, 'updated_by' => $request->user()->id,
            'status' => $data['status'], 'notes' => $data['notes'] ?? null,
        ]);

        $order->update(['current_status' => $data['status']]);

        if ($data['status'] === 'Completed' && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid']);
        }

        $order->load(['customer', 'service', 'tracks.updater']);

        return response()->json(['sukses' => true, 'pesan' => 'Status berhasil diperbarui ke '.$data['status'], 'data' => new OrderResource($order)], 201);
    }
}
```

**Route** (tambah ke `routes/api.php`): publik `GET /track/{invoice_number}`; grup auth: `GET/POST /orders`, `GET /orders/{order}`, `PUT /orders/{order}` (grup `role:admin`: POST/PUT + `POST /orders/{order}/tracks`).

**Cek manual:** order user terdaftar + walk-in + HP ganda (user sama) → 201; filter `?status=&payment_status=` + pagination; tracking publik 200/404; ID tenant lain 404.
**Commit:** `feat: API order CRUD + tracking` → push, kabari Anggota 3.

## Langkah 5 — Promo diskon (relasi Many-to-Many kedua)

**Kenapa di bagianmu:** promo menempel ke kalkulasi harga order + tabel pivot `promo_service` adalah relasi Many-to-Many yang disyaratkan ketentuan (satu promo ↔ banyak layanan).

```bash
php artisan make:migration create_promos_table --no-interaction
php artisan make:migration add_promo_to_orders_table --no-interaction
php artisan make:model Promo --no-interaction
```

**Migration 1** — tabel `promos` (`tenant_id` FK cascade, `code` 20, `name`, `percent` tinyint, `active` default true, `starts_at`/`ends_at` date nullable, unique `[tenant_id, code]`) + pivot `promo_service` (`promo_id`/`service_id` cascade, unique pair).

**Migration 2** — `orders`: `promo_id` nullable FK nullOnDelete + `discount_percent` decimal default 0.

**Model `Promo`:** fillable + casts; `services(): BelongsToMany(Service::class, 'promo_service')`; `orders(): HasMany`; `tenant(): BelongsTo`; method `isValidFor($serviceId)`: aktif + dalam window tanggal + ter-attach ke layanan itu. **Model `Service`:** tambah `promos(): BelongsToMany`. **Model `Order`:** fillable + `promo()` BelongsTo.

**API `PromoController` (CRUD milik bagian ini):** index scope + cari + paginasi; store (kode unik per tenant, sync `service_ids` scope tenant) 201; show; destroy.

**Order pakai promo:** `StoreOrderRequest` + `promo_code` nullable; `resolvePromo()` (cari by code uppercase se-tenant → `isValidFor`, kode salah/expired = harga normal); total = `berat × tarif × (1 - persen/100)`; simpan `promo_id` + snapshot persen. `OrderResource`: tambah `discount_percent` + nested `promo`.

**Route API** (grup `role:admin`): `GET/POST /promos`, `GET /promos/{id}`, `DELETE /promos/{id}`.

Halaman `promos/index` (form + tabel) dan field kode promo di form order = kerja Anggota 3, tapi logikanya milikmu — koordinasikan nama field (`promo_code`, `service_ids`).

### Kode lengkap promo (salin per path)

**`database/migrations/*_create_promos_table.php`:**

```php
public function up(): void
{
    Schema::create('promos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->string('code', 20);
        $table->string('name', 100);
        $table->unsignedTinyInteger('percent');
        $table->boolean('active')->default(true);
        $table->date('starts_at')->nullable();
        $table->date('ends_at')->nullable();
        $table->timestamps();

        $table->unique(['tenant_id', 'code']);
    });

    Schema::create('promo_service', function (Blueprint $table) {
        $table->id();
        $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
        $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['promo_id', 'service_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('promo_service');
    Schema::dropIfExists('promos');
}
```

**`database/migrations/*_add_promo_to_orders_table.php`:**

```php
public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->foreignId('promo_id')->nullable()->after('service_id')->constrained('promos')->nullOnDelete();
        $table->decimal('discount_percent', 5, 2)->default(0)->after('total_price');
    });
}
```

**`app/Models/Promo.php` (lengkap):**

```php
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

    protected $fillable = ['tenant_id', 'code', 'name', 'percent', 'active', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['percent' => 'integer', 'active' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'promo_service')->withTimestamps();
    }

    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

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
```

**Tambahan `app/Models/Service.php`:** import `BelongsToMany`, tambah:

```php
public function promos(): BelongsToMany
{
    return $this->belongsToMany(Promo::class, 'promo_service')->withTimestamps();
}
```

**Tambahan `app/Models/Order.php`:** fillable + `'promo_id'`, `'discount_percent'`; casts + `'discount_percent' => 'decimal:2'`; relasi:

```php
public function promo(): BelongsTo { return $this->belongsTo(Promo::class); }
```

**`app/Http/Controllers/Api/V1/PromoController.php` (lengkap):**

```php
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
            $kueri->where(fn ($sub) => $sub->where('code', 'like', '%'.$request->query('cari').'%')
                ->orWhere('name', 'like', '%'.$request->query('cari').'%'));
        }

        return PromoResource::collection($kueri->orderBy('code')->paginate(min($request->integer('per_halaman', 10), 100)));
    }

    public function store(StorePromoRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        if (Promo::where('tenant_id', $tenantId)->where('code', $data['code'])->exists()) {
            return response()->json(['sukses' => false, 'pesan' => 'Promo code already used'], 422);
        }

        $serviceIds = Service::where('tenant_id', $tenantId)->whereIn('id', $data['service_ids'] ?? [])->pluck('id');
        $promo = Promo::create($data + ['tenant_id' => $tenantId]);
        $promo->services()->sync($serviceIds);
        $promo->load('services');

        return response()->json(['sukses' => true, 'pesan' => 'Promo berhasil dibuat', 'data' => new PromoResource($promo)], 201);
    }

    public function show(Request $request, int $promo): JsonResponse
    {
        $item = Promo::where('tenant_id', $request->user()->tenant_id)->with('services')->findOrFail($promo);

        return response()->json(['sukses' => true, 'data' => new PromoResource($item)]);
    }

    public function destroy(Request $request, int $promo): JsonResponse
    {
        Promo::where('tenant_id', $request->user()->tenant_id)->findOrFail($promo)->delete();

        return response()->json(['sukses' => true, 'pesan' => 'Promo berhasil dihapus']);
    }
}
```

**`StorePromoRequest`:** `prepareForValidation` uppercase code; rules: code required max 20, name, percent int 1–100, active sometimes boolean, dates nullable + ends after starts, `service_ids` array + exists.

**`PromoResource`:** id, code, name, percent int, active bool, dates, `services` (collection, whenLoaded).

**Patch `OrderController@store`:** tambah `use App\Models\Promo;`, resolve service+customer seperti biasa lalu:

```php
$promo = null;

if (! empty($data['promo_code'])) {
    $candidate = Promo::where('tenant_id', $tenantId)->where('code', strtoupper($data['promo_code']))->first();

    if ($candidate && $candidate->isValidFor((int) $service->id)) {
        $promo = $candidate;
    }
}

$gross = (float) $data['weight_or_qty'] * (float) $service->price_per_unit;
$discount = $promo ? (float) $promo->percent : 0;

$order = Order::create([
    // ... field lama
    'promo_id' => $promo?->id,
    'total_price' => $gross * (1 - $discount / 100),
    'discount_percent' => $discount,
    // ...
]);
```

Dan `StoreOrderRequest` tambah `'promo_code' => ['nullable', 'string', 'max:20']`. `OrderResource` tambah `discount_percent` + nested `promo` (whenLoaded).

### View promo → pindah ke dokumen 03 (milik Anggota 3).



## Checklist serah terima

- [ ] Walk-in baru vs HP ganda benar, harga = berat × tarif
- [ ] Isolasi tenant, `pint` bersih, `migrate:fresh --seed` hijau
