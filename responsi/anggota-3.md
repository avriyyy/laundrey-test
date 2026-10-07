# anggota-3 — yudha: Customers, Promos, Landing, Docs & Deploy

**CRUD milikmu (backend + halaman): Customers, Promos** (+ landing, README, deploy; porsi lebih, tidak apa).

**Branch:** `yudha` (buat setelah `pika`+`bahtiar` merge: `git checkout main && git pull && git checkout -b yudha`; merge via PR setelah checklist akhir hijau + LIVE).

## Langkah 1 — Migration + model promo & email nullable

`isValidFor($serviceId, $qty, $unit)`: aktif + unit cocok + qty cukup + tanggal + attach. `php artisan migrate`. **Commit:** `feat: promo + email nullable`

**Path:** `database/migrations/2026_10_06_200551_create_promos_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
};
```

**Path:** `database/migrations/2026_10_06_200552_add_promo_to_orders_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->after('service_id')->constrained('promos')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_id');
            $table->dropColumn('discount_percent');
        });
    }
};
```

**Path:** `database/migrations/2026_10_07_031713_make_email_nullable_on_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE users_new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR NOT NULL, email VARCHAR NULL UNIQUE, email_verified_at DATETIME NULL, password VARCHAR NOT NULL, remember_token VARCHAR(100) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, role VARCHAR NOT NULL DEFAULT \'pelanggan\', phone VARCHAR NULL, tenant_id INTEGER NULL)');
            DB::statement('INSERT INTO users_new (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id) SELECT id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id FROM users');
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE users_new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR NOT NULL, email VARCHAR NOT NULL UNIQUE, email_verified_at DATETIME NULL, password VARCHAR NOT NULL, remember_token VARCHAR(100) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, role VARCHAR NOT NULL DEFAULT \'pelanggan\', phone VARCHAR NULL, tenant_id INTEGER NULL)');
            DB::statement("INSERT INTO users_new (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id) SELECT id, name, COALESCE(email, 'restored-' || id || '@laundrey.local'), email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id FROM users");
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(100) NOT NULL');
        }
    }
};
```

**Path:** `database/migrations/2026_10_07_031714_rework_promos_to_rules.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->decimal('min_qty', 8, 2)->default(0)->after('percent');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('tenant_id');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('min_qty');
        });
    }
};
```

**Path:** `database/migrations/2026_10_07_033525_add_unit_to_promos.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('min_unit', 20)->default('kg')->after('min_qty');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('min_unit');
        });
    }
};
```

