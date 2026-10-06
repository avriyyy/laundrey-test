# 01 — Anggota 1: Auth, Tenant & Master Service

**Peran:** fondasi. Dikerjakan pertama. Hasil: register/login/logout Sanctum, multi-tenant, dan **CRUD Services**. Semua kode di bawah copy-paste, sesuaikan bila perlu.

**Prasyarat:** `inisialisasi.md` selesai, branch `pika` (buat: `git checkout main && git pull && git checkout -b pika`; merge ke `main` via PR setelah checklist serah terima di bawah hijau).

---

## Langkah 1 — Exception & middleware global

**Path:** `bootstrap/app.php` (timpa seluruh file)

```php
<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['sukses' => false, 'pesan' => 'Sumber daya tidak ditemukan'], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['sukses' => false, 'pesan' => 'Data yang dikirim tidak valid', 'galat' => $e->errors()], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['sukses' => false, 'pesan' => 'Token tidak valid atau belum dikirim'], 401);
            }
            if (! $request->expectsJson()) {
                return redirect()->guest(route('login'));
            }
        });
    })->create();
```

**Fungsi:** alias `role`, trust proxy deploy, error API konsisten.
**Commit:** `chore: samakan error API + middleware role` → push boleh.

---

## Langkah 2 — Middleware peran

**Path:** `app/Http/Middleware/EnsureRole.php` (baru)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['sukses' => false, 'pesan' => 'Token tidak valid atau belum dikirim'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['sukses' => false, 'pesan' => 'Akses ditolak untuk peran ini'], 403);
        }

        return $next($request);
    }
}
```

**Commit:** `feat: middleware role untuk otorisasi`

---

## Langkah 3 — Migration

```bash
php artisan make:migration create_tenants_table --no-interaction
php artisan make:migration add_role_phone_to_users_table --no-interaction
php artisan make:migration create_services_table --no-interaction
php artisan make:migration add_tenant_id_to_tables --no-interaction
```

**Path:** `*_create_tenants_table.php` — method `up`:

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->char('prefix', 3)->unique();
    $table->string('phone', 20)->nullable();
    $table->string('address', 255)->nullable();
    $table->timestamps();
});
```

`down`: `Schema::dropIfExists('tenants');`

**Path:** `*_add_role_phone_to_users_table.php` — `up`: tambah `role` string default `pelanggan` + `phone` nullable; `down`: drop keduanya.

**Path:** `*_create_services_table.php` — `up`:

```php
Schema::create('services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
    $table->string('service_name', 100);
    $table->decimal('price_per_unit', 12, 2);
    $table->string('unit_type', 20)->default('kg');
    $table->integer('estimated_hours')->default(24);
    $table->timestamps();
});
```

**Path:** `*_add_tenant_id_to_tables.php` — `up`: `tenant_id` nullable + FK di `users` (nullOnDelete) dan `services` (cascade); `down`: drop keduanya.

**Cek:** `php artisan migrate`
**Commit:** `feat: migration tenant, user role, service` → push (dibutuhkan Anggota 2).

---

## Langkah 4 — Model

