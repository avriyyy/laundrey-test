# 03 — Anggota 3: Frontend, Customer CRUD & Deploy

**Peran:** antarmuka + rilis. **CRUD Customers**, halaman auth, tracking publik, invoice print + QR, Dockerfile + deploy, README.

**Prasyarat:** branch `pika`+`bahtiar` merge ke `main`. Branch `yudha` dari `main` terbaru. Package: `composer require simplesoftwareio/simple-qrcode`.

Aturan desain: tanpa gradient/blob/emoji; bg kertas `#F6F5F2`; Space Grotesk (judul) + Inter (isi) + JetBrains Mono (resi/angka); radius kecil; status = label mono + titik.

---

## Langkah 1 — Asset + layout + komponen

**Path:** `resources/css/app.css` — `@import "tailwindcss";` + `@source` pagination + `@theme` token (`--font-sans/dislay/mono`, `--color-paper/ink/ink-2/muted/line/line-strong/primary`) + blok `html.dark ...` override permukaan (body, bg-white/paper, teks, border, tombol). Cek file final di repo bila ragu.

**Path:** `resources/views/layouts/app.blade.php` — head (font Google + `@vite` + script tema `localStorage laundrey-theme`, default gelap): sidebar (brand nama tenant + `by Laundrey`, nav Dashboard/Orders/Services/Customers/Operations, toggle tema, kartu user + ikon settings modal + logout), topbar (breadcrumb + search order tengah + nama), mobile nav, alert sukses/error dengan tombol ×, footer, modal General settings + JS open/close. Tamu: tanpa sidebar.

**Path:** `resources/views/components/status-badge.blade.php` — label mono uppercase + titik warna per tahap. **Path:** `payment-badge` — teks `Paid`/`Unpaid`.

**Cek:** `npm run build` hijau.
**Commit:** `feat: tema + layout + badge`

---

## Langkah 2 — Web controller (salin penuh)

```bash
php artisan make:controller Web/AuthWebController --no-interaction
php artisan make:controller Web/DashboardController --no-interaction
php artisan make:controller Web/TrackWebController --no-interaction
php artisan make:controller Web/SettingWebController --no-interaction
php artisan make:controller Web/TenantWebController --resource --no-interaction
php artisan make:controller Web/CustomerWebController --resource --no-interaction
php artisan make:controller Web/OrderWebController --resource --no-interaction
php artisan make:controller Web/ServiceWebController --resource --no-interaction
php artisan make:controller Web/OperationWebController --no-interaction
```

**`Web/AuthWebController`** — `showLogin/login` (tolak non-admin/superadmin), `showRegister/register` (pakai `RegisterRequest`, transaction tenant+admin lalu login), `logout`:

```php
public function login(Request $request): RedirectResponse
{
    $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

    if (! Auth::attempt($data, $request->boolean('remember'))) {
        return back()->withErrors(['email' => 'These credentials do not match our records'])->onlyInput('email');
    }

    if (! in_array(Auth::user()->role, ['admin', 'superadmin'], true)) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()->withErrors(['email' => 'Customer accounts are managed by the laundry counter'])->onlyInput('email');
    }

    $request->session()->regenerate();
    $target = Auth::user()->role === 'superadmin' ? route('admin.dashboard') : route('dashboard');

    return redirect()->intended($target)->with('sukses', 'Signed in. Welcome back.');
}
```

**`Web/DashboardController`** — `index()`: bila superadmin redirect `admin.dashboard`; else hitung scope `tenant_id` (total, processing, ready, revenue, recent 8, ready 5, services) → `dashboard.admin`. `platform()`: total tenant/order/revenue + 8 tenant → `dashboard.platform`.

**`Web/TrackWebController`** — `index` (dukung `?invoice=` prefill), `track` (validasi resi, load customer/service/tenant/tracks, error bila tidak ketemu).

