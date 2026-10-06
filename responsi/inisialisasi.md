# 00 — Inisialisasi Proyek & Aturan Kelompok

Dokumen ini dibaca semua anggota sebelum mulai. Tujuannya satu: environment sama, repo sama, alur git sama.

## 1. Spesifikasi dependency (wajib sama)

| Tools | Versi minimal |
| ----- | ------------- |
| PHP | ≥ 8.4 (`php -v`) |
| Composer | ≥ 2.7 (`composer --version`) |
| Node.js | ≥ 20 LTS (`node -v`) |
| MySQL / MariaDB | sesuai lab (atau SQLite untuk dev cepat) |
| Git | wajib |
| Laravel | 13 (`php artisan --version`) |

Kenapa disamakan: beda versi PHP/Composer bikin `composer.lock` konflik dan error platform (kasus nyata: vendor butuh PHP ≥ 8.4 tapi runtime 8.3 → container restart terus).

## 2. Proyek awal (sudah push, jangan ulang)

Skeleton Laravel 13 + Sanctum (`php artisan install:api`) sudah ada di repo. Clone lalu siapkan `.env` sendiri (jangan commit `.env`):

```bash
# via HTTPS
git clone https://github.com/avriyyy/praktikum_pemweb2-responsi-a5.git
# atau via SSH (perlu SSH key terdaftar di GitHub)
git clone git@github.com:avriyyy/praktikum_pemweb2-responsi-a5.git
cd praktikum_pemweb2-responsi-a5
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Database development pakai **SQLite dulu** (nol setup, gampang diganti ke MySQL/Postgres nanti):

```env
DB_CONNECTION=sqlite
```

```bash
php artisan migrate
composer run dev   # http://localhost:8000
```

File `database/database.sqlite` otomatis dipakai. Nanti ganti ke MySQL/Postgres cukup ubah blok `DB_*` di `.env` + `php artisan config:clear`.

## 3. Repo baru & branch per anggota

Repo ini (`laundrey-final`) **jangan dipakai** — buat repository GitHub baru, misal `laundrey-responsi`.

```bash
git init
git add .
git commit -m "chore: inisialisasi laravel 13 + sanctum"
git branch -M main
git remote add origin <url-repo-baru>
git push -u origin main
```

Branch kerja (jangan coding di `main`):

```bash
git checkout -b anggota-1-auth-master
git checkout -b anggota-2-transaksi
git checkout -b anggota-3-frontend
```

## 4. Aturan commit & push

Format pesan: `tipe: penjelasan singkat`

| Tipe | Pakai untuk |
| ---- | ----------- |
| `feat` | fitur baru |
| `fix` | perbaikan bug |
| `chore` | setup, config, dependency |
| `docs` | dokumentasi |

Contoh: `feat: tambah CRUD service + API resource`, `fix: invoice unik per tenant`.

**Kapan push:** setiap 1 langkah besar selesai DAN sudah lolos cek di bawah. Jangan menumpuk 10 file tanpa commit.

Cek wajib sebelum push:

```bash
php artisan migrate:fresh --seed
vendor/bin/pint --dirty --format agent
php artisan test --compact   # bila ada test
```

Merge ke `main` via Pull Request, 1 approve teman. Konflik `composer.lock`: jangan edit manual, jalankan `composer update --lock` lalu commit hasilnya.

## 5. Pembagian kerja (detail di file masing-masing)

| File | Anggota | Fokus | CRUD miliknya |
| ---- | ------- | ----- | ------------- |
| `01-anggota-1-auth-master.md` | 1 | Auth Sanctum, tenant, user, service | **Services** |
| `02-anggota-2-transaksi-tracking.md` | 2 | Order, track, invoice otomatis | **Orders + OrderTracks** |
| `03-anggota-3-frontend-deploy.md` | 3 | Blade, web controller, deploy | **Customers** |

Urutan kerjakan: **Anggota 1 dulu** (fondasi), lalu 2 dan 3 paralel. Tiap file di bawah berisi: langkah berurutan, path file, kode lengkap, fungsi singkat, dan saran commit/push.
