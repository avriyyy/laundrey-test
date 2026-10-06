# Laundrey

Multi-tenant laundry ops: tiap kedai daftar dengan kode resi 3 huruf sendiri (misal `QWP`), data terisolasi per tenant. Pelanggan lacak resi publik, admin kelola transaksi, tarif, file pelanggan, dan tahap pengerjaan.

## Cara kerja multi-tenant

- Registrasi (`/register` atau `POST /api/v1/auth/register`): nama laundry + prefix 3 huruf kapital (unik global) + akun admin. Contoh resi: `QWP-20261006-001`.
- Semua data (services, orders, customers) terfilter `tenant_id`. Cross-tenant return 404.
- Prefix bisa diganti di Settings, hanya berlaku untuk resi baru.
- Tracking publik by nomor resi penuh (unik global).

## Permasalahan

UMKM laundry masih catat manual: pelanggan tanya status berulang, tahapan cucian sulit dilacak, nota hilang dan salah hitung.

## Solusi

- Admin tunggal: counter mencatat order, tahap, tarif, dan file pelanggan
- Pelanggan tanpa login: dilayani by nama/HP, dilacak by resi publik
- CRUD pelanggan oleh admin: cari by nama/HP/ID, riwayat + total belanja, cegah duplikat via cek live di form order
- Kalkulasi harga otomatis (berat × tarif)
- Alur status: Received → Washing → Drying → Ironing → Ready → Completed
- Setiap perubahan status tercatat di `order_tracks`
- Relasi Eloquent: One-to-Many (tenant→users/services/orders, user→orders, order→tracks) + Many-to-Many (promo↔service via `promo_service`)
- Promo diskon: kode + persen + window tanggal, attach ke layanan, order pakai `promo_code` → total terpangkas otomatis
- Desain referensi Linear.app: minimalis, whitespace lega, border subtle, satu aksen indigo `#5E6AD2`

## Teknologi

Laravel 13, PHP ≥ 8.4, MySQL/MariaDB (dev default SQLite), Eloquent ORM, Sanctum Bearer Token, Blade, Vite.

## Cara menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
# MySQL: sesuaikan DB_* di .env. SQLite: biarkan default.
php artisan migrate --seed
php artisan serve
```

Buka `http://localhost:8000`.

## Akun pengujian

| Role | Email | Password |
| ---- | ----- | -------- |
| Admin demo | admin@laundrey.test via `/login` (tenant Laundrey, prefix INV) | password123 |
| Admin demo 2 | klin@laundrey.test via `/login` (tenant Klin Laundry, prefix KLN) | password123 |
| Platform | super@laundrey.test via `/login` (monitor + hapus tenant di `/tenants`) | password123 |

Daftar laundry baru via `/register`. Login pelanggan dinonaktifkan: file pelanggan dikelola counter. Data contoh `budi@laundrey.test` tetap ada sebagai file pelanggan.

Contoh resi: `INV-20261006-001` (lihat `orders` setelah seed).

## API Documentation

Base: `/api/v1`. Header wajib `Accept: application/json`. Auth: `Authorization: Bearer <token>`.

| Method | Endpoint | Keterangan | Auth | Role |
| ------ | -------- | ---------- | ---- | ---- |
| POST | /api/v1/auth/register | Registrasi laundry (name+prefix+admin) | No | Public |
| POST | /api/v1/auth/login | Login & token (admin only) | No | Public |
| POST | /api/v1/auth/logout | Logout (hapus token aktif) | Yes | Admin |
| GET | /api/v1/services | Daftar layanan (cari=`cari`, paginasi `per_halaman`) | Yes | Admin |
| POST | /api/v1/services | Tambah layanan | Yes | Admin |
| PUT | /api/v1/services/{id} | Update layanan | Yes | Admin |
| DELETE | /api/v1/services/{id} | Hapus layanan | Yes | Admin |
| GET | /api/v1/orders | Daftar transaksi (filter `cari`, `status`, `payment_status`, `per_halaman`) | Yes | Admin |
| POST | /api/v1/orders | Buat transaksi (user_id terdaftar ATAU customer_name/phone walk-in) + invoice + track awal | Yes | Admin |
| GET | /api/v1/orders/{id} | Detail + tracks | Yes | Admin |
| PUT | /api/v1/orders/{id} | Update transaksi | Yes | Admin |
| POST | /api/v1/orders/{id}/tracks | Update status + catat track | Yes | Admin |
| GET | /api/v1/track/{invoice} | Tracking publik | No | Public |
| GET | /api/v1/promos | Daftar promo + layanan (cari=`cari`) | Yes | Admin |
| POST | /api/v1/promos | Buat promo + attach layanan | Yes | Admin |
| GET | /api/v1/promos/{id} | Detail promo | Yes | Admin |
| DELETE | /api/v1/promos/{id} | Hapus promo | Yes | Admin |

