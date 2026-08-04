# API Contract — Modul Booking (Sarepundi)

> **Sumber kebenaran**: dokumen ini ditulis dengan membaca langsung kode backend
> (`backend/app/...`, `backend/routes/api.php`, `backend/database/migrations/...`)
> per **2026-08-04**, BUKAN disalin dari `PRD-Platform-Booking-Villa.md` atau
> `CLAUDE.md`. Kedua dokumen itu sudah banyak menyimpang dari kode aktual — lihat
> bagian [10. Perbedaan dengan PRD/CLAUDE.md](#10-perbedaan-dengan-prdclaudemd)
> di bagian bawah.
>
> Setiap contoh JSON di dokumen ini diambil dari respons aktual (`php artisan
> tinker` / `curl` ke server lokal yang menjalankan kode saat ini), bukan
> dikira-kira. Bagian yang saya tidak bisa pastikan dari kode ditandai
> **⚠️ PERLU KONFIRMASI**.

---

## 0. Untuk Developer Mobile (Flutter) — baca ini dulu

### ⚠️ PERLU KONFIRMASI — Autentikasi API ini BUKAN token-based

Backend ini pakai **Laravel Sanctum mode SPA (session cookie + CSRF)**, bukan
Sanctum Personal Access Token (bearer token). Bukti dari kode:

- `bootstrap/app.php` memanggil `$middleware->statefulApi()`.
- `AuthController::login()`/`register()` memakai `Auth::attempt()` +
  `$request->session()->regenerate()` — pola sesi berbasis cookie.
- Tidak ada satupun pemanggilan `createToken()`/`PersonalAccessToken` atau
  pemeriksaan header `Authorization: Bearer` di seluruh `app/` — sudah dicek
  dengan grep menyeluruh, hasilnya nihil.
- `config/cors.php` & `config/sanctum.php` dikonfigurasi untuk domain frontend
  web (`FRONTEND_URLS`, `SANCTUM_STATEFUL_DOMAINS`), bukan untuk klien mobile.

**Konsekuensi**: Flutter tidak bisa langsung `POST /api/login` lalu simpan
`access_token` seperti API REST pada umumnya. Supaya app mobile bisa login,
salah satu dari ini harus terjadi lebih dulu (belum ada di kode, jadi ini
keputusan yang perlu diambil tim sebelum development mobile jalan):

1. **Backend menambahkan Sanctum token auth** — endpoint baru yang
   mengembalikan `plainTextToken` setelah login, dipakai lewat header
   `Authorization: Bearer <token>` untuk semua request berikutnya. Ini pola
   paling umum untuk REST API + mobile.
2. Flutter mengimplementasikan **cookie jar** (mis. paket `dio_cookie_manager`)
   dan mengulang alur SPA: `GET /sanctum/csrf-cookie` → simpan cookie
   `XSRF-TOKEN` & session → kirim ulang di setiap request. Rapuh untuk mobile
   (background refresh, logout otomatis OS, dll) dan bukan pola yang lazim.

Dokumen ini menulis endpoint apa adanya (sesuai kode), tapi soal strategi auth
mobile **harus diputuskan dulu** sebelum implementasi Flutter dimulai.

### ⚠️ PERLU KONFIRMASI — Redirect sukses/gagal pembayaran mengarah ke web, bukan deep link

`PaymentController::store()` mengirim `success_redirect_url` &
`failure_redirect_url` ke Xendit dalam bentuk URL **frontend web** (Nuxt):
```
{FRONTEND_URL}/user/bookings/{id}?payment=success
{FRONTEND_URL}/user/bookings/{id}?payment=failed
```
Kalau mobile app membuka `invoice_url` di WebView/in-app browser, setelah bayar
Xendit akan redirect ke halaman web itu, **bukan** otomatis kembali ke app.
Perlu diputuskan: apakah backend perlu param/endpoint terpisah untuk
menghasilkan deep-link (`sarepundi://booking/{id}?payment=success`), atau
mobile app mendeteksi pola URL tsb di WebView dan menutup sendiri.

### ⚠️ PERLU KONFIRMASI — Login Google hanya untuk web

`SocialAuthController` (di `routes/web.php`, bukan `routes/api.php`) adalah
alur redirect browser klasik (redirect ke Google → callback → set session
cookie → redirect ke frontend web). Tidak ada endpoint untuk menukar Google ID
Token (hasil native Google Sign-In di Android/iOS) dengan sesi/token Sarepundi.
Kalau mobile butuh "Login with Google", ini perlu endpoint baru.

### Fitur di PRD lama yang TIDAK ada di kode (jangan didesain di mobile dulu)

- **Wishlist / simpan villa favorit** — disebut di PRD §3.1, tidak ada tabel,
  endpoint, atau kolom apapun di kode. Halaman dashboard user di frontend web
  bahkan masih menampilkan placeholder teks "Wishlist akan tampil di sini".
- **Approve/reject booking oleh mitra** — dihapus total dari kode (lihat
  bagian 10). Jangan bangun layar "booking masuk, terima/tolak" di mobile.

---

## 1. Konvensi Umum

| Hal | Aturan |
|---|---|
| Base URL | `{APP_URL}/api/...` (mis. `http://localhost:8000/api`) |
| Format tanggal (request & response) | `YYYY-MM-DD` (ISO), field datetime pakai ISO 8601 (`2026-08-04T15:01:31+00:00`) |
| Mata uang | Integer Rupiah, tanpa desimal (contoh `4500000` = Rp4.500.000) — **tidak ada field currency**, semua asumsi IDR |
| Auth header non-GET | Sanctum SPA butuh header `X-XSRF-TOKEN` dari cookie `XSRF-TOKEN` untuk request selain GET/HEAD — lihat peringatan mobile di atas |
| Content negotiation | Kirim header `Accept: application/json` di semua request supaya error Laravel selalu balik JSON, bukan HTML |
| Amplop respons list/detail | Selalu dibungkus `{"data": ...}` (single object atau array). Endpoint `index` yang di-`paginate()` (Admin bookings) menambah `links`/`meta` standar Laravel |

---

## 2. Status Booking

### 2.1 Enum yang AKTIF diproduksi kode saat ini

```
pending_payment → dikonfirmasi → checked_in → selesai
                        ↓
                 dibatalkan_user
```

| Status | Arti | Siapa yang men-trigger |
|---|---|---|
| `pending_payment` | Booking dibuat, menunggu pembayaran | User (`POST /api/bookings`) |
| `dikonfirmasi` | Pembayaran sukses — booking otomatis terkonfirmasi, **tidak ada langkah approval mitra** | Sistem, via webhook Xendit (`XenditWebhookController`) saat status `PAID` |
| `checked_in` | Tanggal check-in sudah lewat | Scheduled command `bookings:advance-completed-stays` (jalan harian) |
| `selesai` | Tanggal check-out sudah lewat | Command yang sama di atas |
| `dibatalkan_user` | User membatalkan booking yang `dikonfirmasi` | User (`POST /api/bookings/{id}/cancel`) |

**Transisi valid** (tidak ada jalur lain yang diproduksi kode):
- `pending_payment` → `dikonfirmasi` (webhook PAID)
- `dikonfirmasi` → `checked_in` (job harian, `check_in_date <= now()`)
- `checked_in` → `selesai` (job harian, `check_out_date <= now()`)
- `dikonfirmasi` → `checked_in` **atau langsung** → `selesai` dalam sekali jalan job kalau `check_out_date` sudah lewat juga (job memproses kedua transisi di query terpisah tapi berurutan, jadi booking lama yang belum sempat diproses bisa loncat ke `selesai` langsung tanpa pernah terlihat di `checked_in` oleh API)
- `dikonfirmasi` → `dibatalkan_user` (aksi user, hanya kalau statusnya masih `dikonfirmasi`)
- `pending_payment` **tidak pernah otomatis batal** — tidak ada job/timeout yang membatalkan booking pending_payment yang tidak dibayar-bayar. ⚠️ **PERLU KONFIRMASI**: apakah ini bug/kekurangan, atau memang disengaja (booking pending menumpuk selamanya kalau user tidak bayar dan tidak cancel — tapi tidak ada endpoint cancel untuk status `pending_payment` juga, lihat kebijakan cancel di bagian 3).

### 2.2 Enum yang MASIH ADA di skema DB tapi TIDAK PERNAH diproduksi kode baru

Kolom `status` (migration awal) & `cancellation_reason` masih mendefinisikan
nilai-nilai berikut untuk kompatibilitas mundur data lama, tapi **tidak akan
pernah muncul** di booking yang dibuat lewat alur saat ini:

- Status: `menunggu_konfirmasi`, `dibatalkan_mitra`
- Cancellation reason: `mitra_reject`, `mitra_timeout`, `user_cancel_pending`

Mobile app **tidak perlu** menangani UI untuk nilai-nilai ini (mis. tombol
"terima/tolak booking" di sisi mitra), tapi kalau membuat `enum`/`switch` di
Dart untuk field `status`, aman untuk menyertakannya sebagai fallback
non-crash (data lampau di database production bisa saja masih memilikinya).

⚠️ **PERLU KONFIRMASI**: `Admin\BookingController::index()` (endpoint
`GET /api/admin/bookings`) **masih memvalidasi** query param `status` dengan
`Rule::in([...])` yang mencantumkan `menunggu_konfirmasi` & `dibatalkan_mitra`
sebagai nilai valid — artinya endpoint ini tidak akan menolak filter dengan
nilai itu (hanya saja hasilnya pasti kosong untuk booking baru). Tidak
mempengaruhi mobile app pencari (`user`), hanya relevan kalau ada dashboard
admin di mobile.

### 2.3 Status Payment (tabel `payments`, field `payment.status` di response)

| Status | Arti |
|---|---|
| `pending` | Invoice Xendit dibuat, menunggu pembayaran |
| `success` | Pembayaran sukses (di-set oleh webhook saat `PAID`) |
| `failed` | Invoice expired/gagal (webhook `EXPIRED`/`FAILED`) |
| `refunded` | Sudah direfund penuh |
| `partial_refunded` | Sudah direfund sebagian |

### 2.4 Status Refund (tabel `refunds`, tidak diekspos langsung di endpoint booking manapun — hanya field `refund_amount`/`refund_percentage` di Booking yang publik)

`pending`, `succeeded`, `failed`. ⚠️ **PERLU KONFIRMASI**: tidak ada endpoint
publik/user untuk membaca detail record `refunds` (mis. `xendit_refund_id`,
status refund yang gagal) — user hanya melihat `refund_amount` &
`refund_percentage` di object Booking. Kalau mobile perlu status refund yang
lebih rinci (mis. "refund gagal, hubungi CS"), belum ada endpoint untuk itu.

### 2.5 Status Payout (tabel `payouts`) — tidak relevan untuk app user/pencari, hanya untuk mitra

`pending`, `completed`, `failed`.

---

## 3. Business Rules (dari kode, bukan dari dokumen lama)

### 3.1 Komisi Platform

- Default **10%** dari `total_price` (`MitraProfile::DEFAULT_COMMISSION_RATE`).
- ⚠️ **Beda dari CLAUDE.md**: admin BISA override komisi per mitra lewat
  `PATCH /api/admin/mitras/{mitra}/commission`, disimpan di kolom
  `mitra_profiles.commission_rate`. Kalau kolom ini `null`, dipakai default
  10%. Artinya komisi **tidak selalu 10%/90%** — tergantung mitra.
- `commission_amount` & `mitra_payout_amount` dihitung **sekali** saat
  availability-check/booking dibuat (`{Category}AvailabilityService::evaluate()`)
  dan disimpan langsung di baris `bookings`. Tidak pernah dihitung ulang di
  tempat lain, termasuk saat payout — `PayoutService` hanya menjumlahkan
  `mitra_payout_amount` yang sudah tersimpan.
- Dihitung dari `total_price` (**setelah** diskon kupon), bukan `subtotal`.

### 3.2 Kupon/Diskon

Tidak ada di PRD/CLAUDE.md sama sekali — fitur baru. `CouponService::resolve()`
dipanggil oleh semua 6 `{Category}AvailabilityService`:
- Kupon dicocokkan case-insensitive.
- `discount_type`: `percentage` (dihitung dari `subtotal`) atau `fixed`.
- Diskon di-cap supaya tidak melebihi `subtotal` (`total_price` tidak pernah
  negatif).
- Kupon tidak valid/kadaluarsa → `422` dengan error di field `coupon_code`.

### 3.3 Kebijakan Cancellation & Refund oleh User

Satu-satunya jalur pembatalan (mitra tidak bisa membatalkan sama sekali):

| Status booking saat cancel | Syarat | Refund |
|---|---|---|
| `dikonfirmasi` | `check_in_date - hari ini >= 2 hari` (H-2 atau lebih) | **85%** dari `total_price` |
| `dikonfirmasi` | `check_in_date - hari ini < 2 hari` | **0%** (tidak ada refund) |
| Selain `dikonfirmasi` | — | **Tidak bisa cancel** (`403`, lihat `BookingPolicy::cancel()`) |

- `refund_percentage` & `refund_amount` dihitung & disimpan saat cancel.
- `cancellation_reason` selalu `user_cancel_confirmed` untuk pembatalan baru.
- Refund ke Xendit hanya dipanggil kalau `refund_amount > 0` — pembatalan
  H-2 yang refund-nya 0% **tidak membuat record `Refund`** dan **tidak
  memanggil API Xendit sama sekali**.
- Kalau API refund Xendit gagal, booking **tetap** berubah jadi
  `dibatalkan_user` (pembatalan tidak diblok oleh kegagalan refund) — dicatat
  sebagai `Refund` dengan `status: 'failed'` untuk ditindaklanjuti admin.

### 3.4 Payout ke Mitra

- Trigger: booking berstatus `selesai` DAN `payout_id` masih `null`.
- Dijalankan otomatis via scheduler: **tanggal 1 & 15, jam 02:00** (cron
  `0 2 1,15 * *`) — ini **sudah final**, bukan lagi "misal" seperti yang
  tertulis di `CLAUDE.md`.
- Juga bisa dipicu manual oleh admin: `POST /api/admin/payouts/run`.
- Satu `Payout` = akumulasi SEMUA booking `selesai` milik satu mitra (lintas
  6 kategori listing) yang belum masuk payout manapun.
- Payout gagal (`status: failed`) bisa di-retry manual: `POST /api/admin/payouts/{payout}/retry`.

### 3.5 Voucher & Receipt PDF (fitur baru, tidak ada di PRD/CLAUDE.md sama sekali)

- Setelah pembayaran sukses, sistem mengirim email ke user berisi **2 lampiran
  PDF terpisah**: Voucher (untuk ditunjukkan ke mitra) dan Receipt (bukti
  bayar).
- Kedua dokumen ini juga bisa diunduh kapan saja lewat endpoint
  `GET /api/bookings/{id}/voucher` dan `/receipt` (lihat bagian 5.3).
- Hanya bisa diakses kalau `status !== 'pending_payment'` (lihat
  `BookingPolicy::viewDocument()`).

### 3.6 Auto-transition Terjadwal (scheduled commands)

| Command | Jadwal | Efek ke booking |
|---|---|---|
| `bookings:advance-completed-stays` | Harian | `dikonfirmasi` → `checked_in` (jika `check_in_date` lewat), lalu `dikonfirmasi`/`checked_in` → `selesai` (jika `check_out_date` lewat) |
| `bookings:send-checkin-reminders` | Harian jam 09:00 | Kirim notifikasi H-1 (tidak mengubah status) |
| `payouts:run` | Tanggal 1 & 15 jam 02:00 | Membuat `Payout` untuk booking `selesai` |

Tidak ada job apapun yang mengubah status `pending_payment` (tidak ada
auto-cancel akibat timeout — job `AutoCancelExpiredBookings` yang dulu ada di
PRD sudah **dihapus total** dari kode).

---

## 4. Daftar Kategori Listing (bookable_type)

Booking bersifat polimorfik (`bookable_type` + `bookable_id`), berlaku untuk 6
kategori. `bookable_type` dalam **request** booking pakai string pendek ini:

| Kunci `bookable_type` | Model Eloquent | Pola harga | Endpoint availability |
|---|---|---|---|
| `villa` | `App\Models\Villa` | Per-tanggal (kalender, bisa custom price/min-stay) x malam | `GET /api/villas/{slug}/availability` |
| `glamping` | `App\Models\Glamping` | Flat `base_price` x malam | `GET /api/glampings/{slug}/availability` |
| `homestay` | `App\Models\Homestay` | Flat `base_price` x malam | `GET /api/homestays/{slug}/availability` |
| `apartment` | `App\Models\Apartment` | Flat `base_price` x malam | `GET /api/apartments/{slug}/availability` |
| `gathering_venue` | `App\Models\GatheringVenue` | Per slot per hari (bukan per malam) | `GET /api/gathering-venues/{slug}/availability` (lihat catatan khusus di 5.2.4) |
| `transport` | `App\Models\Transport` | Flat harga/hari, tergantung `with_driver` | `GET /api/transports/{slug}/availability` |

Field `bookable.type` di **response** Booking sama persis dengan kunci di atas
(match expression di `BookingResource`/`AdminBookingResource`/
`MitraBookingResource`, ketiganya identik).

⚠️ **PERLU KONFIRMASI / kemungkinan bug ditemukan saat riset**:
`ReviewResource` (`GET .../reviews`) punya `match()` terpisah untuk
`reviewable.type` yang **belum diupdate** untuk 2 kategori terbaru:

```php
// app/Http/Resources/ReviewResource.php
'type' => match (true) {
    $this->reviewable instanceof Homestay => 'homestay',
    $this->reviewable instanceof GatheringVenue => 'gathering_venue',
    $this->reviewable instanceof Transport => 'transport',
    default => 'villa',   // <- Glamping & Apartment jatuh ke sini!
},
```

Review milik booking Glamping atau Apartment akan tampil dengan
`reviewable.type: "villa"` yang salah. Ini murni soal label kategori di objek
`reviewable` dalam response review — tidak mempengaruhi data lain. Perlu
diperbaiki di backend sebelum mobile mengandalkan field ini untuk kategori
Glamping/Apartment.

---

## 5. Endpoint

### 5.1 Auth (ringkas — dibutuhkan sebelum semua endpoint booking user)

| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/register` | Guest | Body: `name, email, phone?, password, password_confirmation, role (user\|mitra), business_name? (required jika role=mitra), business_address?` |
| POST | `/api/login` | Guest | Body: `email, password` |
| POST | `/api/logout` | Sesi aktif | — |
| GET | `/api/me` | Sesi aktif | User yang sedang login |

Response `UserResource` (register/login/me):
```json
{
  "data": {
    "id": 1,
    "name": "Dewi Anggraini",
    "email": "user1@sarepundi.demo",
    "phone": "081234567890",
    "avatar": null,
    "status": "active",
    "role": "user",
    "mitra_profile": null
  }
}
```

### 5.2 Publik — Browse & Availability Check (tidak perlu auth)

Pola yang sama berlaku di 6 kategori (`villas`, `glampings`, `homestays`,
`apartments`, `gathering-venues`, `transports`) kecuali disebutkan lain:

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/{kategori}` | List published, query: `q, city, guests\|capacity, min_price, max_price, facility_ids[]` (nama query param persis, lihat tiap Public Controller) |
| GET | `/api/{kategori}/{slug}` | Detail satu listing |
| GET | `/api/{kategori}/{slug}/availability` | Cek ketersediaan + harga (lihat 5.2.1–5.2.4 untuk parameter tiap pola) |
| GET | `/api/{kategori}/{slug}/reviews` | List review (paginated, 10/halaman) |

#### 5.2.1 Availability — pola "date range" (villa, glamping, homestay, apartment)

`GET /api/villas/{slug}/availability?check_in_date=2026-09-10&check_out_date=2026-09-13&guest_count=2&coupon_code=HEMAT15`

Query: `check_in_date` (wajib, `>= hari ini`), `check_out_date` (wajib,
`> check_in_date`), `guest_count` (opsional, default `1` di server kalau
tidak dikirim), `coupon_code` (opsional).

Response (contoh nyata):
```json
{
  "data": {
    "available": true,
    "reason": null,
    "nights": 3,
    "subtotal": 4500000,
    "coupon_id": null,
    "discount_amount": 0,
    "total_price": 4500000,
    "commission_amount": 450000,
    "mitra_payout_amount": 4050000
  }
}
```
Kalau tidak tersedia, semua field angka `0`/`null` dan `reason` berisi pesan
Indonesia siap-tampil (mis. `"Tanggal yang dipilih sudah dipesan."`,
`"Kapasitas villa maksimal 4 tamu."`, `"Minimum menginap 3 malam untuk
tanggal check-in ini."` — khusus Villa yang punya kalender custom).

#### 5.2.2 Availability — pola "day range + opsi sopir" (transport)

Sama seperti 5.2.1, ditambah query **wajib** `with_driver` (`0`/`1`/`true`/`false`).
Kalau kendaraan tidak menyediakan opsi yang diminta, `reason`:
`"Kendaraan ini tidak tersedia dengan sopir."` atau `"...hanya tersedia dengan sopir."`

#### 5.2.3 Availability — pola "slot per hari" (gathering_venue)

⚠️ Berbeda bentuk total dari kategori lain — **tidak menerima/menghitung
kupon atau harga** di endpoint ini, hanya daftar slot + status ketersediaan.

`GET /api/gathering-venues/{slug}/availability?date=2026-09-20`

Response (contoh nyata):
```json
{
  "data": {
    "date": "2026-09-20",
    "slots": [
      {"id": 1, "name": "Sesi Pagi", "start_time": "08:00", "end_time": "12:00", "price": 2500000, "available": true},
      {"id": 2, "name": "Sesi Siang", "start_time": "13:00", "end_time": "17:00", "price": 2800000, "available": true},
      {"id": 3, "name": "Sesi Malam", "start_time": "18:00", "end_time": "22:00", "price": 3200000, "available": true}
    ]
  }
}
```
Harga per slot sudah final (tidak ada perhitungan diskon di endpoint ini —
kupon untuk gathering venue baru dihitung ulang di `POST /api/bookings` saat
booking benar-benar dibuat, lewat `gathering_venue_slot_id`).

### 5.3 User — Booking Lifecycle (butuh auth + role `user`)

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/bookings` | List booking milik user login |
| POST | `/api/bookings` | Buat booking baru (`status: pending_payment`) |
| GET | `/api/bookings/{id}` | Detail booking (harus milik user login) |
| POST | `/api/bookings/{id}/pay` | Buat/ambil invoice Xendit |
| POST | `/api/bookings/{id}/cancel` | Batalkan booking (hanya jika `dikonfirmasi`) |
| POST | `/api/bookings/{id}/review` | Beri review (hanya jika `selesai`) |
| GET | `/api/bookings/{id}/voucher` | Unduh PDF voucher |
| GET | `/api/bookings/{id}/receipt` | Unduh PDF receipt |

#### 5.3.1 `POST /api/bookings`

Body (bentuk berbeda tergantung `bookable_type`):

```jsonc
// villa / glamping / homestay / apartment
{
  "bookable_type": "villa",
  "bookable_id": 1,
  "check_in_date": "2026-09-10",
  "check_out_date": "2026-09-13",
  "guest_count": 2,
  "coupon_code": "HEMAT15"        // opsional
}

// gathering_venue — pakai gathering_venue_slot_id, TANPA check_out_date
{
  "bookable_type": "gathering_venue",
  "bookable_id": 1,
  "gathering_venue_slot_id": 2,
  "check_in_date": "2026-09-20",
  "guest_count": 150,
  "coupon_code": null
}

// transport — wajib with_driver
{
  "bookable_type": "transport",
  "bookable_id": 1,
  "check_in_date": "2026-09-10",
  "check_out_date": "2026-09-12",
  "guest_count": 4,
  "with_driver": false
}
```

Validasi (`StoreBookingRequest`):
- `bookable_type`: wajib, salah satu dari `villa|glamping|homestay|apartment|gathering_venue|transport`.
- `bookable_id`: wajib, harus ada di tabel kategori terkait.
- `check_in_date`: wajib, `>= hari ini`.
- `check_out_date`: wajib & `> check_in_date` **kecuali** `bookable_type === 'gathering_venue'` (field ini tidak dipakai untuk gathering venue).
- `gathering_venue_slot_id`: wajib **hanya** jika `bookable_type === 'gathering_venue'`.
- `with_driver`: wajib (boolean) **hanya** jika `bookable_type === 'transport'`.
- `guest_count`: wajib, integer `>= 1`.
- `coupon_code`: opsional.

Response sukses — `201 Created`, body `BookingResource` (lihat contoh di 5.3.3).
Kalau availability re-check di server menemukan sudah tidak tersedia (race
condition, mis. slot baru saja diambil orang lain): `422` dengan
`errors.check_in_date[0]` berisi alasan (sama seperti `reason` di endpoint
availability).

#### 5.3.2 `POST /api/bookings/{id}/pay`

Tidak ada body. Response `201`:
```json
{ "data": { "invoice_url": "https://checkout-staging.xendit.co/web/inv_xxx" } }
```
Kalau dipanggil ulang untuk booking yang sudah punya invoice `pending`,
response `200` (bukan `201`) dengan `invoice_url` yang sama (tidak membuat
invoice baru). Kalau `booking.status !== 'pending_payment'`: `422` —
`{"message": "Booking ini tidak sedang menunggu pembayaran."}`.

#### 5.3.3 `GET /api/bookings/{id}` — contoh response nyata

Booking villa yang sudah `dikonfirmasi`:
```json
{
  "data": {
    "id": 2,
    "booking_code": "BKCTQ1RCEVMW",
    "check_in_date": "2026-08-19",
    "check_out_date": "2026-08-23",
    "guest_count": 5,
    "subtotal": 6000000,
    "discount_amount": 0,
    "coupon_code": null,
    "total_price": 6000000,
    "commission_amount": 600000,
    "mitra_payout_amount": 5400000,
    "status": "dikonfirmasi",
    "mitra_confirmation_deadline": null,
    "cancellation_reason": null,
    "cancelled_at": null,
    "refund_amount": null,
    "refund_percentage": null,
    "payment": {
      "status": "success",
      "invoice_url": null,
      "paid_at": "2026-08-04T11:01:31+00:00"
    },
    "review": null,
    "bookable": {
      "type": "villa",
      "id": 1,
      "name": "Villa Sunset Seminyak",
      "slug": "villa-sunset-seminyak",
      "city": "Bali",
      "primary_image": "http://localhost:8000/storage/villa-images/XMP3yjEqYvwFndC1SpNf.jpg"
    },
    "slot": null,
    "transport_with_driver": null,
    "created_at": "2026-08-04T15:01:31+00:00"
  }
}
```
Catatan: `payment.invoice_url` **sengaja** `null` kecuali `payment.status ===
"pending"` (lihat `BookingResource` — begitu sukses/gagal, link invoice tidak
relevan lagi ditampilkan). `mitra_confirmation_deadline` selalu `null` untuk
semua booking baru (field peninggalan alur lama, lihat bagian 10).

Booking gathering_venue (field `slot` terisi, `check_in_date ===
check_out_date`):
```json
{
  "data": {
    "id": 13,
    "booking_code": "BKUCQVCNKMOK",
    "check_in_date": "2026-08-29",
    "check_out_date": "2026-08-29",
    "guest_count": 150,
    "total_price": 2500000,
    "status": "dikonfirmasi",
    "bookable": { "type": "gathering_venue", "id": 1, "name": "Aula Grand Ballroom Jakarta", "slug": "aula-grand-ballroom-jakarta", "city": "Jakarta", "primary_image": "http://localhost:8000/storage/gathering-venue-images/yGQyliFjA3FlHdl0Onuz.jpg" },
    "slot": { "id": 1, "name": "Sesi Pagi", "start_time": "08:00", "end_time": "12:00" },
    "transport_with_driver": null
  }
}
```

Booking yang sudah dibatalkan & direfund 85%:
```json
{
  "data": {
    "status": "dibatalkan_user",
    "cancellation_reason": "user_cancel_confirmed",
    "cancelled_at": "2026-08-03T15:01:31+00:00",
    "refund_amount": 4590000,
    "refund_percentage": 85,
    "payment": { "status": "refunded", "invoice_url": null, "paid_at": "2026-08-01T15:01:31+00:00" }
  }
}
```

#### 5.3.4 `POST /api/bookings/{id}/cancel`

Tanpa body. Sukses → `200`, body `BookingResource` dengan status & field
refund yang sudah terupdate (lihat contoh di atas). Kalau status booking
bukan `dikonfirmasi` → `403` (via Policy, pesan default Laravel "This action
is unauthorized.").

#### 5.3.5 `POST /api/bookings/{id}/review`

Body: `{ "rating": 5, "comment": "Villa sangat bersih..." }` (`rating` wajib
1–5, `comment` opsional maks 2000 karakter). Hanya bisa jika
`booking.status === 'selesai'` dan belum pernah direview (`422` jika salah
satu tidak terpenuhi). Response `201`, body `ReviewResource` — **ingat bug
`reviewable.type` untuk Glamping/Apartment di bagian 4**.

#### 5.3.6 `GET /api/bookings/{id}/voucher` & `/receipt`

Tidak ada body/query. Response: **file PDF** (`Content-Type:
application/pdf`, `Content-Disposition: attachment`), bukan JSON. Ditolak
`403` jika `booking.status === 'pending_payment'` (lihat 3.5).

### 5.4 Mitra — Booking (read-only, butuh auth + role `mitra`)

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/mitra/bookings` | List booking di semua listing milik mitra ybs, lintas 6 kategori. Query: `status` (opsional, salah satu dari `pending_payment\|dikonfirmasi\|dibatalkan_user\|checked_in\|selesai` — **tidak termasuk** nilai lama) |

Tidak ada endpoint terima/tolak booking (dihapus, lihat bagian 10). Response
`MitraBookingResource` — mirip `BookingResource` tapi tanpa `commission_amount`
(mitra tidak perlu tahu potongan platform, hanya `mitra_payout_amount`) dan
ada field `guest: {name, email, phone}`.

### 5.5 Admin — Booking Monitoring (butuh auth + role `admin`)

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/admin/bookings` | List semua booking, **paginated** (20/halaman). Query: `status` (lihat catatan enum lama di 2.2), `search` (cocok ke `booking_code`, nama listing, nama/email user) |

Response `AdminBookingResource` (dibungkus struktur pagination Laravel
standar: `data`, `links`, `meta`).

---

## 6. Format Error Standar

Backend ini **tidak** punya custom Exception Handler (`bootstrap/app.php`
hanya mengatur `shouldRenderJsonWhen` untuk path `api/*`) — semua error
mengikuti format default Laravel:

| Situasi | HTTP Status | Body |
|---|---|---|
| Validasi gagal (`FormRequest`) | `422` | `{"message": "The check in date field is required. (and 1 more error)", "errors": {"check_in_date": ["The check in date field is required."], "check_out_date": ["..."]}}` |
| Belum login (route butuh `auth:sanctum`) | `401` | `{"message": "Unauthenticated."}` |
| Policy/otorisasi gagal (`$this->authorize()`) | `403` | `{"message": "This action is unauthorized."}` (pesan default Laravel, generik) |
| `abort(403, 'pesan custom')` (mis. akun disuspend) | `403` | `{"message": "Akun Anda telah disuspend. Hubungi admin platform."}` |
| `abort_unless(..., 422, 'pesan custom')` | `422` | `{"message": "Booking ini tidak sedang menunggu pembayaran."}` |
| Record tidak ditemukan (`findOrFail`/route model binding) | `404` | Mode **debug ON** (lokal): trace lengkap + `exception`, `file`, `line`. Mode **debug OFF** (production, `APP_DEBUG=false`): hanya `{"message": "No query results for model [App\\Models\\Villa]."}` |
| Webhook token salah | `401` | `{"message": "Invalid callback token."}` |

⚠️ **PERLU KONFIRMASI**: pesan validasi (`errors.*`) dan pesan 403 default
("This action is unauthorized.") masih **berbahasa Inggris** — tidak
konsisten dengan pesan custom lain yang sudah Bahasa Indonesia (mis. pesan di
`reason` availability-check, atau `abort_unless` di beberapa controller).
Mobile app perlu menangani campuran bahasa ini kalau mau menampilkan pesan
error langsung ke user, atau membuat mapping pesan sendiri di sisi klien.

---

## 7. Ringkasan Field Booking (semua field yang bisa muncul)

| Field | Tipe | Selalu ada? | Catatan |
|---|---|---|---|
| `id` | int | ya | |
| `booking_code` | string | ya | Format `BK` + 4 digit tahun+bulan+tanggal + 4 karakter acak (mis. `BK20260904A1B2`) — dibuat di `BookingController::generateBookingCode()` |
| `check_in_date` | string (date) | ya | Untuk `gathering_venue`, sama dengan `check_out_date` |
| `check_out_date` | string (date) | ya | |
| `guest_count` | int | ya (kecuali di `AdminBookingResource`, field ini **tidak ada**) | |
| `subtotal` | int | ya (kecuali `AdminBookingResource`/`MitraBookingResource` — ada) | Harga sebelum diskon kupon |
| `discount_amount` | int | ya | `0` jika tanpa kupon |
| `coupon_code` | string\|null | hanya jika relasi `coupon` di-load | |
| `total_price` | int | ya | `subtotal - discount_amount` |
| `commission_amount` | int | hanya di `BookingResource`/`AdminBookingResource` | **Tidak ada** di `MitraBookingResource` (mitra tidak perlu tahu) |
| `mitra_payout_amount` | int | ya | |
| `status` | string enum | ya | Lihat bagian 2 |
| `mitra_confirmation_deadline` | string (ISO datetime)\|null | ya | **Selalu `null`** untuk booking baru — sisa kolom dari alur lama, lihat bagian 10 |
| `cancellation_reason` | string\|null | ya | |
| `cancelled_at` | string (ISO datetime)\|null | hanya `BookingResource` | |
| `refund_amount` | int\|null | ya | |
| `refund_percentage` | int\|null | ya (kecuali `MitraBookingResource` — tidak ada) | |
| `payment` | object\|null | hanya jika relasi `latestPayment` di-load | `{status, invoice_url, paid_at}` |
| `review` | object\|null | hanya `BookingResource`, hanya jika relasi di-load | `ReviewResource` |
| `bookable` | object | ya | `{type, id, name, slug?, city?, primary_image?}` — field opsional tidak ada di `AdminBookingResource`/`MitraBookingResource` |
| `slot` | object\|null | hanya jika relasi di-load | Hanya terisi untuk `gathering_venue` |
| `transport_with_driver` | bool\|null | ya | Hanya relevan untuk `transport` |
| `guest` | object | hanya `MitraBookingResource` | `{name, email, phone}` — identitas user, untuk mitra |
| `mitra` | object | hanya `AdminBookingResource` | `{business_name}` |
| `user` | object | hanya `AdminBookingResource` | `{name, email}` |
| `payment_status` | string\|null | hanya `AdminBookingResource` | Bukan object, cuma string status |
| `created_at` | string (ISO datetime) | ya | |

---

## 8. Autentikasi & Role — ringkas

- 3 role: `user`, `mitra`, `admin` (Spatie Permission, satu role per akun).
- Endpoint booking untuk pencari selalu di bawah middleware
  `role:user` — akun `mitra`/`admin` akan mendapat `403` kalau memanggil
  `POST /api/bookings` dkk.
- Akun `mitra` baru berstatus `pending` sampai admin approve — TIDAK ada
  pemblokiran booking terkait ini (mitra tidak pernah menyentuh alur booking
  langsung), tapi listing mitra `pending` tidak akan muncul di endpoint
  publik (`publiclyVisible()` scope mengecek `mitraProfile.status ===
  'approved'` DAN `user.status === 'active'`).
- Akun `suspended` (kolom `users.status`) diblokir di 2 tempat: saat login
  (`AuthController`) dan di setiap request terautentikasi berikutnya
  (middleware `EnsureUserIsActive`) — jadi sesi yang sedang aktif pun akan
  ter-block begitu admin men-suspend akun tsb.

---

## 9. Field yang TIDAK ADA (jangan diasumsikan ada)

Supaya tidak menebak-nebak saat integrasi mobile, ini yang **eksplisit tidak
ada** di kode meski mungkin terdengar wajar untuk fitur booking:

- Tidak ada field `currency` — semua asumsi IDR.
- Tidak ada endpoint "cancel pending_payment booking" — sekali dibuat,
  booking `pending_payment` hanya bisa lanjut bayar atau dibiarkan
  menggantung (tidak ada tombol/endpoint cancel untuk status ini, beda dari
  PRD lama yang menyebut refund 100% untuk pembatalan sebelum konfirmasi
  mitra — skenario itu sudah tidak ada relevansinya sama sekali).
- Tidak ada endpoint search/filter booking milik user sendiri (`GET
  /api/bookings` selalu mengembalikan semua booking user tsb tanpa
  query/filter/pagination).
- Tidak ada webhook/callback status pembayaran selain lewat Xendit (tidak ada
  polling endpoint "cek status pembayaran terbaru" — mobile harus refresh
  `GET /api/bookings/{id}` sendiri, atau reload setelah WebView redirect).
- Tidak ada rate limiting khusus yang terlihat di kode untuk endpoint booking
  (di luar default Laravel, kalau ada, tidak dicek eksplisit dalam riset ini)
  — ⚠️ **PERLU KONFIRMASI** kalau relevan untuk kapasitas server.

---

## 10. Perbedaan dengan PRD/CLAUDE.md

Lihat ringkasan lengkap di percakapan/laporan penutup. Poin teknis paling
kritikal untuk kontrak API ini:

1. Alur konfirmasi mitra manual (PRD §4.1, status `menunggu_konfirmasi` →
   accept/reject dalam 24 jam) **sudah dihapus total** dari kode. `CLAUDE.md`
   sudah diupdate untuk ini, PRD **belum**.
2. PRD hanya mengenal 1 kategori (Villa). Kode sekarang punya 6 kategori
   polimorfik. PRD/CLAUDE.md sama sekali tidak menyebut Glamping, Apartment,
   Gathering Venue (venue+slot), atau Transport.
3. Komisi platform PRD/CLAUDE.md: flat 10%/90%. Kode: 10% default TAPI bisa
   di-override per mitra oleh admin.
4. Sistem kupon/diskon (subtotal, discount_amount, coupon_id) sama sekali
   tidak ada di PRD/CLAUDE.md.
5. Voucher & Receipt PDF (email + endpoint download) sama sekali tidak ada di
   PRD/CLAUDE.md — fitur baru di luar dokumen manapun.
6. `AutoCancelExpiredBookings` (command untuk auto-cancel booking yang tidak
   direspon mitra dalam 24 jam) yang disebut di PRD §6/§7 **sudah dihapus**
   dari kode, filenya tidak ada lagi.
