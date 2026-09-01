# Seni Bina MyKaunsel

## Gambaran Keseluruhan

MyKaunsel ialah platform SaaS multi-tenant untuk pengurusan tempahan sesi
kaunseling di Malaysia. Platform ini membenarkan pelbagai jenis organisasi
(universiti, korporat, klinik) mendaftar sebagai penyewa (tenant) berasingan,
setiap satu dengan kaunselor, ahli, dan tetapan tempahan tersendiri, di
samping membenarkan kaunselor bebas beroperasi terus di bawah organisasi
maya "Platform". Seorang pengguna boleh mempunyai keahlian (membership) di
lebih daripada satu organisasi, dan konteks organisasi semasa (current org
context) ditentukan semasa sesi log masuk melalui pemilih konteks.

## Tech Stack

| Lapisan | Teknologi |
|---|---|
| Backend & Portal Web | Laravel 12, Blade, Livewire, Tailwind CSS |
| Auth Scaffolding | Laravel Breeze (Blade stack) |
| Pangkalan Data | MySQL (SQLite untuk pembangunan tempatan) |
| Aplikasi Mudah Alih | Flutter (belum bermula) |

## Struktur Folder

```
mykaunsel/
├── mykaunsel-api/      Projek Laravel (backend + portal web)
├── mykaunsel-app/      Aplikasi Flutter (kosong buat masa ini)
├── docs/                Dokumentasi projek
├── README.md
├── LICENSE
└── .gitignore
```

### `mykaunsel-api/app/`

| Folder | Fungsi |
|---|---|
| `Http/Controllers/Auth` | Controller pengesahan (dijana oleh Breeze) |
| `Http/Controllers/Organization` | Controller papan pemuka & tetapan organisasi |
| `Http/Controllers/Counselor` | Controller papan pemuka kaunselor |
| `Http/Controllers/Platform` | Controller papan pemuka Platform Admin |
| `Http/Controllers/Api` | Controller API untuk aplikasi Flutter (akan datang) |
| `Http/Middleware` | Middleware konteks organisasi, status keahlian, dan peranan |
| `Http/Requests` | Form Request untuk pengesahan input, disusun mengikut modul |
| `Models` | Model Eloquent bagi semua jadual pangkalan data |
| `Policies` | Kelas kebenaran (authorization) untuk model |
| `Services` | Logik perniagaan (pengesahan LKM, domain, keahlian, ketersediaan) |
| `Console/Commands` | Tugas berjadual (scheduled tasks) |
| `Enums` | PHP Enum untuk nilai tetap yang digunakan merentasi aplikasi |

### `mykaunsel-api/resources/views/`

| Folder | Fungsi |
|---|---|
| `layouts` | Layout portal (`app`), layout dua lajur untuk auth (`auth`), layout landing page (`guest`) |
| `auth` | Log masuk, daftar, lupa kata laluan, pemilih konteks |
| `organizations` | Pendaftaran organisasi (`signup`) dan papan pemuka Org Admin (`dashboard`) |
| `counselors` | Pendaftaran kaunselor (`signup`), papan pemuka kaunselor (`dashboard`), carian awam (`search`) |
| `platform` | Papan pemuka Platform Admin |
| `components` | Komponen Blade boleh guna semula (input, select, button, badge status, modal) |
| `emails` | Templat e-mel |

## Middleware

| Alias | Kelas | Fungsi |
|---|---|---|
| `org.context` | `EnsureOrgContext` | Memastikan `current_org_id` dan `current_role` wujud dalam sesi; jika tiada, arah ke pemilih konteks |
| `membership.active` | `CheckMembershipStatus` | Menyekat akses jika keahlian pengguna dalam organisasi semasa bukan `active` |
| `role` | `RequireRole` | Menyemak `current_role` sesi sepadan dengan peranan yang diperlukan bagi laluan (contoh: `role:org_admin`) |
| `org.status` | `CheckOrgStatus` | Menyekat akses jika organisasi semasa berstatus `pending` atau `suspended` |

## Senarai Enum

| Enum | Nilai |
|---|---|
| `OrgType` | `university`, `corporate`, `clinic`, `platform` |
| `AccessModel` | `closed`, `open` |
| `MembershipRole` | `student`, `staff`, `employee`, `counselor`, `org_admin`, `platform_admin` |
| `MembershipStatus` | `active`, `suspended`, `alumni`, `notice_period`, `offboarded` |
| `OrgStatus` | `pending`, `active`, `suspended`, `rejected` |
| `VerificationType` | `org_assigned`, `platform_verified` |
| `VerificationStatus` | `pending`, `approved`, `rejected`, `suspended` |
| `BookingMode` | `slot_based`, `request_based` |
| `BookingStatus` | `confirmed`, `completed`, `cancelled`, `no_show` |
| `SessionMode` | `online`, `physical` |
| `CalendarEntryType` | `available_slot`, `personal_block` |
| `JoinMethod` | `domain`, `invite_code`, `member_list`, `approval` |
| `JoinSource` | `domain`, `invite_code`, `member_list`, `manual` |
| `Attendance` | `attended`, `no_show`, `cancelled` |
| `FollowUpStatus` | `none`, `follow_up_needed`, `referred` |
| `ComplaintStatus` | `open`, `under_review`, `resolved`, `dismissed` |
| `BookingRequestStatus` | `pending`, `approved`, `rejected`, `expired` |
| `DomainMatchType` | `exact`, `wildcard` |
