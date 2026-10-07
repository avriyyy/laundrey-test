# anggota-1 — pika: Fondasi, Auth & CRUD Tenants

**CRUD milikmu (backend + halaman): Tenants**

**Branch:** `pika` (buat: `git checkout main && git pull && git checkout -b pika`; merge via PR setelah checklist hijau).

## Langkah 1 — Bootstrap & middleware

Alias `role`, trust proxy, error API konsisten. **Commit:** `chore: bootstrap + middleware` → push boleh.

**Path:** `bootstrap/app.php`

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
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Sumber daya tidak ditemukan',
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Data yang dikirim tidak valid',
                    'galat' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Token tidak valid atau belum dikirim',
                ], 401);
            }

            if (! $request->expectsJson()) {
                return redirect()->guest(route('login'));
            }
        });
    })->create();
```

**Path:** `app/Http/Middleware/EnsureRole.php`

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
            return response()->json([
                'sukses' => false,
                'pesan' => 'Token tidak valid atau belum dikirim',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akses ditolak untuk peran ini',
            ], 403);
        }

        return $next($request);
    }
}
```

## Langkah 2 — Migration tenant + user

Jalankan `php artisan migrate`. **Commit:** `feat: migration tenant user` → push (dibutuhkan semua).

**Path:** `database/migrations/2026_10_06_141538_create_tenants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('prefix', 3)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```

**Path:** `database/migrations/2026_10_06_054614_add_role_phone_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('pelanggan')->after('email');
            $table->string('phone', 20)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone']);
        });
    }
};
```

**Path:** `database/migrations/2026_10_06_141539_add_tenant_id_to_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Laundrey',
            'prefix' => 'INV',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->update(['tenant_id' => $tenantId]);
        DB::table('services')->update(['tenant_id' => $tenantId]);
        DB::table('orders')->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('tenants');
    }
};
```

**Path:** `database/migrations/2026_10_06_152802_add_contact_to_tenants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('prefix');
            $table->string('address', 255)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
};
```

## Langkah 3 — Model Tenant + User + seeder akun

Trait `HasApiTokens`, `customerCode()`, relasi. Seeder: tenant INV + operator + platform (lihat `LaundreySeeder`). **Cek:** `migrate:fresh --seed`. **Commit:** `feat: model + seed` → push.

**Path:** `app/Models/Tenant.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefix',
        'phone',
        'address',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

**Path:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderTracks(): HasMany
    {
        return $this->hasMany(OrderTrack::class, 'updated_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customerCode(): string
    {
        return 'CUST-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }
}
```

## Langkah 4 — Auth

Register laundry (transaction), login by role, logout. **Cek curl.** **Commit:** `feat: auth` → push, kabari tim.

**Path:** `app/Http/Requests/RegisterRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
}
```