**Path:** `app/Models/Promo.php`

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

    protected $fillable = [
        'tenant_id',
        'name',
        'percent',
        'min_qty',
        'min_unit',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'min_qty' => 'decimal:2',
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

    public function isValidFor(int $serviceId, float $qty, ?string $unit = null): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($unit !== null && $this->min_unit !== $unit) {
            return false;
        }

        if ((float) $this->min_qty > 0 && $qty < (float) $this->min_qty) {
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

## Langkah 2 — CRUD Customers

Lookup JSON, email opsional→null, hapus dikunci bila berorder. Route resource + lookup. **Commit:** `feat: CRUD customer` → push.

**Path:** `app/Http/Controllers/Web/CustomerWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CustomerWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = User::query()->where('tenant_id', auth()->user()->tenant_id)->where('role', 'pelanggan')->withCount('orders')->withSum('orders as spent_sum', 'total_price');

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('phone', 'like', '%'.$kataKunci.'%')
                    ->orWhere('id', $kataKunci);
            });
        }

        $customers = $kueri->orderBy('name')->paginate(12)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $kataKunci = trim($request->query('q', ''));

        if (strlen($kataKunci) < 2) {
            return response()->json(['data' => []]);
        }

        $rows = User::where('tenant_id', auth()->user()->tenant_id)->where('role', 'pelanggan')
            ->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('phone', 'like', '%'.$kataKunci.'%');
            })
            ->withCount('orders')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'code' => $u->customerCode(),
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'orders' => $u->orders_count,
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
        ]);

        User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => Hash::make(str()->random(32)),
            'role' => 'pelanggan',
        ]);

        return redirect()->route('customers.index')->with('sukses', 'Customer recorded.');
    }

    public function show(int $customer): View
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        $orders = Order::with('service')->where('user_id', $customer->id)->orderBy('created_at', 'desc')->paginate(10);
        $spent = (float) Order::where('user_id', $customer->id)->where('payment_status', 'paid')->sum('total_price');
        $unpaid = (float) Order::where('user_id', $customer->id)->where('payment_status', 'unpaid')->sum('total_price');

        return view('customers.show', compact('customer', 'orders', 'spent', 'unpaid'));
    }

    public function edit(int $customer): View
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, int $customer): RedirectResponse
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,'.$customer->id],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email,'.$customer->id],
        ]);

        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('sukses', 'Customer updated.');
    }

    public function destroy(int $customer): RedirectResponse
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)->findOrFail($customer);
        abort_unless($customer->role === 'pelanggan', 404);

        if ($customer->orders()->exists()) {
            return back()->withErrors(['customer' => 'Customer has order history and cannot be deleted']);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('sukses', 'Customer deleted.');
    }
}
```

**Path:** `resources/views/customers/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Customers - Laundrey')
@section('breadcrumb', 'Customers')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Customer detail</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Customers.</h1>
</div>
<a href="{{ route('customers.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Record customer</a>
</div>
<form method="GET" class="mt-6 flex gap-2">
<input name="cari" placeholder="Find name, phone, or ID…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none">
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($customers as $c)
<a href="{{ route('customers.show', $c) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-24 shrink-0 font-mono text-xs font-bold">{{ $c->customerCode() }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $c->name }}</span><span class="block truncate font-mono text-xs text-muted">{{ $c->phone ?? 'no phone' }} · {{ $c->orders_count }} loads</span></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($c->spent_sum ?? 0, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No customers found. <a href="{{ route('customers.create') }}" class="font-medium text-primary">Record the first one →</a></p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $customers->links() }}</div>
@endsection
```

**Path:** `resources/views/customers/form.blade.php`

```blade
<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Name</label><input name="name" value="{{ old('name', $customer->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone (WhatsApp)</label><input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" placeholder="08…" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email (optional)</label><input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
```

**Path:** `resources/views/customers/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Record customer - Laundrey')
@section('breadcrumb', 'Customers / Record')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New file</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Record customer.</h1>
<form method="POST" action="{{ route('customers.store') }}" class="mt-8">@csrf
@include('customers.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save customer</button><a href="{{ route('customers.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/customers/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Edit '.$customer->name.' - Laundrey')
@section('breadcrumb', 'Customers / Edit')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $customer->customerCode() }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Edit customer.</h1>
<form method="POST" action="{{ route('customers.update', $customer) }}" class="mt-8">@csrf @method('PUT')
@include('customers.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save changes</button><a href="{{ route('customers.show', $customer) }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/customers/show.blade.php`

```blade
@extends('layouts.app')
@section('title', $customer->name.' - Laundrey')
@section('breadcrumb', 'Customers / File')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $customer->name }}</h1>
<p class="mt-1 font-mono text-[13px] text-ink-2">{{ $customer->phone ?? 'no phone' }} · {{ $customer->email ?? 'no email' }}</p>
</div>
<div class="flex gap-2">
<a href="{{ route('customers.edit', $customer) }}" class="h-9 rounded-md border border-ink px-3 text-[13px] font-medium leading-8 hover:bg-ink hover:text-white">Edit</a>
<form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete</button></form>
</div>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Loads</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $orders->total() }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Paid total</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($spent, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Unpaid</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($unpaid, 0, ',', '.') }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Order history</h2>
<div class="mt-3 border-t border-ink">
@forelse($orders as $o)
<a href="{{ route('orders.show', $o) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $o->service->service_name }} · {{ $o->weight_or_qty }} {{ $o->service->unit_type }}</span><span class="block text-xs text-muted">{{ $o->created_at->format('d M Y') }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="hidden md:block"><x-payment-badge :status="$o->payment_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No loads yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
```

## Langkah 3 — CRUD Promos + aturan main

Tanpa kode: nama+persen+min qty/unit+tanggal; ditempel dari form service; order otomatis. **Commit:** `feat: CRUD promo` → push.

**Path:** `app/Http/Requests/StorePromoRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'percent' => ['required', 'integer', 'min:1', 'max:100'],
            'min_qty' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_unit' => ['required', 'string', 'in:kg,pcs'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
```

**Path:** `app/Http/Resources/PromoResource.php`

```php
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
```

**Path:** `app/Http/Controllers/Api/V1/PromoController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $kueri = Promo::query()->with('services')->where('tenant_id', $request->user()->tenant_id);

        if ($request->filled('cari')) {
            $kueri->where('name', 'like', '%'.$request->query('cari').'%');
        }

        $kueri->orderBy('name');

        $perHalaman = min($request->integer('per_halaman', 10), 100);

        return PromoResource::collection($kueri->paginate($perHalaman));
    }

    public function store(StorePromoRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $promo = Promo::create($data + ['tenant_id' => $tenantId]);
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
```

**Path:** `app/Http/Controllers/Web/PromoWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoWebController extends Controller
{
    public function index(): View
    {
        $promos = Promo::where('tenant_id', auth()->user()->tenant_id)
            ->with('services')->orderBy('name')->paginate(10);

        return view('promos.index', compact('promos'));
    }

    public function create(): View
    {
        return view('promos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Promo::create($data + ['tenant_id' => auth()->user()->tenant_id]);

        return redirect()->route('promos.index')->with('sukses', 'Promo added.');
    }

    public function edit(int $promo): View
    {
        $promo = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);

        return view('promos.edit', compact('promo'));
    }

    public function update(Request $request, int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $data = $this->validated($request);

        $item->update($data);

        return redirect()->route('promos.index')->with('sukses', 'Promo updated.');
    }

    public function destroy(int $promo): RedirectResponse
    {
        $item = Promo::where('tenant_id', auth()->user()->tenant_id)->findOrFail($promo);
        $item->delete();

        return back()->with('sukses', 'Promo deleted.');
    }

    public function show(int $promo): RedirectResponse
    {
        return redirect()->route('promos.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'percent' => ['required', 'integer', 'min:1', 'max:100'],
            'min_qty' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_unit' => ['required', 'string', 'in:kg,pcs'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
```

**Path:** `resources/views/promos/form.blade.php`

```blade
<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Promo name</label><input name="name" value="{{ old('name', $promo->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-3 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Discount %</label><input name="percent" type="number" min="1" max="100" value="{{ old('percent', $promo->percent ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Min qty</label><input name="min_qty" type="number" step="0.1" min="0" value="{{ old('min_qty', $promo->min_qty ?? 0) }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Min unit</label><select name="min_unit" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"><option value="kg" @selected(old('min_unit', $promo->min_unit ?? 'kg') === 'kg')>kg</option><option value="pcs" @selected(old('min_unit', $promo->min_unit ?? 'kg') === 'pcs')>pcs</option></select></div>
</div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Starts</label><input name="starts_at" type="date" value="{{ old('starts_at', isset($promo) && $promo->starts_at ? $promo->starts_at->format('Y-m-d') : '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Ends</label><input name="ends_at" type="date" value="{{ old('ends_at', isset($promo) && $promo->ends_at ? $promo->ends_at->format('Y-m-d') : '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm"></div>
</div>
<p class="text-xs text-muted">Attach this promo to services from the service form. It applies automatically when the weight meets the minimum.</p>
</div>
```

**Path:** `resources/views/promos/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Add promo - Laundrey')
@section('breadcrumb', 'Promos / Add')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New rule</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Add promo.</h1>
<form method="POST" action="{{ route('promos.store') }}" class="mt-8">@csrf
@include('promos.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save promo</button><a href="{{ route('promos.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/promos/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Edit promo - Laundrey')
@section('breadcrumb', 'Promos / Edit')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Change rule</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Edit promo.</h1>
<form method="POST" action="{{ route('promos.update', $promo) }}" class="mt-8">@csrf @method('PUT')
@include('promos.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save changes</button><a href="{{ route('promos.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/promos/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Promos - Laundrey')
@section('breadcrumb', 'Promos')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Discounts</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Promos.</h1>
</div>
<a href="{{ route('promos.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Add promo</a>
</div>
<div class="mt-6 overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Name</th><th class="px-4 py-2.5">Off</th><th class="px-4 py-2.5">Min qty</th><th class="px-4 py-2.5">Services</th><th class="px-4 py-2.5">Active</th><th class="px-5 py-2.5 text-right">Actions</th></tr></thead>
<tbody>
@forelse($promos as $p)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-medium">{{ $p->name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">{{ $p->percent }}%</td>
<td class="px-4 py-3 font-mono text-[13px] tabular-nums">{{ $p->min_qty }}{{ $p->min_unit }}+</td>
<td class="px-4 py-3 text-[13px] text-ink-2">{{ $p->services->pluck('service_name')->join(', ') ?: '—' }}</td>
<td class="px-4 py-3 font-mono text-[11px] font-bold uppercase tracking-widest {{ $p->active ? 'text-emerald-700' : 'text-muted' }}">{{ $p->active ? 'Yes' : 'No' }}</td>
<td class="px-5 py-3 text-right">
<a href="{{ route('promos.edit', $p) }}" class="mr-3 font-mono text-[11px] uppercase tracking-widest text-ink-2 hover:text-ink">Edit</a>
<form method="POST" action="{{ route('promos.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete this promo?')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-5 py-8 text-center text-[13px] text-muted">No promos yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
<div class="mt-4 text-[13px]">{{ $promos->links() }}</div>
@endsection
```

## Langkah 4 — Landing, auth view, README

Hero 2 kolom, struk + box tenant, FAQ, CTA email. README: akun uji, tabel API, cara per halaman, link deploy. **Commit:** `feat: landing` → `docs: readme` → push.

**Path:** `app/Http/Controllers/Web/TrackWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackWebController extends Controller
{
    public function index(): View
    {
        return view('tracking.index', array_merge(['order' => null, 'prefill' => request()->query('invoice', '')], $this->extras()));
    }

    public function track(Request $request): View
    {
        $data = $request->validate(['invoice_number' => ['required', 'string', 'max:30']]);

        $order = Order::where('invoice_number', $data['invoice_number'])
            ->with(['customer', 'service', 'tenant', 'tracks.updater'])
            ->first();

        if ($order === null) {
            return view('tracking.index', array_merge(['order' => null], $this->extras()))
                ->withErrors(['invoice_number' => 'Receipt not found']);
        }

        return view('tracking.index', array_merge(compact('order'), $this->extras()));
    }

    /** @return array<string, mixed> */
    private function extras(): array
    {
        return [];
    }
}
```

**Path:** `resources/views/tracking/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Laundrey - laundry tracking made simple')
@section('breadcrumb', 'Laundrey')
@section('content')
{{-- Hero --}}
<div class="grid grid-cols-1 gap-10 md:grid-cols-2">
<div class="flex flex-col">
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Laundrey · Laundry tracking</p>
<h1 class="mt-3 font-display text-5xl font-bold leading-[1.02] tracking-tight md:text-6xl">Know exactly where your laundry is.</h1>
<p class="mt-5 max-w-md text-base leading-relaxed text-ink-2">Drop off your clothes, keep the receipt, and follow every stage online - from intake to ready for pickup. No calls, no guessing.</p>
@if(isset($order) && $order && $order->tenant)
<div class="relative mt-6 flex max-w-md flex-1 flex-col justify-center border border-line bg-white px-6 py-8">
<p class="absolute left-6 top-4 font-mono text-[11px] uppercase tracking-[0.22em] text-muted">This receipt belongs to</p>
<div class="text-center">
<p class="font-display text-3xl font-bold tracking-tight md:text-4xl">{{ $order->tenant->name }}<span class="text-primary">.</span></p>
<p class="mt-2 font-mono text-xs text-muted">{{ $order->tenant->prefix }} receipts · {{ $order->invoice_number }}</p>
</div>
</div>
@endif
</div>
<div>
<div class="border border-line bg-white">
<div class="border-b border-line px-5 py-3 font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Track a receipt</div>
<div class="px-5 py-5">
<form method="POST" action="{{ route('track.search') }}" class="flex flex-col gap-2 sm:flex-row">@csrf
<input name="invoice_number" value="{{ old('invoice_number', $order->invoice_number ?? ($prefill ?? '')) }}" placeholder="INV-20261006-001" required spellcheck="false"
class="h-12 flex-1 rounded-md border border-line-strong bg-white px-4 font-mono text-sm tracking-wide placeholder:text-muted focus:border-ink focus:outline-none">
<button class="h-12 shrink-0 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Track →</button>
</form>
@if(isset($order) && $order)
@php($steps = ['Received','Washing','Drying','Ironing','Ready','Completed'])
@php($idx = array_search($order->current_status, $steps))
<div class="mt-5 border border-line">
<div class="border-b border-dashed border-line-strong px-5 py-4">
<div class="flex items-baseline justify-between gap-3">
<p class="font-mono text-sm font-bold tracking-wide">{{ $order->invoice_number }}</p>
<x-status-badge :status="$order->current_status" />
</div>
<p class="mt-1 text-[13px] text-ink-2">{{ $order->customer->name }} · {{ $order->service->service_name }} · {{ $order->weight_or_qty }} {{ $order->service->unit_type }}</p>
<div class="mt-2 flex items-baseline justify-between">
<p class="font-mono text-lg font-bold tabular-nums">Rp{{ number_format($order->total_price, 0, ',', '.') }}</p>
<x-payment-badge :status="$order->payment_status" />
</div>
</div>
<div class="px-5 py-4">
@foreach($steps as $i => $s)
@php($t = $order->tracks->firstWhere('status', $s))
<div class="flex items-baseline gap-4 border-b border-line py-2.5 last:border-0">
<span class="w-6 shrink-0 font-mono text-xs text-muted">{{ sprintf('%02d', $i + 1) }}</span>
<span class="flex-1 text-sm {{ $i <= $idx ? 'font-semibold' : 'text-muted' }}">{{ $s }}</span>
<span class="font-mono text-xs text-muted">{{ $t ? $t->created_at->format('d M, H:i') : '--' }}</span>
</div>
@endforeach
</div>
<div class="border-t border-dashed border-line-strong px-5 py-3 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">* digital receipt - keep your receipt number</div>
</div>
@endif
</div>
</div>
</div>
</div>

<div class="mt-16 border-t border-ink"></div>

{{-- How it works --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 01 - How it works</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Three steps,<br>done.</h2>
</div>
<div class="border-t border-ink">
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">01</span><div><p class="font-semibold">Drop off and take the receipt</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">The admin records the laundry, weighs it, and issues a receipt number.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">02</span><div><p class="font-semibold">Watch it on this page</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Every stage change is logged by the admin and shows up here.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">03</span><div><p class="font-semibold">Pick up when Ready</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Come with your receipt, settle the bill if unpaid, take it home.</p></div></div>
</div>
</div>

{{-- Start here --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div class="border-t border-ink md:order-1 order-2">
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">01</span><div><p class="font-semibold">Register the shop</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Pick a 3-letter receipt code at <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">/register</a>.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">02</span><div><p class="font-semibold">Record the basics</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Add services, customer files, then daily orders.</p></div></div>
<div class="flex gap-4 border-b border-line py-4"><span class="font-mono text-sm font-bold text-primary">03</span><div><p class="font-semibold">Move stages</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Advance Received to Completed from Operations.</p></div></div>
</div>
<div class="md:order-2">
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 02 - Start here</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Open<br>a shop.</h2>
</div>
</div>

{{-- FAQ --}}
<div class="mt-16 grid grid-cols-1 gap-10 md:grid-cols-2">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">No. 03 - Questions</p>
<h2 class="mt-2 font-display text-2xl font-bold tracking-tight md:text-3xl">Asked<br>often.</h2>
</div>
<div class="border-t border-ink">
<div class="border-b border-line py-4"><p class="font-semibold">Do I need an account to track?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">No. The receipt number is enough - tracking here is public and needs no login.</p></div>
<div class="border-b border-line py-4"><p class="font-semibold">What if I lose my receipt number?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Mention your name at the counter. Every load is filed under your customer ID.</p></div>
<div class="border-b border-line py-4"><p class="font-semibold">Can I pay later?</p><p class="mt-0.5 text-[13px] leading-relaxed text-ink-2">Yes. Unpaid loads can be settled at pickup - the receipt shows the bill status.</p></div>
</div>
</div>

{{-- CTA --}}
@guest
<div class="mt-16 border border-ink bg-white px-6 py-8 text-center md:px-10 md:py-10">
<div class="mx-auto max-w-xl">
<div>
<h2 class="font-display text-2xl font-bold tracking-tight md:text-3xl">Something wrong?</h2>
<p class="mt-2 text-sm leading-relaxed text-ink-2">Wrong status, missing load, or a billing question - report it straight to our email.</p>
</div>
<div class="mt-6 flex justify-center gap-2">
<a href="mailto:help@laundrey.test?subject=Problem%20report&body=Hello%20Laundrey%2C%20I%20want%20to%20report%20a%20problem%3A%20" class="h-11 rounded-md bg-ink px-6 text-sm font-semibold leading-10 text-white hover:bg-black">Report via Email</a>
</div>
</div>
</div>
@endguest
@endsection
```

## Langkah 5 — Deploy Coolify sampai LIVE

Samakan PHP image dengan `composer.json`. Env: APP_*, pgsql, sslmode, stderr. Restart loop → Logs container. **Commit:** `chore: docker` → push + deploy.

**Path:** `Dockerfile`

```
# ---------- Stage 1: PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --prefer-dist \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- Stage 2: frontend assets ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build && rm -rf node_modules

# ---------- Stage 3: runtime ----------
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_pgsql mbstring zip gd bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
```

**Path:** `docker/entrypoint.sh`

```
#!/bin/sh
set -e

# Laravel caches (safe to rebuild every boot)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Database schema (safe to re-run)
php artisan migrate --force

exec apache2-foreground
```

**Path:** `.dockerignore`

```
.git
.github
node_modules
vendor
tests
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
database/database.sqlite
.env
.env.*
!.env.example
.opencode
.claude
.agents
npm-debug.log
```

## Checklist akhir

- [ ] Register → walk-in → tahap → tracking → struk; tenant lain 404
- [ ] LIVE + link README + video individu
