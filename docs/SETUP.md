# Panduan Setup MyKaunsel

## Keperluan Sistem

- PHP >= 8.2
- Composer 2.x
- Node.js >= 18 dan npm
- MySQL 8.x (atau SQLite untuk pembangunan tempatan)
- Git

## 1. Clone Repositori

```bash
git clone <url-repo>
cd mykaunsel
```

## 2. Setup Backend Laravel (`mykaunsel-api`)

```bash
cd mykaunsel-api
composer install
cp .env.example .env
php artisan key:generate
```

Kemas kini medan pangkalan data dalam `.env` mengikut pilihan anda:

**Pilihan A — SQLite (paling mudah untuk pembangunan tempatan)**

```env
DB_CONNECTION=sqlite
```

Cipta fail pangkalan data kosong:

```bash
touch database/database.sqlite
```

**Pilihan B — MySQL**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mykaunsel
DB_USERNAME=root
DB_PASSWORD=
```

## 3. Jalankan Migration dan Seeder

```bash
php artisan migrate --seed
```

Ini akan mencipta semua jadual dan mengisi data demo (organisasi, kaunselor,
pengguna, dan rekod simulasi LKM). Akaun demo yang tersedia selepas seeding:

| Peranan | E-mel | Kata Laluan |
|---|---|---|
| Platform Admin | `admin@mykaunsel.com` | `password` |
| Org Admin (UMPSA) | `afif@umpsa.edu.my` | `password` |
| Org Admin (Klinik Damai) | `admin@klinikdamai.com` | `password` |
| Kaunselor (UMPSA) | `ahmad.zulkarnain@umpsa.edu.my` | `password` |
| Pelajar (UMPSA) | `amalina.zulkifli@adab.umpsa.edu.my` | `password` |

## 4. Pasang Dependency Frontend & Bina Aset

```bash
npm install
npm run build
```

Untuk pembangunan dengan hot-reload:

```bash
npm run dev
```

## 5. Jalankan Development Server

```bash
php artisan serve
```

Portal boleh diakses di `http://127.0.0.1:8000`.

## Nota

- Aplikasi Flutter (`mykaunsel-app/`) belum bermula pembangunan; folder ini
  akan diisi apabila kerja mudah alih dimulakan.
- Semua kata laluan akaun demo ialah `password`.