**`Web/SettingWebController`** — hanya `update`: uppercase prefix, validasi nama/prefix/regex/unique-kecuali-milik-sendiri/phone/address; update tenant + nama owner.

**`Web/TenantWebController`** — `index` (cari + withCount), `show` (stat + order + admin list), `destroy` (cascade).

**`Web/CustomerWebController`** — salin penuh (CRUD milikmu):

```php
public function index(Request $request): View
{
    $kueri = User::query()->where('tenant_id', auth()->user()->tenant_id)
        ->where('role', 'pelanggan')->withCount('orders')->withSum('orders as spent_sum', 'total_price');

    if ($request->filled('cari')) {
        $kataKunci = $request->query('cari');
        $kueri->where(fn ($sub) => $sub->where('name', 'like', "%$kataKunci%")
            ->orWhere('phone', 'like', "%$kataKunci%")->orWhere('id', $kataKunci));
    }

    return view('customers.index', ['customers' => $kueri->orderBy('name')->paginate(12)->withQueryString()]);
}

public function lookup(Request $request): JsonResponse
{
    $kataKunci = trim($request->query('q', ''));

    if (strlen($kataKunci) < 2) {
        return response()->json(['data' => []]);
    }

    $rows = User::where('tenant_id', auth()->user()->tenant_id)->where('role', 'pelanggan')
        ->where(fn ($sub) => $sub->where('name', 'like', "%$kataKunci%")->orWhere('phone', 'like', "%$kataKunci%"))
        ->withCount('orders')->orderBy('name')->limit(6)->get()
        ->map(fn ($u) => ['id' => $u->id, 'code' => $u->customerCode(), 'name' => $u->name, 'phone' => $u->phone, 'orders' => $u->orders_count]);

    return response()->json(['data' => $rows]);
}

public function store(Request $request): RedirectResponse
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
        'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
    ]);

    User::create([
        'tenant_id' => auth()->user()->tenant_id, 'name' => $data['name'],
        'phone' => $data['phone'] ?? null,
        'email' => $data['email'] ?? 'walkin-'.now()->format('YmdHis').'-'.str()->random(6).'@laundrey.local',
        'password' => Hash::make(str()->random(32)), 'role' => 'pelanggan',
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
// edit/update: findOrFail scope tenant + abort_unless pelanggan + validasi unique-ignore-id
// destroy: findOrFail scope + abort_unless + tolak bila orders()->exists()
```

**Fungsi:** semua baca tulis scope tenant; lookup dipakai form order; hapus terkunci bila ada riwayat.

**Lookup di form order** (`orders/create.blade.php`, fetch + debounce 350ms): ketik nama/HP → `GET /customers-lookup?q=` → tampil cocok + tombol Use (isi select, sembunyikan walk-in); tidak cocok → info file baru dibuat.

**`Web/OrderWebController`** — `index` (scope + cari + status), `create` (service+customer se-tenant), `store` (validasi spt API, `resolveCustomer($tenantId,...)` sama, invoice via generator+prefix tenant, transaction, redirect ke detail), `show/update/destroy` via `findOrFail` scope tenant (tanda tangan `int`), `edit` redirect ke show.

**`Web/ServiceWebController`** — index scope, store +tenant, update/destroy `int` scope tenant, destroy kunci bila dipakai, create/show/edit redirect index.

**`Web/OperationWebController`** — index scope + belum Completed + cari; `updateStatus(int)` scope tenant, tolak Completed-ganda, Completed wajib admin + auto-lunas.

**Commit:** `feat: web controller` → push boleh.

---

## Langkah 3 — Route web (salin penuh)

**Path:** `routes/web.php`