Customer CRUD + live lookup (`/customers`, `/customers-lookup`) tersedia di web untuk admin.

Response sukses: `{sukses:true, pesan, data}`. Error konsisten: 401 token, 403 peran, 404 resi, 422 validasi (`galat`).

## Cara kerja per halaman

Aktor: **tamu** (tanpa login), **tenant** (pemilik kedai, login `/login`), **platform** (login yang sama, role superadmin).

### `/` — Landing + lacak resi (tamu)
- Ketik nomor resi → struk digital: identitas, total, status tahap 01–06 + riwayat waktu.
- Kolom kiri menampilkan box nama laundry pemilik resi saat hasil ditemukan.
- Baca cara kerja 3 langkah, info mulai (pemilik kedai vs pelanggan), FAQ, dan tombol Report via Email (mailto dummy).
- Tanpa sidebar, tanpa login.

### `/register` — Daftar kedai (tamu)
- Isi nama laundry, prefix 3 huruf kapital (unik global, auto-uppercase), nama owner, email, HP, password.
- Sekali submit: tenant + akun admin dibuat dan langsung login. Resi berikutnya memakai prefix tersebut.

### `/login` — Satu pintu masuk
- Tenant dan platform login di sini, redirect otomatis sesuai role (tenant → `/dashboard`, superadmin → `/admin/dashboard`).
- Akun customer ditolak dengan pesan yang jelas.

### `/dashboard` — Dashboard tenant
- Strip angka: masuk, dikerjakan, siap diambil, kas masuk (lunas).
- Order terbaru (klik ke detail) + daftar tunggu pickup.
- Sidebar: Orders, Services, Customers, Operations, Settings (modal), theme toggle, kartu laundry + keluar.

### `/orders` — Buku order
- Cari invoice/nama + saring tahap. Baris order klik ke detail.

### `/orders/create` — Catat order
- Pilih customer terdaftar (nama + ID + HP) atau walk-in: ketik nama/HP, sistem cek file live (cocok → pakai file existing satu klik; tidak cocok → file baru otomatis).
- Pilih layanan, berat, status bayar. Estimasi total live. Simpan → lompat ke detail order.

### `/orders/{id}` — Detail + struk
- Total, tahap, aksi kasir (tandai lunas/belum, hapus), riwayat status.
- Tombol Print invoice (rata kanan) → halaman struk: kop kedai, tabel, total, QR scan-to-track (buka landing dengan resi terisi).

### `/services` — Tarif
- Tambah/ubah/hapus layanan (harga, satuan, estimasi). Hapus dikunci bila dipakai order.

### `/customers` — File pelanggan
- Cari nama/HP/ID (ID format `CUST-001`, penanda nama kembar).
- Tambah/ubah/hapus (hapus dikunci bila ada riwayat). Detail: total load, total lunas, tunggakan, riwayat order.

### `/operations` — Antrean kerja
- Urut menunggu terlama. Dropdown default = tahap berikutnya, tinggal klik Update + catatan opsional.

### `/admin/*` — Platform (superadmin)
- `/admin/dashboard`: total kedai, order, revenue + daftar kedai.
- `/admin/tenants`: cari, detail stat + order per kedai, hapus kedai beserta datanya.

### Umum
- Toggle tema gelap/terang (topbar tamu, sidebar login; default gelap, diingat browser).
- Notifikasi sukses/error bisa ditutup (×).
- Invoice: `PREFIX-YYYYMMDD-urut` harian per tenant, unik global.

## Struktur

- `database/migrations`: tenants, users/services/orders (+tenant_id), order_tracks
- `app/Models`: Tenant, User (tenant), Service (tenant), Order (tenant, prefix invoice), OrderTrack
- `app/Models`: User (HasApiTokens, orders, orderTracks), Service (orders), Order (customer, service, tracks), OrderTrack (order, updater)
- `app/Http/Requests`: Login, Store/Update Service, Store/Update Order, StoreTrack
- `app/Http/Resources`: Service, Order (nested customer/service/tracks), OrderTrack
- `app/Http/Controllers/Api/V1`: Auth, Service, Order, Track
- `app/Http/Middleware/EnsureRole.php` → alias `role`
- `app/Http/Controllers/Web`: Auth (session), Dashboard, Order, Service, Operation, Track, Customer (CRUD + lookup)
- `resources/views`: layouts/app, tracking, auth, dashboard, orders, services, operations, customers

## Deployment

Set `APP_URL`, `DB_*` MySQL, `php artisan migrate --force`, `npm run build`. Link deployment: (isi setelah deploy).
