# 03 — Anggota 3: Frontend, Customer CRUD & Deploy

**Peran:** antarmuka + rilis. **CRUD Customers**, halaman auth, tracking publik, invoice print + QR, Dockerfile + deploy, README.

**Prasyarat:** branch Anggota 1+2 merge ke `main`. Branch `anggota-3-frontend` dari `main` terbaru. Package: `composer require simplesoftwareio/simple-qrcode`.

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

## Checklist akhir

- [ ] `migrate:fresh --seed` hijau, register laundry → order walk-in → tahap → tracking → struk
- [ ] Customer: duplikat HP dipakai ulang, hapus terkunci bila berorder; tenant lain 404
- [ ] LIVE + link di README + video individu