```php
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

    Route::middleware('role:admin')->group(function () {
        Route::get('/orders/{order}/invoice', [OrderWebController::class, 'invoice'])->name('orders.invoice');
        Route::get('/orders/{order}/invoice.pdf', [OrderWebController::class, 'invoicePdf'])->name('orders.invoice.pdf');
        Route::resource('orders', OrderWebController::class);
        Route::resource('services', ServiceWebController::class)->except(['create', 'show', 'edit']);
        Route::resource('customers', CustomerWebController::class);
        Route::get('/customers-lookup', [CustomerWebController::class, 'lookup'])->name('customers.lookup');
        Route::put('/settings', [SettingWebController::class, 'update'])->name('settings.update');
        Route::get('/operations', [OperationWebController::class, 'index'])->name('operations.index');
        Route::post('/operations/{order}/status', [OperationWebController::class, 'updateStatus'])->name('operations.status');
    });

    Route::middleware('auth')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::middleware('role:superadmin')->group(function () {
                Route::get('/dashboard', [DashboardController::class, 'platform'])->name('dashboard');
                Route::resource('tenants', TenantWebController::class)->only(['index', 'show', 'destroy']);
            });
        });
    });
});
```

**Commit:** gabung langkah 4.

---

## Langkah 4 — View (salin per path, lengkap di repo acuan)

Pola umum: kicker mono (`No. 01 - ...`), judul `font-display`, ledger (border-t/b + hover), angka `font-mono tabular-nums`, form label mono + input `h-10/11 rounded-md border-line-strong`.

- `tracking/index.blade.php` — hero 2 kolom (teks + box tracking/struk), box nama tenant bila hasil ada, divider, cara kerja 01-03, Start here, FAQ, CTA email. Input value pakai `$order->invoice_number ?? ($prefill ?? '')`.
- `auth/login.blade.php` — email/password + link register. `auth/register.blade.php` — laundry_name, prefix (maxlength 3, uppercase), owner, email, phone optional, password+konfirmasi; `*` merah = wajib.
- `dashboard/admin.blade.php` — greeting + tanggal, 4 stat, recent + pickup.
- `orders/index.blade.php` — filter + ledger (nama + `customerCode()`). `orders/create.blade.php` — select customer (tampilkan kode+HP) + field walk-in (JS toggle + fetch lookup debounce → tombol Use) + service/berat + estimasi live + aside perkiraan. `orders/show.blade.php` — stat, aksi bayar/hapus, tombol Print invoice kanan, riwayat.
- `orders/invoice.blade.php` — file mandiri (style sendiri, aman DOMPDF): kop tenant, tabel fix layout + lebar eksplisit, total, QR `QrCode::size(110)->generate(route('home', ['invoice' => ...]))` + tombol Print (sembunyi saat print).
- `services/index.blade.php` — form tambah + tabel (Harga/Estimasi/Order/Aksi).
- `customers/index.blade.php` (+`form/create/edit/show`) — cari, ledger + spent, form partial, detail stat + riwayat.
- `operations/index.blade.php` — kartu antrean, segmen progres, select default tahap berikut (`$steps[min($idx+1,5)]`), tombol Update.
- `dashboard/platform.blade.php`, `tenants/index|show.blade.php` — stat platform + CRUD tenant (hapus cascade).

**Commit:** `feat: seluruh halaman blade` → push.

---

## Langkah 5 — README + deploy

**Path:** `README.md` — nama, masalah, solusi, teknologi, cara jalan, **akun uji** (admin/klin/super + `password123`, resi contoh), tabel API, **cara kerja per halaman**, link deployment.

**Deploy Coolify** (tanggung jawabmu sampai LIVE):