**Path:** `app/Models/Tenant.php` (baru)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'prefix', 'phone', 'address'];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function services(): HasMany { return $this->hasMany(Service::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
```

**Path:** `app/Models/Service.php` (baru, `php artisan make:model Service`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['service_name', 'price_per_unit', 'unit_type', 'estimated_hours', 'tenant_id'];

    protected function casts(): array
    {
        return ['price_per_unit' => 'decimal:2', 'estimated_hours' => 'integer'];
    }

    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
```

**Path:** `app/Models/User.php` (edit bawaan) — pastikan trait `HasApiTokens`, fillable `['name','email','password','role','phone','tenant_id']`, casts ada `'password' => 'hashed'`, tambah:

```php
public function orders(): HasMany { return $this->hasMany(Order::class); }
public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

public function customerCode(): string
{
    return 'CUST-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
}

public function isAdmin(): bool { return $this->role === 'admin'; }
```

**Commit:** `feat: model tenant, user sanctum, service`

---

## Langkah 5 — Factory & seeder

**Path:** `database/factories/TenantFactory.php` (baru)

```php
public function definition(): array
{
    return [
        'name' => fake()->company().' Laundry',
        'prefix' => strtoupper(fake()->unique()->lexify('???')),
    ];
}
```

**Path:** `database/factories/ServiceFactory.php` (baru, `php artisan make:factory ServiceFactory`)

```php
public function definition(): array
{
    return [
        'tenant_id' => \App\Models\Tenant::first()?->id ?? \App\Models\Tenant::factory()->create()->id,
        'service_name' => fake()->randomElement(['Cuci Kering Reguler', 'Cuci Setrika Express', 'Setrika Saja', 'Cuci Satuan Jas']),
        'price_per_unit' => fake()->randomElement([8000, 10000, 12000, 15000, 25000]),
        'unit_type' => fake()->randomElement(['kg', 'pcs']),
        'estimated_hours' => fake()->randomElement([6, 12, 24, 48]),
    ];
}
```

**Path:** `database/factories/UserFactory.php` (edit `definition`, tambah di awal return):

```php
'tenant_id' => \App\Models\Tenant::first()?->id ?? \App\Models\Tenant::factory()->create()->id,
'role' => 'pelanggan',
```

**Path:** `database/seeders/ServiceSeeder.php` (baru) — 4 layanan + `tenant_id` dari tenant pertama.

**Path:** `database/seeders/LaundreySeeder.php` — buat tenant `INV`/`Laundrey`, admin `admin@laundrey.test`, superadmin `super@laundrey.test`, contoh customer. Contoh order/lengkapnya bareng Anggota 2. Daftarkan di `DatabaseSeeder`.

**Cek:** `php artisan migrate:fresh --seed`
**Commit:** `feat: factory + seeder dasar` → push.

---

## Langkah 6 — Form Request

```bash
php artisan make:request RegisterRequest --no-interaction
php artisan make:request LoginRequest --no-interaction
php artisan make:request StoreServiceRequest --no-interaction
php artisan make:request UpdateServiceRequest --no-interaction
```

**Path:** `app/Http/Requests/RegisterRequest.php`

```php
protected function prepareForValidation(): void
{
    if ($this->has('prefix')) {
        $this->merge(['prefix' => strtoupper((string) $this->input('prefix'))]);
    }
}

public function rules(): array
{
    return [
        'laundry_name' => ['required', 'string', 'max:100'],
        'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix'],
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:100', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'phone' => ['nullable', 'string', 'max:20'],
    ];
}

public function messages(): array
{
    return [
        'prefix.regex' => 'Prefix must be 3 capital letters',
        'prefix.unique' => 'Prefix already taken by another laundry',
    ];
}
```

`authorize()` selalu `return true;` di semua request.

**Path:** `LoginRequest.php` — `email` required email, `password` required string.

**Path:** `StoreServiceRequest.php`

```php
return [
    'service_name' => ['required', 'string', 'max:100'],
    'price_per_unit' => ['required', 'numeric', 'min:0'],
    'unit_type' => ['required', 'string', 'in:kg,pcs'],
    'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
];
```

**Path:** `UpdateServiceRequest.php` — sama dengan `sometimes` di tiap aturan.

**Commit:** `feat: form request auth + service`

---

## Langkah 7 — Resource + controller + route

**Path:** `app/Http/Resources/ServiceResource.php` (`php artisan make:resource ServiceResource`)

```php
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
```

**Path:** `app/Http/Controllers/Api/V1/AuthController.php`

```php
public function register(RegisterRequest $request): JsonResponse
{
    $data = $request->validated();

    [$tenant, $admin, $token] = DB::transaction(function () use ($data) {
        $tenant = Tenant::create(['name' => $data['laundry_name'], 'prefix' => $data['prefix']]);
        $admin = User::create([
            'tenant_id' => $tenant->id, 'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']), 'role' => 'admin',
            'phone' => $data['phone'] ?? null,
        ]);
        $token = $admin->createToken('token-perangkat', ['order:tulis', 'service:tulis', 'track:tulis'])->plainTextToken;

        return [$tenant, $admin, $token];
    });

    return response()->json([
        'sukses' => true, 'pesan' => 'Laundry registered',
        'data' => [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'prefix' => $tenant->prefix],
            'pengguna' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role],
            'token' => $token,
        ],
    ], 201);
}

public function login(LoginRequest $request): JsonResponse
{
    $data = $request->validated();
    $pengguna = User::where('email', $data['email'])->first();

    if ($pengguna === null || Hash::check($data['password'], $pengguna->password) === false) {
        return response()->json(['sukses' => false, 'pesan' => 'Email atau kata sandi tidak sesuai'], 401);
    }

    $token = $pengguna->createToken('token-perangkat', $pengguna->role === 'admin'
        ? ['order:tulis', 'service:tulis', 'track:tulis'] : [])->plainTextToken;

    return response()->json(['sukses' => true, 'pesan' => 'Login berhasil',
        'data' => ['pengguna' => ['id' => $pengguna->id, 'name' => $pengguna->name, 'email' => $pengguna->email, 'role' => $pengguna->role], 'token' => $token]]);
}

public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json(['sukses' => true, 'pesan' => 'Logout berhasil']);
}
```

Import: `RegisterRequest, LoginRequest, Tenant, User, JsonResponse, Request, DB, Hash`.

**Path:** `app/Http/Controllers/Api/V1/ServiceController.php` — pola di tiap method: scope `where('tenant_id', $request->user()->tenant_id)`; `index` + filter `cari` + `paginate(min(per_halaman,100))`; `store` tambah `tenant_id` → 201; `show/update/destroy` via `findOrFail` scope (tanda tangan `int $service`, bukan model binding, agar ID tenant lain 404); `destroy` tolak 422 bila `orders()->exists()`.

**Path:** `routes/api.php`

```php
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ServiceController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);

        Route::middleware('role:admin')->group(function () {
            Route::post('/services', [ServiceController::class, 'store']);
            Route::put('/services/{service}', [ServiceController::class, 'update']);
            Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
        });
    });
});
```

**Cek manual:**
```bash
curl -s -X POST localhost:8000/api/v1/auth/register -H "Accept: application/json" -H "Content-Type: application/json" \
 -d '{"laundry_name":"Coba","prefix":"CBA","name":"Owner","email":"o@x.test","password":"password123","password_confirmation":"password123"}'
# login → salin token, lalu:
curl -s localhost:8000/api/v1/services -H "Accept: application/json" -H "Authorization: Bearer TOKEN"
```

**Commit:** `feat: auth sanctum + CRUD service API` → push, kabari Anggota 2.

## Checklist serah terima

- [ ] `migrate:fresh --seed` hijau, register/login/logout curl OK
- [ ] CRUD service 5 endpoint + tenant isolation OK, `pint` bersih
