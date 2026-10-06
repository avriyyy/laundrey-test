# Laundrey

Sistem pelacakan status cucian & layanan laundry untuk UMKM. Pelanggan lacak resi mandiri, admin kelola transaksi, tarif, dan tahap pengerjaan.

## Permasalahan

UMKM laundry masih catat manual: pelanggan tanya status berulang, tahapan cucian sulit dilacak, nota hilang dan salah hitung.

## Solusi

- Dua peran: admin/admin dan pelanggan
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
| Kasir | admin@laundrey.test | password123 |
| Pelanggan | budi@laundrey.test | password123 |

Contoh resi: `INV-20261006-001` (lihat `orders` setelah seed).

## API Documentation

Base: `/api/v1`. Header wajib `Accept: application/json`. Auth: `Authorization: Bearer <token>`.

| Method | Endpoint | Keterangan | Auth | Role |
| ------ | -------- | ---------- | ---- | ---- |
| POST | /api/v1/auth/register | Registrasi pelanggan | No | Public |
| POST | /api/v1/auth/login | Login & token | No | Public |
| POST | /api/v1/auth/logout | Logout (hapus token aktif) | Yes | All |
| GET | /api/v1/services | Daftar layanan (cari=`cari`, paginasi `per_halaman`) | Yes | All |
| POST | /api/v1/services | Tambah layanan | Yes | Kasir/Admin |
| PUT | /api/v1/services/{id} | Update layanan | Yes | Kasir/Admin |
| DELETE | /api/v1/services/{id} | Hapus layanan | Yes | Kasir/Admin |
| GET | /api/v1/orders | Daftar transaksi (filter `cari`, `status`, `payment_status`, `per_halaman`) | Yes | All (pelanggan hanya miliknya) |
| POST | /api/v1/orders | Buat transaksi (user_id terdaftar ATAU customer_name/phone walk-in) + invoice + track awal | Yes | Admin |
| GET | /api/v1/orders/{id} | Detail + tracks | Yes | All (miliknya / staf) |
| PUT | /api/v1/orders/{id} | Update transaksi | Yes | Kasir/Admin |
| POST | /api/v1/orders/{id}/tracks | Update status + catat track | Yes | Kasir/Admin |
| GET | /api/v1/track/{invoice} | Tracking publik | No | Public |

Response sukses: `{sukses:true, pesan, data}`. Error konsisten: 401 token, 403 peran, 404 resi, 422 validasi (`galat`).

## Struktur (ikut pola modul 2–5)

- `database/migrations`: users (+role,phone), services, orders, order_tracks
- `app/Models`: User (HasApiTokens, orders, orderTracks), Service (orders), Order (customer, service, tracks), OrderTrack (order, updater)
- `app/Http/Requests`: Register, Login, Store/Update Service, Store/Update Order, StoreTrack
- `app/Http/Resources`: Service, Order (nested customer/service/tracks), OrderTrack
- `app/Http/Controllers/Api/V1`: Auth, Service, Order, Track
- `app/Http/Middleware/EnsureRole.php` → alias `role`
- `app/Http/Controllers/Web`: Auth (session), Dashboard, Order, Service, Operation, Track
- `resources/views`: layouts/app, tracking, auth, dashboard, orders, services, operations

## Deployment

Set `APP_URL`, `DB_*` MySQL, `php artisan migrate --force`, `npm run build`. Link deployment: (isi setelah deploy).