1. **Path:** `Dockerfile` (salin penuh):

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build && rm -rf node_modules

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
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
```

Versi PHP image **wajib** ≥ syarat `composer.json` (kasus nyata: 8.3 vs butuh 8.4 → restart loop).

2. **Path:** `docker/entrypoint.sh`:

```sh
#!/bin/sh
set -e
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
exec apache2-foreground
```

3. **Path:** `.dockerignore` — `.git, node_modules, vendor, tests, storage/logs/*, framework/*, database.sqlite, .env* (kecuali example)`.
4. App Coolify: repo+branch, build pack Dockerfile, port 80. Env: `APP_*`, `DB_CONNECTION=pgsql` + kredensial, `DB_SSLMODE=require`, `LOG_CHANNEL=stderr`, `VIEW_COMPILED_PATH=/tmp/views`.
5. Restart loop → baca tab **Logs container**: `platform_check` = PHP image kekecilan; `could not find driver` = ext kurang; `Connection refused` = env DB.

**Commit:** `chore: dockerfile + entrypoint`, `docs: readme final` → push + deploy.

## Lampiran — seluruh view (salin per path)

### `resources/views/layouts/app.blade.php`

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
@if(auth()->user()->role === 'superadmin')
<p class="font-display text-lg font-bold tracking-tight">Laundrey<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">Platform console</p>
@else
<p class="font-display text-lg font-bold tracking-tight">{{ auth()->user()->tenant->name }}<span class="text-primary">.</span></p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">by Laundrey</p>
@endif
</div>
<nav class="mt-8 flex flex-1 flex-col gap-0.5 text-[13.5px]">
@if(auth()->user()->role === 'superadmin')
<a href="{{ route('admin.dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('admin.tenants.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('dashboard') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('orders.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Orders</a>
<a href="{{ route('services.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('services.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Services & pricing</a>
<a href="{{ route('customers.index') }}" class="rounded-md px-2 py-1.5 font-medium {{ request()->routeIs('customers.*') ? 'bg-paper text-ink' : 'text-ink-2 hover:bg-paper hover:text-ink' }}">Customers</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
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
<div class="min-w-0"><p class="truncate text-[13px] font-semibold">{{ auth()->user()->role === 'admin' && auth()->user()->tenant ? auth()->user()->tenant->name : auth()->user()->name }}</p>
<p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">{{ auth()->user()->role === 'admin' ? 'TENANT' : auth()->user()->role }}</p></div>
@if(auth()->user()->role === 'admin')
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
@if(auth()->user()->role === 'admin')
<form method="GET" action="{{ route('orders.index') }}" class="absolute left-1/2 hidden -translate-x-1/2 items-center lg:flex">
<input name="cari" value="{{ request('cari') }}" placeholder="Search orders…" class="h-8 w-64 border-b border-line-strong bg-transparent text-center font-mono text-xs placeholder:text-muted focus:border-primary focus:outline-none">
</form>
@endif
<span class="font-mono text-xs text-ink-2">{{ auth()->user()->name }} <span class="text-muted">/ {{ auth()->user()->role === 'admin' ? 'tenant' : auth()->user()->role }}</span></span>
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
@if(auth()->user()->role === 'superadmin')
<a href="{{ route('admin.dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
<a href="{{ route('admin.tenants.index') }}" class="whitespace-nowrap px-2 py-1">Tenants</a>
@else
<a href="{{ route('dashboard') }}" class="whitespace-nowrap px-2 py-1">Dashboard</a>
@if(in_array(auth()->user()->role, ['admin']))
<a href="{{ route('orders.index') }}" class="whitespace-nowrap px-2 py-1">Orders</a>
<a href="{{ route('services.index') }}" class="whitespace-nowrap px-2 py-1">Services</a>
<a href="{{ route('customers.index') }}" class="whitespace-nowrap px-2 py-1">Customers</a>
@endif
@if(in_array(auth()->user()->role, ['admin']))
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
@if(auth()->user()->role === 'admin' && isset($layoutTenant) && $layoutTenant)
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

### `resources/views/components/status-badge.blade.php`

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

### `resources/views/components/payment-badge.blade.php`

```blade
@props(['status'])
<span {{ $attributes->merge(['class' => 'font-mono text-[11px] font-bold uppercase tracking-widest '.($status === 'paid' ? 'text-emerald-700' : 'text-amber-700')]) }}>{{ $status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
```

### `resources/views/tracking/index.blade.php`

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

### `resources/views/auth/login.blade.php`

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

### `resources/views/auth/register.blade.php`

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

### `resources/views/dashboard/admin.blade.php`

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

### `resources/views/dashboard/platform.blade.php`

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

### `resources/views/orders/index.blade.php`

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

### `resources/views/orders/create.blade.php`

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
<p class="text-xs text-muted">Pick a registered customer, or leave walk-in and type a name. Typing checks the file live - a match reuses it, otherwise a new file is created.</p>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Service</label><select id="serviceSelect" name="service_id" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"><option value="">- pick -</option>@foreach($services as $s)<option value="{{ $s->id }}" data-price="{{ $s->price_per_unit }}" data-unit="{{ $s->unit_type }}">{{ $s->service_name }} — Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}/{{ $s->unit_type }}</option>@endforeach</select></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Weight / qty</label><input id="weightInput" type="number" step="0.1" min="0.1" name="weight_or_qty" placeholder="3.5" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
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
  if (price > 0 && weight > 0) {
    total.textContent = fmt(price * weight);
    calc.textContent = weight + ' ' + opt.dataset.unit + ' x ' + fmt(price);
  } else { total.textContent = 'Rp0'; calc.textContent = '-'; }
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
      lb.innerHTML = '<p class="py-1 font-mono text-xs text-muted">No file found - a new customer will be created.</p>';
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

### `resources/views/orders/show.blade.php`

```blade
@extends('layouts.app')
@section('title', $order->invoice_number.' - Laundrey')
@section('breadcrumb', 'Orders / Detail')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $order->invoice_number }} · {{ $order->customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $order->customer->name }}</h1>
<p class="mt-1 text-sm text-ink-2">{{ $order->service->service_name }} · {{ $order->weight_or_qty }} {{ $order->service->unit_type }} · in {{ $order->created_at->format('d M Y H:i') }}</p>
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

### `resources/views/orders/invoice.blade.php`

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
<td class="right mono">Rp{{ number_format($order->total_price, 0, ',', '.') }}</td>
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

### `resources/views/services/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Services & pricing - Laundrey')
@section('breadcrumb', 'Services & pricing')
@section('content')
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Manage rates</p>
<h1 class="mt-2 font-display text-3xl font-bold tracking-tight md:text-4xl">Price board.</h1>
<div class="mt-8 border border-line bg-white">
<div class="border-b border-line px-5 py-4">
<p class="text-sm font-semibold">Add service</p>
<form method="POST" action="{{ route('services.store') }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">@csrf
<input name="service_name" placeholder="Service name" required class="h-10 rounded-md border border-line bg-paper px-3 text-sm focus:border-ink focus:bg-white focus:outline-none">
<input name="price_per_unit" type="number" min="0" placeholder="Price Rp" required class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm focus:border-ink focus:bg-white focus:outline-none">
<select name="unit_type" class="h-10 rounded-md border border-line bg-paper px-3 text-sm"><option value="kg">per kg</option><option value="pcs">per pcs</option></select>
<input name="estimated_hours" type="number" min="1" value="24" title="Estimated hours" class="h-10 rounded-md border border-line bg-paper px-3 font-mono text-sm">
<button class="h-10 whitespace-nowrap rounded-md bg-ink px-4 text-sm font-semibold text-white hover:bg-black">Save</button>
</form>
</div>
<div class="overflow-hidden border border-line bg-white">
<div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
<thead><tr class="bg-paper text-left text-xs font-semibold uppercase tracking-wide text-ink-2">
<th class="px-5 py-2.5">Service</th><th class="px-4 py-2.5">Price</th><th class="px-4 py-2.5">Turnaround</th><th class="px-4 py-2.5">Orders</th><th class="px-5 py-2.5 text-right">Action</th></tr></thead>
<tbody>
@forelse($services as $s)
<tr class="border-t border-line hover:bg-paper/60">
<td class="px-5 py-3 font-medium">{{ $s->service_name }}</td>
<td class="px-4 py-3 font-mono text-[13px] font-bold tabular-nums">Rp{{ number_format($s->price_per_unit, 0, ',', '.') }}<span class="font-normal text-muted">/{{ $s->unit_type }}</span></td>
<td class="px-4 py-3 text-ink-2">{{ $s->estimated_hours }} hrs</td>
<td class="px-4 py-3 font-mono text-[13px] tabular-nums">{{ $s->orders_count }}</td>
<td class="px-5 py-3 text-right"><form method="POST" action="{{ route('services.destroy', $s) }}" onsubmit="return confirm('Delete Service? This action cannot be undone.')">@csrf @method('DELETE')<button class="font-mono text-[11px] uppercase tracking-widest text-muted hover:text-red-600">Delete</button></form></td>
</tr>
@empty
<tr><td colspan="5" class="px-5 py-8 text-center text-[13px] text-muted">No services yet.</td></tr>
@endforelse
</tbody>
</table></div>
</div>
</div>
<div class="mt-4 text-[13px]">{{ $services->links() }}</div>
@endsection
```

### `resources/views/customers/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Customers - Laundrey')
@section('breadcrumb', 'Customers')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
<div>
<p class="font-mono text-[11px] uppercase tracking-[0.22em] text-muted">Customer file</p>
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

### `resources/views/customers/form.blade.php`

```blade
<div class="flex flex-col gap-4">
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Name</label><input name="name" value="{{ old('name', $customer->name ?? '') }}" required class="h-11 w-full rounded-md border border-line-strong bg-white px-3 text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Phone (WhatsApp)</label><input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" placeholder="08…" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
<div><label class="mb-1.5 block font-mono text-[11px] uppercase tracking-[0.18em] text-muted">Email (optional)</label><input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="h-11 w-full rounded-md border border-line-strong bg-white px-3 font-mono text-sm focus:border-ink focus:outline-none"></div>
</div>
```

### `resources/views/customers/create.blade.php`

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

### `resources/views/customers/edit.blade.php`

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

### `resources/views/customers/show.blade.php`

```blade
@extends('layouts.app')
@section('title', $customer->name.' - Laundrey')
@section('breadcrumb', 'Customers / File')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="font-mono text-xs font-bold tracking-wide">{{ $customer->customerCode() }}</p>
<h1 class="mt-1 font-display text-3xl font-bold tracking-tight">{{ $customer->name }}</h1>
<p class="mt-1 font-mono text-[13px] text-ink-2">{{ $customer->phone ?? 'no phone' }} · {{ $customer->email }}</p>
</div>
<div class="flex gap-2">
<a href="{{ route('customers.edit', $customer) }}" class="h-9 rounded-md border border-ink px-3 text-[13px] font-medium leading-8 hover:bg-ink hover:text-white">Edit</a>
<form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer file?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete</button></form>
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
<p class="border-b border-line py-8 text-center text-[13px] text-muted">No loads on file yet.</p>
@endforelse
</div>
<div class="mt-4 text-[13px]">{{ $orders->links() }}</div>
@endsection
```

### `resources/views/operations/index.blade.php`

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

### `resources/views/tenants/index.blade.php`

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

### `resources/views/tenants/show.blade.php`

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
<form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this shop and ALL its data?')">@csrf @method('DELETE')<button class="h-9 rounded-md px-3 text-[13px] text-muted hover:text-red-600">Delete shop</button></form>
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

## Checklist akhir

- [ ] `migrate:fresh --seed` hijau, register laundry → order walk-in → tahap → tracking → struk
- [ ] Customer: duplikat HP dipakai ulang, hapus terkunci bila berorder; tenant lain 404
- [ ] LIVE + link di README + video individu
