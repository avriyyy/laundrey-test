# Laundrey

Sistem pelacakan status cucian & layanan laundry untuk UMKM. Pelanggan lacak resi mandiri, admin kelola transaksi, tarif, dan tahap pengerjaan.

## Permasalahan

UMKM laundry masih catat manual: pelanggan tanya status berulang, tahapan cucian sulit dilacak, nota hilang dan salah hitung.

## Solusi

- Admin tunggal: counter mencatat order, tahap, tarif, dan file pelanggan
- Pelanggan tanpa login: dilayani by nama/HP, dilacak by resi publik
- CRUD pelanggan oleh admin: cari by nama/HP/ID, riwayat + total belanja, cegah duplikat via cek live di form order
- Kalkulasi harga otomatis (berat × tarif)
- Alur status: Received → Washing → Drying → Ironing → Ready → Completed
- Setiap perubahan status tercatat di `order_tracks`
- Tracking publik via nomor resi, dashboard per peran
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
| Admin | admin@laundrey.test | password123 |

Login pelanggan dinonaktifkan: file pelanggan dikelola counter. Data contoh `budi@laundrey.test` tetap ada sebagai file pelanggan.

Contoh resi: `INV-20261006-001` (lihat `orders` setelah seed).

## API Documentation

Base: `/api/v1`. Header wajib `Accept: application/json`. Auth: `Authorization: Bearer <token>`.

| Method | Endpoint | Keterangan | Auth | Role |
| ------ | -------- | ---------- | ---- | ---- |
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

Customer CRUD + live lookup (`/customers`, `/customers-lookup`) tersedia di web untuk admin.

Response sukses: `{sukses:true, pesan, data}`. Error konsisten: 401 token, 403 peran, 404 resi, 422 validasi (`galat`).

## Struktur

- `database/migrations`: users (+role,phone), services, orders, order_tracks
- `app/Models`: User (HasApiTokens, orders, orderTracks), Service (orders), Order (customer, service, tracks), OrderTrack (order, updater)
- `app/Http/Requests`: Login, Store/Update Service, Store/Update Order, StoreTrack
- `app/Http/Resources`: Service, Order (nested customer/service/tracks), OrderTrack
- `app/Http/Controllers/Api/V1`: Auth, Service, Order, Track
- `app/Http/Middleware/EnsureRole.php` → alias `role`
- `app/Http/Controllers/Web`: Auth (session), Dashboard, Order, Service, Operation, Track, Customer (CRUD + lookup)
- `resources/views`: layouts/app, tracking, auth, dashboard, orders, services, operations, customers

## Deployment

Set `APP_URL`, `DB_*` MySQL, `php artisan migrate --force`, `npm run build`. Link deployment: (isi setelah deploy).
