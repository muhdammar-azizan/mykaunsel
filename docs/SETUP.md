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

## 2. Setup Backend Laravel (`mykaunsel-web`)

```bash
cd mykaunsel-web
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

### Pembangunan (local)

```bash
php artisan migrate --seed
```

Ini akan mencipta semua jadual dan mengisi data demo (organisasi, kaunselor,
pengguna, dan rekod simulasi LKM). Seeder demo **hanya berjalan pada persekitaran
`local` dan `testing`**; di tempat lain `PlatformSeeder` berhenti dengan ralat dan
tidak mencipta apa-apa. Akaun demo yang tersedia selepas seeding:

| Peranan | E-mel | Kata Laluan |
|---|---|---|
| Platform Admin | dicipta oleh `database/seeders/PlatformSeeder.php` (untuk local sahaja) | lihat fail itu |
| Org Admin (UMPSA) | `afif@umpsa.edu.my` | `password` |
| Org Admin (Klinik Damai) | `admin@klinikdamai.com` | `password` |
| Kaunselor (UMPSA) | `ahmad.zulkarnain@umpsa.edu.my` | `password` |
| Pelajar (UMPSA) | `amalina.zulkifli@adab.umpsa.edu.my` | `password` |

### Pelayan sebenar (staging / produksi)

Jangan jalankan `--seed`. Semua seeder yang mencipta data demo (akaun berkata laluan diketahui, organisasi, kaunselor, aduan, harga contoh dan lain-lain) **berhenti dengan ralat yang jelas sebelum mencipta apa-apa** jika persekitaran bukan `local` atau `testing` (`Database\Seeders\Concerns\RefusesOutsideDevelopment`), dan `DatabaseSeeder` tidak memanggil satu pun di luar persekitaran itu. Ujian `SeederGuardTest` memastikan seeder baharu tidak boleh terlupa dipagar. Seeder asas tanpa akaun (tiada buat masa ini) akan disenaraikan dalam `DatabaseSeeder::BASE_SEEDERS`. Jalankan migration sahaja, kemudian cipta Platform Admin
pertama dari terminal:

```bash
php artisan migrate --force
php artisan platform:create-admin
```

Arahan ini bertanya nama, e-mel dan kata laluan (kata laluan ditaip pada input
tersembunyi dan **tidak boleh** diberikan sebagai argumen, kerana ia akan kekal dalam
sejarah shell). Kata laluan mengikut polisi yang sama seperti halaman Account
(sekurang-kurangnya 8 aksara, huruf besar dan kecil, nombor dan simbol). E-mel dianggap
disahkan. Arahan ini ditolak jika sudah ada Platform Admin aktif (gunakan
`--force` untuk menambah satu lagi; admin lain dijemput dari Settings > Platform Admins),
dan ditolak jika e-mel itu sudah ada akaun (ia tidak pernah mengubah akaun sedia ada).
Tindakan ini direkod dalam audit log sebagai tindakan sistem (`admin.created_from_console`),
tanpa e-mel dan kata laluan.

Sebelum setiap deploy, jalankan seluruh suite ujian pada MySQL (ujian biasa guna SQLite, yang berbeza dalam perkara kecil tetapi nyata): `bash tool/test-on-mysql.sh`. Lihat [`MYSQL_TESTING.md`](MYSQL_TESTING.md).

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

## 6. E-mel sebenar semasa ujian

Secara asal `MAIL_MAILER=log`: e-mel (sahkan e-mel, tetapkan semula kata laluan, tempahan, jemputan) hanya ditulis ke `storage/logs`, tidak dihantar. Untuk menguji aliran sebenar tanpa domain, guna akaun Gmail awak sendiri:

1. Hidupkan pengesahan dua langkah pada akaun Google, kemudian cipta **App Password** (Google Account > Security > App passwords). Kata laluan 16 huruf itu bukan kata laluan Gmail biasa.
2. Dalam `.env` (fail ini tidak masuk git; **jangan** tampal kata laluan di tempat lain):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=alamat-anda@gmail.com
MAIL_PASSWORD=app-password-16-huruf
MAIL_FROM_ADDRESS=alamat-anda@gmail.com
MAIL_FROM_NAME="MyKaunsel"
```

   `MAIL_FROM_ADDRESS` mesti sama dengan akaun Gmail itu. Gmail menulis semula pengirim lain, dan itulah yang menyebabkan e-mel masuk spam. Dihantar daripada Gmail sendiri, e-mel biasanya masuk peti masuk.
3. **Pautan dalam e-mel guna `APP_URL`.** Ia mesti alamat yang boleh dibuka dari telefon, bukan `localhost`. Dua cara:
   - **Wi-Fi yang sama:** `php artisan serve --host=0.0.0.0`, dan `APP_URL=http://ALAMAT-IP-KOMPUTER:8000` (lihat `ipconfig`). Android mungkin menyekat `http`; jika ya, guna cara seterusnya.
   - **Terowong (disyorkan, dapat `https`):** contohnya `cloudflared tunnel --url http://localhost:8000` atau `ngrok http 8000`, kemudian `APP_URL=https://alamat-terowong`. Aplikasi pula dibina dengan alamat yang sama sebagai alamat API.
4. Selepas menukar `.env`: `php artisan config:clear`.
5. **E-mel tempahan dan lain-lain dihantar melalui baris gilir.** Hidupkan `php artisan queue:work` (e-mel sahkan akaun dan tetapkan semula kata laluan dihantar terus dan tidak perlukannya).
6. Semak dengan `php artisan mail:test alamat@contoh.com`. Ia menyatakan mailer, pengirim dan `APP_URL`, memberi amaran jika tidak sesuai, dan menghantar satu e-mel ujian.

Had Gmail kira-kira 500 e-mel sehari; cukup untuk ujian. Sebelum lancar, guna domain sendiri dengan SPF dan DKIM (perkhidmatan seperti Brevo, Mailgun atau Amazon SES).

## Nota

- Aplikasi Flutter (`mykaunsel-app/`) belum bermula pembangunan; folder ini
  akan diisi apabila kerja mudah alih dimulakan.
- Kata laluan akaun demo (local sahaja) diketahui umum; jangan sekali-kali guna seeder demo pada pelayan sebenar.