**Path:** `app/Http/Requests/LoginRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

**Path:** `app/Http/Controllers/Api/V1/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['laundry_name'],
                'prefix' => $data['prefix'],
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'tenant',
                'phone' => $data['phone'] ?? null,
            ]);

            $token = $admin->createToken('token-perangkat', ['order:tulis', 'service:tulis', 'track:tulis'])->plainTextToken;

            return [$tenant, $admin, $token];
        });

        [$tenant, $admin, $token] = $result;

        return response()->json([
            'sukses' => true,
            'pesan' => 'Laundry registered',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'prefix' => $tenant->prefix,
                ],
                'pengguna' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $pengguna = User::where('email', $data['email'])->first();

        if ($pengguna === null || Hash::check($data['password'], $pengguna->password) === false) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Email atau kata sandi tidak sesuai',
            ], 401);
        }

        if (! in_array($pengguna->role, ['tenant', 'admin'], true)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Customer accounts are managed by the laundry counter',
            ], 403);
        }

        $abilities = match ($pengguna->role) {
            'tenant', 'admin' => ['order:tulis', 'service:tulis', 'track:tulis'],
            default => [],
        };

        $token = $pengguna->createToken('token-perangkat', $abilities)->plainTextToken;

        return response()->json([
            'sukses' => true,
            'pesan' => 'Login berhasil',
            'data' => [
                'pengguna' => [
                    'id' => $pengguna->id,
                    'name' => $pengguna->name,
                    'email' => $pengguna->email,
                    'role' => $pengguna->role,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Logout berhasil',
        ]);
    }
}
```

**Path:** `app/Http/Controllers/Web/AuthWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records'])->onlyInput('email');
        }

        if (! in_array(Auth::user()->role, ['tenant', 'admin'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Customer accounts are managed by the laundry counter'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $target = Auth::user()->role === 'admin' ? route('admin.dashboard') : route('dashboard');

        return redirect()->intended($target)->with('sukses', 'Signed in. Welcome back.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $admin = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['laundry_name'],
                'prefix' => $data['prefix'],
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'tenant',
                'phone' => $data['phone'] ?? null,
            ]);
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('sukses', 'Laundry registered. Welcome.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('sukses', 'Signed out.');
    }
}
```

## Langkah 5 — CRUD Tenants + platform + settings

Form create tenant SEKALIGUS akun login. **Commit:** `feat: tenants CRUD + platform` → push.

**Path:** `app/Http/Controllers/Web/TenantWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TenantWebController extends Controller
{
    public function index(Request $request): View
    {
        $kueri = Tenant::query()->withCount(['users', 'services', 'orders']);

        if ($request->filled('cari')) {
            $kataKunci = $request->query('cari');
            $kueri->where(function ($sub) use ($kataKunci) {
                $sub->where('name', 'like', '%'.$kataKunci.'%')
                    ->orWhere('prefix', 'like', '%'.$kataKunci.'%');
            });
        }

        $tenants = $kueri->orderBy('name')->paginate(12)->withQueryString();
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');

        return view('tenants.index', compact('tenants', 'totalOrders', 'totalRevenue'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->loadCount(['users', 'services', 'orders']);
        $orders = Order::with(['customer', 'service'])->where('tenant_id', $tenant->id)->orderBy('created_at', 'desc')->paginate(10);
        $revenue = (float) Order::where('tenant_id', $tenant->id)->where('payment_status', 'paid')->sum('total_price');
        $admins = $tenant->users()->where('role', 'tenant')->get(['id', 'name', 'email']);

        return view('tenants.show', compact('tenant', 'orders', 'revenue', 'admins'));
    }

    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $admin = $request->validate([
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data, $admin) {
            $tenant = Tenant::create($data);

            User::create([
                'tenant_id' => $tenant->id,
                'name' => $admin['admin_name'],
                'email' => $admin['admin_email'],
                'password' => Hash::make($admin['admin_password']),
                'role' => 'tenant',
            ]);
        });

        return redirect()->route('admin.tenants.index')->with('sukses', 'Tenant added with login account.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($this->validated($request, $tenant->id));

        return redirect()->route('admin.tenants.show', $tenant)->with('sukses', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('sukses', 'Tenant deleted with all its data.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignore = null): array
    {
        if ($request->input('prefix')) {
            $request->merge(['prefix' => strtoupper((string) $request->input('prefix'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix'.($ignore ? ','.$ignore : '')],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'prefix.regex' => 'Prefix must be 3 capital letters',
            'prefix.unique' => 'Prefix already taken by another laundry',
        ]);
    }
}
```

**Path:** `app/Http/Controllers/Web/SettingWebController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingWebController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        if ($request->input('prefix')) {
            $request->merge(['prefix' => strtoupper((string) $request->input('prefix'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix,'.auth()->user()->tenant_id],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'prefix.regex' => 'Prefix must be 3 capital letters',
            'prefix.unique' => 'Prefix already taken by another laundry',
        ]);

        auth()->user()->tenant->update([
            'name' => $data['name'],
            'prefix' => $data['prefix'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        auth()->user()->update(['name' => $data['owner_name']]);

        return back()->with('sukses', 'Settings saved. New receipts use the new prefix.');
    }
}
```

**Path:** `app/Http/Controllers/Web/DashboardController.php`

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $tenantId = auth()->user()->tenant_id;

        $totalOrders = Order::where('tenant_id', $tenantId)->count();
        $processing = Order::where('tenant_id', $tenantId)->whereNotIn('current_status', ['Ready', 'Completed'])->count();
        $ready = Order::where('tenant_id', $tenantId)->where('current_status', 'Ready')->count();
        $revenue = (float) Order::where('tenant_id', $tenantId)->where('payment_status', 'paid')->sum('total_price');
        $recentOrders = Order::with(['customer', 'service'])->where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->limit(8)->get();
        $readyOrders = Order::with(['customer', 'service'])->where('tenant_id', $tenantId)->where('current_status', 'Ready')->orderBy('updated_at')->limit(5)->get();
        $totalServices = Service::where('tenant_id', $tenantId)->count();

        return view('dashboard.admin', compact('totalOrders', 'processing', 'ready', 'revenue', 'recentOrders', 'readyOrders', 'totalServices'));
    }

    public function platform(): View
    {
        $tenants = Tenant::withCount(['users', 'services', 'orders'])->orderBy('name')->limit(8)->get();
        $tenantCount = Tenant::count();
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');

        return view('dashboard.platform', compact('tenants', 'tenantCount', 'totalOrders', 'totalRevenue'));
    }
}
```

**Path:** `resources/views/tenants/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Tenants - Laundrey')
@section('breadcrumb', 'Tenants')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $tenants->total() }} shops · {{ $totalOrders }} orders platform-wide</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Tenants.</h1>
</div>
<a href="{{ route('admin.tenants.create') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">+ Add tenant</a>
</div>
<form method="GET" class="mt-6 flex gap-2">
<input name="cari" placeholder="Find shop or prefix…" value="{{ request('cari') }}" class="h-10 flex-1 rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none">
<button class="h-10 shrink-0 rounded-md border border-ink px-4 text-sm font-medium hover:bg-ink hover:text-white">Search</button>
</form>
<div class="mt-5 border-t border-ink">
@forelse($tenants as $t)
<a href="{{ route('admin.tenants.show', $t) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-20 shrink-0 font-mono text-xs font-bold">{{ $t->prefix }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $t->name }}</span><span class="block truncate text-xs text-muted">{{ $t->orders_count }} orders · {{ $t->services_count }} services · {{ $t->users_count }} users</span></span>
<span class="shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M Y') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No shops match.</p>
@endforelse
</div>
<div class="mt-4 grid grid-cols-2 gap-4 text-[13px] text-ink-2">
<p>Platform revenue (paid): <b class="font-mono tabular-nums">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</b></p>
</div>
<div class="mt-2 text-[13px]">{{ $tenants->links() }}</div>
@endsection
```

**Path:** `resources/views/tenants/form.blade.php`

```blade
<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name</label><input name="name" value="{{ old('name', $tenant->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Prefix (3 letters)</label><input name="prefix" value="{{ old('prefix', $tenant->prefix ?? '') }}" required maxlength="3" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone</label><input name="phone" value="{{ old('phone', $tenant->phone ?? '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Address</label><textarea name="address" rows="2" class="w-full rounded-md border border-line-strong bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none">{{ old('address', $tenant->address ?? '') }}</textarea></div>
@if(! isset($tenant->id))
<div class="border-t border-line pt-4">
<p class="mb-3 font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Login account</p>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name</label><input name="admin_name" value="{{ old('admin_name') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email</label><input name="admin_email" type="email" value="{{ old('admin_email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
<div class="mt-4"><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password (min 8)</label><input name="admin_password" type="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
@endif
</div>
```

**Path:** `resources/views/tenants/create.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Add tenant - Laundrey')
@section('breadcrumb', 'Tenants / Add')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">New shop</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Add tenant.</h1>
<form method="POST" action="{{ route('admin.tenants.store') }}" class="mt-8">@csrf
@include('tenants.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save tenant</button><a href="{{ route('admin.tenants.index') }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/tenants/edit.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Edit tenant - Laundrey')
@section('breadcrumb', 'Tenants / Edit')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">{{ $tenant->prefix }}</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Edit tenant.</h1>
<form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="mt-8">@csrf @method('PUT')
@include('tenants.form')
<div class="mt-5 flex gap-2 border-t border-line pt-5"><button class="h-11 rounded-md bg-ink px-6 text-sm font-semibold text-white hover:bg-black">Save changes</button><a href="{{ route('admin.tenants.show', $tenant) }}" class="h-11 rounded-md border border-line-strong px-5 text-sm font-medium leading-10 hover:bg-paper">Cancel</a></div>
</form>
@endsection
```

**Path:** `resources/views/tenants/show.blade.php`

```blade
@extends('layouts.app')
@section('title', $tenant->name.' - Laundrey')
@section('breadcrumb', 'Tenants / Detail')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $tenant->prefix }} · since {{ $tenant->created_at->format('d M Y') }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $tenant->name }}</h1>
<p class="mt-1 text-sm text-ink-2">Admins: {{ $admins->pluck('email')->join(', ') ?: 'none' }}</p>
</div>
<div class="flex gap-2">
<a href="{{ route('admin.tenants.edit', $tenant) }}" class="h-9 rounded-md border border-ink px-3 text-[13px] font-medium leading-8 hover:bg-ink hover:text-white">Edit</a>
<form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this shop and ALL its data?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete shop</button></form>
</div>
</div>

<div class="mt-6 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-3"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Orders</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $tenant->orders_count }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Revenue</p><p class="mt-0.5 font-mono text-xl font-bold tabular-nums">Rp{{ number_format($revenue, 0, ',', '.') }}</p></div>
<div class="border-l border-line px-1 py-3 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Services</p><p class="mt-0.5 font-display text-2xl font-bold tabular-nums">{{ $tenant->services_count }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Recent orders</h2>
<div class="mt-3 border-t border-ink">
@forelse($orders as $o)
<div class="flex items-center gap-4 border-b border-line py-3.5">
<span class="w-36 shrink-0 font-mono text-xs font-bold tracking-wide">{{ $o->invoice_number }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $o->customer->name }}</span><span class="block truncate text-xs text-muted">{{ $o->service->service_name }}</span></span>
<span class="hidden sm:block"><x-status-badge :status="$o->current_status" /></span>
<span class="w-24 shrink-0 text-right font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($o->total_price, 0, ',', '.') }}</span>
</div>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No orders yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
```

**Path:** `resources/views/dashboard/platform.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Platform - Laundrey')
@section('breadcrumb', 'Platform')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Platform overview</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">All shops.</h1>
</div>
<a href="{{ route('admin.tenants.index') }}" class="h-10 rounded-md bg-ink px-4 text-sm font-semibold leading-10 text-white hover:bg-black">Manage tenants</a>
</div>

<div class="mt-8 grid grid-cols-3 border-y border-ink py-1">
<div class="px-1 py-4"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Shops</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $tenantCount }}</p></div>
<div class="border-l border-line px-1 py-4 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Orders</p><p class="mt-1 font-display text-4xl font-bold tabular-nums tracking-tight">{{ $totalOrders }}</p></div>
<div class="border-l border-line px-1 py-4 pl-5"><p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Revenue</p><p class="mt-1 font-display text-2xl font-bold tabular-nums tracking-tight md:text-3xl">Rp{{ number_format($totalRevenue, 0, ',', '.') }}</p></div>
</div>

<h2 class="mb-1 mt-8 font-display text-xl font-bold tracking-tight">Shops</h2>
<div class="mt-3 border-t border-ink">
@forelse($tenants as $t)
<a href="{{ route('admin.tenants.show', $t) }}" class="group flex items-center gap-4 border-b border-line py-3.5">
<span class="w-20 shrink-0 font-mono text-xs font-bold">{{ $t->prefix }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium group-hover:underline">{{ $t->name }}</span><span class="block text-xs text-muted">{{ $t->orders_count }} orders · {{ $t->users_count }} users</span></span>
<span class="shrink-0 font-mono text-xs text-muted">{{ $t->created_at->format('d M Y') }}</span>
</a>
@empty
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No shops registered yet.</p>
@endforelse
</div>
@endsection
```

## Langkah 6 — Layout + tema + auth view

**Cek:** `npm run build`. **Commit:** `feat: layout tema route` → push.

**Path:** `resources/css/app.css`

```
@import "tailwindcss";

@custom-variant dark (&:where(.dark, .dark *));

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-display: 'Space Grotesk', 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;

    --color-paper: #f6f5f2;
    --color-ink: #1c1917;
    --color-ink-2: #57534e;
    --color-muted: #a8a29e;
    --color-line: #e6e3dc;
    --color-line-strong: #d6d3cb;
    --color-primary: #5e6ad2;
    --color-primary-hover: #4f5bc5;
}

html {
    color-scheme: light;
}

html.dark {
    color-scheme: dark;
}

html.dark body {
    background-color: #1c1917;
    color: #f2efe9;
}

html.dark .bg-paper {
    background-color: #1c1917;
}

html.dark .bg-paper\/95 {
    background-color: rgb(28 25 23 / 0.95);
}

html.dark .bg-white {
    background-color: #25211e;
}

html.dark .text-ink {
    color: #f2efe9;
}

html.dark .text-ink-2 {
    color: #c9c3b8;
}

html.dark .text-muted {
    color: #8d877b;
}

html.dark .border-line {
    border-color: #3b352f;
}

html.dark .border-line-strong {
    border-color: #575046;
}

html.dark .border-ink {
    border-color: #e8e4da;
}

html.dark .bg-line {
    background-color: #3b352f;
}

html.dark .bg-ink {
    background-color: #e8e4da;
}

html.dark .bg-ink,
html.dark .bg-ink *,
html.dark .text-white {
    color: #1c1917 !important;
}

html.dark .hover\:bg-paper:hover {
    background-color: #38322c;
}

html.dark .hover\:bg-black:hover,
html.dark .hover\:bg-ink:hover {
    background-color: #ffffff !important;
    color: #1c1917 !important;
}

html.dark .hover\:bg-black:hover *,
html.dark .hover\:bg-ink:hover * {
    color: #1c1917 !important;
}

html.dark .hover\:text-white:hover {
    color: #1c1917;
}

html.dark .hover\:text-ink:hover {
    color: #f2efe9;
}
```

**Path:** `resources/views/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Laundrey')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
(function () {
    try {
        var saved = localStorage.getItem('laundrey-theme');
        if (saved === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {
        document.documentElement.classList.add('dark');
    }
})();
function toggleTheme() {
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('laundrey-theme', dark ? 'dark' : 'light'); } catch (e) {}
    document.querySelectorAll('[data-theme-icon-moon]').forEach(function (el) { el.classList.toggle('hidden', dark); });
    document.querySelectorAll('[data-theme-icon-sun]').forEach(function (el) { el.classList.toggle('hidden', !dark); });
    document.querySelectorAll('[data-theme-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
}
document.addEventListener('DOMContentLoaded', function () {
    var dark = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-icon-moon]').forEach(function (el) { el.classList.toggle('hidden', dark); });
    document.querySelectorAll('[data-theme-icon-sun]').forEach(function (el) { el.classList.toggle('hidden', !dark); });
    document.querySelectorAll('[data-theme-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
});
</script>
</head>
<body class="bg-paper font-sans text-sm text-ink antialiased">
<div class="flex min-h-screen">

@auth
{{-- Sidebar (signed in only) --}}
<aside class="fixed inset-y-0 left-0 hidden w-60 shrink-0 flex-col border-r border-line bg-white px-4 py-6 md:flex">
<div class="px-1">
@if(auth()->user()->role === 'admin')
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">Platform console</p>
@else
<p class="font-display text-lg font-bold tracking-tight">{{ auth()->user()->tenant->name }}<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">by Laundrey</p>
@endif
</div>
<nav class="mt-8 flex flex-1 flex-col gap-0.5 text-[13.5px]">
@if(auth()->user()->role === 'admin')
<a href="{{ route('admin.dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.tenants.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
@if(in_array(auth()->user()->role, ['tenant']))
<a href="{{ route('orders.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('orders.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Orders</a>
<a href="{{ route('services.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('services.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Services & pricing</a>
<a href="{{ route('customers.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('customers.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Customers</a>
<a href="{{ route('promos.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('promos.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Promos</a>
@endif
@if(in_array(auth()->user()->role, ['tenant']))
<a href="{{ route('operations.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('operations.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Operations</a>
@endif
@endif
</nav>
<button onclick="toggleTheme()" class="mb-1 mt-3 flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] font-medium text-ink-2 hover:bg-paper hover:text-ink">
<svg data-theme-icon-moon class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162z"/></svg>
<svg data-theme-icon-sun class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
<span data-theme-label>Dark mode</span>
</button>
<div class="border-t border-line pt-3">
<div class="flex items-center justify-between gap-2 px-1">
<div class="min-w-0"><p class="truncate text-[13px] font-semibold">{{ auth()->user()->role === 'tenant' && auth()->user()->tenant ? auth()->user()->tenant->name : auth()->user()->name }}</p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">{{ auth()->user()->role === 'tenant' ? 'TENANT' : auth()->user()->role }}</p></div>
@if(auth()->user()->role === 'tenant')
<button onclick="openSettings()" title="Settings" class="shrink-0 rounded-md p-1.5 text-muted hover:bg-paper hover:text-ink"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg></button>
@endif
</div>
<form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-md border border-line px-2 py-1.5 text-[13px] font-medium hover:border-line-strong hover:bg-paper">Log out</button></form>
</div>
</aside>
@endauth

<div class="flex min-h-screen min-w-0 flex-1 flex-col @auth md:ml-60 @endauth">
{{-- Topbar --}}
<header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-line bg-paper/95 px-4 py-2.5 backdrop-blur md:px-8">
@guest
<a href="{{ route('home') }}" class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></a>
@endguest
@auth
<p class="font-mono text-[11px] uppercase tracking-[0.18em] text-muted">@yield('breadcrumb', 'Laundrey')</p>
@endauth
<div class="flex items-center gap-2">
@auth
@if(auth()->user()->role === 'tenant')
<form method="GET" action="{{ route('orders.index') }}" class="absolute left-1/2 hidden -translate-x-1/2 items-center lg:flex">
<input name="cari" value="{{ request('cari') }}" placeholder="Search orders…" class="h-8 w-64 border-b border-line-strong bg-transparent text-center font-mono text-xs placeholder:text-muted focus:border-primary focus:outline-none">
</form>
@endif
<span class="font-mono text-xs text-ink-2">{{ auth()->user()->name }} <span class="text-muted">/ {{ auth()->user()->role === 'tenant' ? 'tenant' : auth()->user()->role }}</span></span>
@else
<button onclick="toggleTheme()" title="Toggle theme" class="mr-3 rounded-md border border-line p-1.5 text-muted hover:text-ink">
<svg data-theme-icon-moon class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162z"/></svg>
<svg data-theme-icon-sun class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0z"/></svg>
</button>
@endauth
</div>
</header>

@auth
{{-- Mobile nav (signed in only) --}}
<nav class="flex gap-1 overflow-x-auto border-b border-line bg-white px-3 py-2 text-[13px] font-medium md:hidden">
@if(auth()->user()->role === 'admin')
<a href="{{ route('admin.dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="whitespace-nowrap px-2 py-1">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
@if(in_array(auth()->user()->role, ['tenant']))
<a href="{{ route('orders.index') }}" class="whitespace-nowrap px-2 py-1">Orders</a>
<a href="{{ route('services.index') }}" class="whitespace-nowrap px-2 py-1">Services</a>
<a href="{{ route('customers.index') }}" class="whitespace-nowrap px-2 py-1">Customers</a>
<a href="{{ route('promos.index') }}" class="whitespace-nowrap px-2 py-1">Promos</a>
@endif
@if(in_array(auth()->user()->role, ['tenant']))
<a href="{{ route('operations.index') }}" class="whitespace-nowrap px-2 py-1">Operations</a>
@endif
@endif
</nav>
@endauth

<main class="mx-auto w-full flex-1 px-4 py-8 @hasSection('wide') max-w-none md:px-10 @else max-w-6xl md:px-8 md:py-10 @endif">
@if(session('sukses'))
<p class="mb-6 flex items-start justify-between gap-3 border-l-2 border-emerald-600 bg-white px-4 py-3 text-[13px]"><span>✓ {{ session('sukses') }}</span><button onclick="this.parentElement.remove()" class="shrink-0 font-mono text-muted hover:text-ink">×</button></p>
@endif
@if($errors->any())
<div class="mb-6 border-l-2 border-red-600 bg-white px-4 py-3 text-[13px]"><div class="flex items-start justify-between gap-3"><span>× Something went wrong</span><button onclick="this.closest('div').remove()" class="shrink-0 font-mono text-muted hover:text-ink">×</button></div>
<ul class="ml-4 mt-1 list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
@yield('content')
</main>
<footer class="mx-auto w-full pb-6 flex items-center justify-between border-t border-line pt-4 font-mono text-[11px] uppercase tracking-[0.18em] text-muted @hasSection('wide') max-w-none px-4 md:px-10 @else max-w-6xl px-4 md:px-8 @endif">
<span>Laundrey - laundry tracking</span><span>Est. 2026</span>
</footer>
</div>
</div>
@auth
@if(auth()->user()->role === 'tenant' && isset($layoutTenant) && $layoutTenant)
<div id="settingsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-ink/40" onclick="closeSettings()"></div>
<div class="relative w-full max-w-md border border-line bg-white">
<div class="flex items-center justify-between border-b border-line px-5 py-3.5">
<p class="font-display text-base font-bold tracking-tight">General settings</p>
<button onclick="closeSettings()" class="font-mono text-muted hover:text-ink">×</button>
</div>
<form method="POST" action="{{ route('settings.update') }}" class="flex flex-col gap-4 px-5 py-5">@csrf @method('PUT')
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name</label><input name="name" value="{{ old('name', $layoutTenant->name) }}" required class="h-10 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name</label><input name="owner_name" value="{{ old('owner_name', auth()->user()->name) }}" required class="h-10 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Receipt prefix (3 capital letters)</label><input name="prefix" value="{{ old('prefix', $layoutTenant->prefix) }}" required maxlength="3" class="h-10 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone</label><input name="phone" value="{{ old('phone', $layoutTenant->phone) }}" class="h-10 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Address</label><textarea name="address" rows="2" class="w-full rounded-md border border-line-strong bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none">{{ old('address', $layoutTenant->address) }}</textarea></div>
<p class="text-xs text-muted">New receipts use the new prefix. Old receipts keep theirs.</p>
<div class="flex gap-2 border-t border-line pt-4"><button class="h-10 rounded-md bg-ink px-5 text-sm font-semibold text-white hover:bg-black">Save</button><button type="button" onclick="closeSettings()" class="h-10 rounded-md border border-line-strong px-5 text-sm font-medium hover:bg-paper">Cancel</button></div>
</form>
</div>
</div>
<script>
function openSettings() {
  var m = document.getElementById('settingsModal');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeSettings() {
  var m = document.getElementById('settingsModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSettings(); });
</script>
@endif
@endauth
</body>
</html>
```

**Path:** `resources/views/components/status-badge.blade.php`

```blade
@props(['status'])
@php($ink = [
    'Received' => 'text-sky-700',
    'Washing' => 'text-amber-700',
    'Drying' => 'text-amber-700',
    'Ironing' => 'text-orange-700',
    'Ready' => 'text-emerald-700',
    'Completed' => 'text-emerald-700',
])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-mono text-[11px] font-bold uppercase tracking-widest '.($ink[$status] ?? 'text-ink-2')]) }}><span class="size-1.5 rounded-full bg-current"></span>{{ $status }}</span>
```

**Path:** `resources/views/components/payment-badge.blade.php`

```blade
@props(['status'])
<span {{ $attributes->merge(['class' => 'font-mono text-[11px] font-bold uppercase tracking-widest '.($status === 'paid' ? 'text-emerald-700' : 'text-amber-700')]) }}>{{ $status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
```

**Path:** `resources/views/auth/login.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Log in - Laundrey')
@section('content')
<div class="mx-auto max-w-sm py-8 md:py-14">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<h1 class="mt-6 font-display text-3xl font-bold tracking-tight">Log in.</h1>
<p class="mt-1.5 text-sm text-ink-2">Shop and platform accounts sign in here.</p>
<form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4 border-t border-ink pt-6">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password</label><input type="password" name="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<button class="h-11 rounded-md bg-ink text-sm font-semibold text-white hover:bg-black">Log in →</button>
</form>
<p class="mt-5 border-t border-line pt-4 text-[13px] text-ink-2">No account yet? <a href="{{ route('register') }}" class="font-semibold text-ink underline">Register here</a></p>
</div>
@endsection
```

**Path:** `resources/views/auth/register.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Register laundry - Laundrey')
@section('content')
<div class="mx-auto max-w-sm py-8 md:py-14">
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<h1 class="mt-6 font-display text-3xl font-bold tracking-tight">Register.</h1>
<p class="mt-1.5 text-sm text-ink-2">One account per shop. Your receipts carry your own 3-letter code.</p>
<form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-4 border-t border-ink pt-6">@csrf
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Laundry name <span class="text-red-600">*</span></label><input name="laundry_name" value="{{ old('laundry_name') }}" placeholder="e.g. Quick Wash Purwokerto" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Receipt prefix (3 capital letters) <span class="text-red-600">*</span></label><input name="prefix" value="{{ old('prefix') }}" placeholder="e.g. QWP" required maxlength="3" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm uppercase focus:border-ink focus:outline-none"></div>
<div class="border-t border-line pt-4"><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Owner name <span class="text-red-600">*</span></label><input name="name" value="{{ old('name') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email <span class="text-red-600">*</span></label><input type="email" name="email" value="{{ old('email') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone <span class="normal-case tracking-normal">(optional)</span></label><input name="phone" value="{{ old('phone') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div class="grid grid-cols-2 gap-3">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Password <span class="text-red-600">*</span></label><input type="password" name="password" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Repeat <span class="text-red-600">*</span></label><input type="password" name="password_confirmation" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
</div>
<button class="h-11 rounded-md bg-ink text-sm font-semibold text-white hover:bg-black">Register shop →</button>
</form>
<p class="mt-5 border-t border-line pt-4 text-[13px] text-ink-2">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-ink underline">Log in here</a></p>
</div>
@endsection
```

**Path:** `routes/api.php`

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromoController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'sukses' => true,
        'pesan' => 'API Laundrey aktif',
        'waktu' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/track/{invoice_number}', [TrackController::class, 'publicTrack']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::middleware('role:tenant')->group(function () {
            Route::post('/services', [ServiceController::class, 'store']);
            Route::put('/services/{service}', [ServiceController::class, 'update']);
            Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
            Route::get('/promos', [PromoController::class, 'index']);
            Route::post('/promos', [PromoController::class, 'store']);
            Route::get('/promos/{promo}', [PromoController::class, 'show']);
            Route::delete('/promos/{promo}', [PromoController::class, 'destroy']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::put('/orders/{order}', [OrderController::class, 'update']);
        });

        Route::middleware('role:tenant')->group(function () {
            Route::post('/orders/{order}/tracks', [TrackController::class, 'store']);
        });
    });
});
```

**Path:** `routes/web.php`

```php
<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CustomerWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OperationWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\PromoWebController;
use App\Http\Controllers\Web\ServiceWebController;
use App\Http\Controllers\Web\SettingWebController;
use App\Http\Controllers\Web\TenantWebController;
use App\Http\Controllers\Web\TrackWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackWebController::class, 'index'])->name('home');
Route::post('/track', [TrackWebController::class, 'track'])->name('track.search');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login']);
    Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthWebController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:tenant')->group(function () {
        Route::get('/orders/{order}/invoice', [OrderWebController::class, 'invoice'])->name('orders.invoice');
        Route::get('/orders/{order}/invoice.pdf', [OrderWebController::class, 'invoicePdf'])->name('orders.invoice.pdf');
        Route::resource('orders', OrderWebController::class);
        Route::resource('services', ServiceWebController::class)->except(['show']);
        Route::resource('promos', PromoWebController::class)->except(['show']);
        Route::resource('customers', CustomerWebController::class);
        Route::get('/customers-lookup', [CustomerWebController::class, 'lookup'])->name('customers.lookup');
        Route::put('/settings', [SettingWebController::class, 'update'])->name('settings.update');
        Route::get('/operations', [OperationWebController::class, 'index'])->name('operations.index');
        Route::post('/operations/{order}/status', [OperationWebController::class, 'updateStatus'])->name('operations.status');
    });

    Route::middleware('role:admin')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'platform'])->name('dashboard');
            Route::resource('tenants', TenantWebController::class);
        });
    });
});
```

## Checklist serah terima

- [ ] Register + login 2 role + logout OK
- [ ] Tenants CRUD + akun login bisa dipakai; tenant lain 403/404
- [ ] `migrate:fresh --seed` hijau, `pint` bersih
