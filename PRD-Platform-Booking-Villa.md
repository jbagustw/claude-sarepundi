# PRD: Sarepundi — Platform Booking Villa, Glamping, Homestay, Apartment, Lokasi Gathering & Transport

> **Versi ini menggantikan seluruhnya versi PRD sebelumnya.** Ditulis
> berdasarkan pembacaan langsung kode backend & frontend aktual per
> **2026-08-05**, bukan berdasarkan rencana awal — banyak keputusan bisnis
> berubah sejak draft pertama (lihat [Riwayat Perubahan](#riwayat-perubahan-dari-versi-sebelumnya)
> di bagian akhir dokumen). Kalau ada bagian kode yang ambigu atau
> sepertinya belum lengkap, ditandai eksplisit **⚠️ PERLU KONFIRMASI** —
> jangan dianggap pasti.
>
> Untuk detail teknis modul booking (endpoint, request/response JSON, status
> enum) dan narasi alur langkah-demi-langkah, dokumen ini **tidak
> mengulang** isinya — rujuk ke:
> - [`API_CONTRACT.md`](./API_CONTRACT.md) — kontrak API lengkap, ditulis
>   untuk kebutuhan integrasi mobile Flutter.
> - [`BOOKING_FLOW.md`](./BOOKING_FLOW.md) — narasi alur booking end-to-end
>   dalam Bahasa Indonesia.

---

## 1. Ringkasan Produk

Sarepundi adalah marketplace booking online yang mempertemukan pencari
akomodasi/layanan (**user**) dengan pemilik/pengelola properti atau layanan
(**mitra**), dikelola oleh **admin** platform. Model bisnis: komisi per
transaksi (default 10%, bisa disesuaikan per mitra oleh admin — lihat bagian
5).

Berbeda dari draft awal yang hanya mencakup Villa, produk saat ini menjual
**6 kategori listing**:

| Kategori | Pola booking | Catatan |
|---|---|---|
| **Villa** | Per malam, kalender harga custom per tanggal | Kategori pertama, satu-satunya dengan override harga per tanggal & minimum stay |
| **Glamping** | Per malam, harga flat | Ditambahkan belakangan — permintaan investor, target pasar Jawa Timur (Batu, Bromo, Malang, dll), unit "tenda" dengan fasilitas ala hotel |
| **Homestay** | Per malam, harga flat | |
| **Apartment** | Per malam, harga flat | Ditambahkan belakangan — sewa harian unit apartemen |
| **Lokasi Gathering (Gathering Venue)** | Per slot waktu per hari, bukan per malam | Untuk acara/meeting, harga per slot sudah ditentukan mitra |
| **Transport** | Per hari, dengan opsi "lepas kunci" atau "dengan sopir" | Sebagian kendaraan hanya menyediakan salah satu opsi |

Urutan tampil di navigasi (frontend web & konsisten dipakai di seluruh
dashboard): **Villa → Glamping → Homestay → Apartment → Gathering Venue →
Transport**.

---

## 2. Role & Aktor

| Role | Deskripsi |
|---|---|
| **User** | Pencari & pemesan. Browsing, booking, bayar, cancel (dengan aturan refund), review. |
| **Mitra** | Pemilik/pengelola listing. Mendaftarkan properti/layanan di salah satu (atau lebih) dari 6 kategori, atur harga & ketersediaan. **Tidak pernah** menerima/menolak booking satu per satu — lihat bagian 4.2. |
| **Admin** | Approve mitra & listing baru, monitor transaksi, kelola komisi per mitra, kelola payout, kelola konten (artikel/banner/kupon), kelola user. |

Role disimpan via **Spatie Permission** (satu role per akun: `user`, `mitra`,
atau `admin`), bukan kolom `role` langsung di tabel `users`.

---

## 3. Fitur per Role

### 3.1 User

- Registrasi (email/password) atau **login Google** (redirect OAuth, hanya
  jalur web SPA — lihat catatan mobile di bagian 6).
- Pencarian per kategori: kota, tanggal/kapasitas, rentang harga, fasilitas.
- Halaman detail: galeri foto, deskripsi (rich text), fasilitas, rating &
  ulasan, kalender ketersediaan (khusus Villa) atau flat availability check
  (kategori lain).
- Booking flow: pilih tanggal/slot → cek ketersediaan & harga real-time →
  (opsional) masukkan kode kupon → buat booking (`pending_payment`) → bayar
  via Xendit → **otomatis terkonfirmasi** begitu pembayaran sukses (tanpa
  approval mitra).
- Metode pembayaran: apa pun yang didukung Xendit Invoice API (VA bank,
  e-wallet, kartu kredit, dll — dipilih user di halaman Xendit, bukan di
  Sarepundi).
- Riwayat booking & status: `pending_payment → dikonfirmasi → checked_in →
  selesai`, dengan cabang `dibatalkan_user`.
- Cancel booking (hanya saat `dikonfirmasi`): refund 85% jika ≥ H-2 sebelum
  check-in, 0% jika kurang dari itu.
- Setelah pembayaran sukses: menerima email berisi 2 PDF — **Voucher**
  (ditunjukkan ke mitra saat check-in) dan **Receipt** (bukti bayar) — juga
  bisa diunduh ulang kapan saja dari halaman detail booking.
- Beri rating (1–5) & ulasan setelah booking `selesai` (satu kali per
  booking, mitra bisa membalas).
- Notifikasi in-app + email untuk: pembayaran sukses, booking dikonfirmasi,
  reminder H-1 check-in, booking selesai (ajakan review), booking
  dibatalkan.
- Berlangganan newsletter, baca artikel/berita, lihat kupon aktif & banner
  promo di homepage.
- ⚠️ **Tidak ada** fitur wishlist/simpan listing favorit meski disebut di
  draft awal — belum dibangun sama sekali di backend maupun frontend.

### 3.2 Mitra

- Registrasi sekaligus pilih role `mitra` saat daftar, isi nama bisnis —
  akun berstatus `pending` sampai admin approve.
- Kelola profil bisnis (nama, alamat, data rekening bank untuk payout).
- CRUD listing di kategori manapun yang relevan (satu mitra bisa punya
  listing di lebih dari satu kategori sekaligus) — nama, deskripsi (rich
  text editor), alamat, foto multi-upload, fasilitas, kapasitas, harga
  dasar. Listing baru berstatus `draft`, mitra kirim untuk direview
  (`pending_review`), admin approve (`published`) atau reject (`rejected`
  + alasan).
- Villa (khusus, satu-satunya kategori dengan ini): kalender ketersediaan —
  block-out tanggal, harga custom per tanggal, minimum stay per tanggal.
- Lokasi Gathering (khusus): kelola slot waktu (nama sesi, jam mulai/selesai,
  harga) per venue, bukan kalender tanggal.
- **Tidak ada** langkah "terima/tolak booking" — lihat bagian 4.2. Peran
  mitra terhadap booking murni pasif: begitu jadwal/listing terposting
  tersedia, booking yang berhasil dibayar otomatis mengikat mitra.
- Dashboard: ringkasan pendapatan (setelah potong komisi), jumlah booking
  per status, occupancy rate, jumlah listing per kategori (draft/published).
- Riwayat booking di semua listingnya (read-only, lintas kategori).
- Laporan payout (riwayat pencairan dana dari platform, status
  pending/completed/failed).
- Balas ulasan dari user.

### 3.3 Admin

- Approve/reject pendaftaran mitra baru.
- Approve/reject listing baru, per kategori (6 halaman moderasi terpisah di
  dashboard, semuanya pola yang sama: lihat detail + foto → setujui/tolak
  dengan alasan).
- Kelola user & mitra: suspend/aktifkan akun (efeknya langsung — sesi yang
  sedang aktif pun ter-block seketika berkat middleware
  `EnsureUserIsActive`).
- **Monitoring Transaksi**: tabel semua booking lintas kategori, filter
  status & pencarian bebas (kode booking/nama listing/nama-email user),
  termasuk link unduh Voucher & Receipt PDF per baris untuk keperluan
  verifikasi.
- Kelola komisi: default 10% platform-wide, admin bisa override per mitra
  (`PATCH /api/admin/mitras/{id}/commission`) — berlaku untuk seluruh
  booking baru mitra tsb ke depannya (nilai lama tidak dihitung ulang).
- Kelola payout: jalankan payout manual kapan saja, atau otomatis
  terjadwal tanggal 1 & 15 tiap bulan (jam 02:00) — sudah final,
  bukan draft.
- Kelola konten: artikel/berita (CRUD + cover image + publish/unpublish),
  kupon (kode, tipe diskon persen/nominal, masa berlaku, aktif/nonaktif),
  banner iklan homepage (gambar + link + urutan tampil).
- Pengaturan situs: logo, favicon, gambar hero homepage, link media sosial
  (Instagram/Facebook/TikTok).

---

## 4. Alur Bisnis Kunci

### 4.1 Alur Booking

Lihat [`BOOKING_FLOW.md`](./BOOKING_FLOW.md) untuk narasi lengkap
langkah-demi-langkah. Ringkasan satu paragraf: user pilih listing & tanggal
→ cek ketersediaan real-time → buat booking (`pending_payment`) → bayar via
Xendit → webhook Xendit sukses → booking **otomatis** `dikonfirmasi` → H-1
reminder → tanggal check-in/check-out lewat → otomatis `checked_in` lalu
`selesai` (job terjadwal harian) → user bisa review, mitra masuk antrean
payout.

### 4.2 Konfirmasi Booking — TIDAK ADA approval manual mitra

**Perubahan kebijakan bisnis paling signifikan sejak draft awal.** Mitra
**tidak pernah** approve/tolak booking satu per satu. Peran mitra hanya
menyediakan jadwal ketersediaan di awal (kalender Villa, slot Lokasi
Gathering, atau sekadar mempublikasikan listing untuk kategori flat-price
lainnya) — begitu itu terposting sebagai tersedia, itu adalah komitmen
mitra. Mitra tidak bisa menolak atau membatalkan booking yang sudah dibayar.

Konsekuensi teknis: tidak ada lagi status `menunggu_konfirmasi`/
`dibatalkan_mitra` yang diproduksi kode, tidak ada batas waktu 24 jam, tidak
ada job terjadwal untuk auto-cancel akibat mitra tidak merespon (command
untuk itu sudah dihapus total dari kode). Nilai enum lama itu masih ada di
skema database untuk kompatibilitas data lama, tapi kode aplikasi tidak
pernah lagi memproduksinya.

### 4.3 Kebijakan Cancellation & Refund (oleh User)

Satu-satunya pihak yang bisa membatalkan adalah **user**, dan hanya selagi
booking berstatus `dikonfirmasi`:

| Selisih hari ke `check_in_date` saat cancel | Refund |
|---|---|
| ≥ 2 hari (H-2 atau lebih) | **85%** dari `total_price` |
| < 2 hari | **0%** |

Refund 0% tidak memicu panggilan API Xendit sama sekali (tidak ada yang
perlu dikembalikan). Kalau panggilan refund ke Xendit gagal, pembatalan
booking tetap diproses (tidak diblok) — dicatat sebagai refund berstatus
`failed` untuk ditindaklanjuti admin secara manual.

⚠️ **PERLU KONFIRMASI**: tidak ada mekanisme apapun untuk membatalkan atau
membersihkan booking yang tersangkut di status `pending_payment` selamanya
(user tidak jadi bayar, tidak ada auto-cancel, tidak ada tombol cancel untuk
status ini). Perlu didiskusikan apakah ini kekurangan yang perlu ditambal
atau memang belum jadi prioritas.

### 4.4 Sistem Kupon/Diskon

Tidak ada di draft awal — fitur baru. Admin membuat kupon (kode unik, tipe
diskon persentase atau nominal tetap, tanggal berlaku, status aktif). User
memasukkan kode saat cek ketersediaan atau saat membuat booking. Diskon
dihitung dari `subtotal`, di-cap supaya tidak melebihi subtotal (`total_price`
tidak pernah negatif). Komisi platform dihitung dari `total_price`
**setelah** diskon — platform yang menanggung "biaya" promosi, bagian mitra
tidak terpengaruh oleh kupon.

### 4.5 Voucher & Receipt PDF Otomatis

Fitur baru, tidak ada di draft awal. Setiap booking yang berhasil dibayar
otomatis menghasilkan 2 dokumen PDF terpisah:

- **Voucher** — detail listing, tanggal, nama tamu; ditunjukkan ke mitra
  saat check-in/serah terima.
- **Receipt** — rincian harga & bukti pembayaran resmi.

Dikirim via email (dengan lampiran) begitu pembayaran sukses, dan bisa
diunduh ulang kapan saja dari dashboard (endpoint tersedia untuk user
pemilik booking, mitra pemilik listing terkait, dan admin).

### 4.6 Payout ke Mitra

Trigger: booking berstatus `selesai` (setelah check-out) dan belum pernah
masuk batch payout manapun. Dijalankan **otomatis tiap tanggal 1 & 15, jam
02:00** (sudah final — draft awal masih menulis "misal tanggal 1 & 15",
sekarang benar-benar dikonfigurasi di scheduler), mengumpulkan semua booking
`selesai` milik satu mitra **lintas 6 kategori sekaligus** jadi satu
pencairan via Xendit Disbursement API. Bisa juga dipicu manual oleh admin.
Kegagalan pencairan (rekening invalid, saldo platform kurang, dll) tidak
dibiarkan gagal senyap — tercatat sebagai payout berstatus `failed`, terlihat
di dashboard admin untuk di-retry manual.

---

## 5. Komisi Platform

- Default **10%** dari `total_price` (setelah diskon kupon) untuk setiap
  booking, disimpan sebagai `commission_amount` saat booking dibuat (tidak
  pernah dihitung ulang di tempat lain, termasuk saat payout).
- **90%** (`mitra_payout_amount`) menjadi hak mitra.
- Admin **bisa override** persentase ini per mitra (`mitra_profiles.commission_rate`,
  nullable — `null` berarti pakai default 10%). Ini beda dari asumsi draft
  awal bahwa komisi selalu flat 10%/90% untuk semua mitra.

---

## 6. Auth & Mobile

Dijelaskan detail di [`API_CONTRACT.md` bagian 0](./API_CONTRACT.md#0-untuk-developer-mobile-flutter--baca-ini-dulu).
Ringkasan:

- Backend memakai **Laravel Sanctum mode hybrid**: cookie session + CSRF
  untuk frontend web (Nuxt SPA), **Bearer token** (Sanctum Personal Access
  Token) untuk klien lain (mobile app) — otomatis terpilih berdasarkan
  apakah request datang dari domain yang terdaftar di
  `SANCTUM_STATEFUL_DOMAINS`, tidak perlu konfigurasi tambahan di sisi
  mobile.
- `POST /api/bookings/{id}/pay` menerima parameter `platform` (`web`
  default, atau `mobile`) untuk mengarahkan redirect sukses/gagal
  pembayaran Xendit ke deep link app (`sarepundi://...`) alih-alih halaman
  web.
- ⚠️ **PERLU KONFIRMASI/belum dikerjakan**: Login Google untuk mobile.
  Implementasi saat ini (`SocialAuthController`) murni alur redirect
  browser untuk web — belum ada endpoint untuk menukar Google ID Token
  (hasil native Google Sign-In di Android/iOS) dengan token Sarepundi.
  Perlu sesi pengerjaan terpisah kalau dibutuhkan.

---

## 7. Skema Database (ringkasan tabel inti)

Tabel-tabel utama saat ini (jauh lebih banyak dari draft awal yang hanya
menyebut `villas`):

**Identitas & mitra**: `users` (kolom `provider`/`provider_id` untuk Google
OAuth, `status` active/suspended), `mitra_profiles` (termasuk
`commission_rate` nullable), `personal_access_tokens` (Sanctum, untuk auth
mobile).

**Listing per kategori** (pola identik untuk tiap kategori — tabel utama +
`_images` + `_facilities` pivot): `villas` (+ `villa_availability` untuk
kalender custom — satu-satunya kategori dengan ini), `glampings`,
`homestays`, `apartments`, `gathering_venues` (+ `gathering_venue_slots`,
bukan availability per tanggal), `transports`. Semua listing merujuk
`mitra_profiles` via `mitra_id`, dan ke `facilities` (master data fasilitas
bersama) via pivot masing-masing.

**Transaksi**: `bookings` (polimorfik — `bookable_type` + `bookable_id`
merujuk salah satu dari 6 tabel listing di atas; kolom kunci: `subtotal`,
`discount_amount`, `total_price`, `commission_amount`, `mitra_payout_amount`,
`status`, `coupon_id`, `gathering_venue_slot_id` nullable, `transport_with_driver`
nullable, field cancellation/refund), `payments`, `refunds`, `payouts`.

**Interaksi & konten**: `reviews` (polimorfik, `reviewable_type` +
`reviewable_id`), `notifications`, `coupons`, `banners`, `articles`,
`newsletter_subscribers`, `site_settings` (single-row).

Detail kolom lengkap tabel `bookings`/`payments`/`refunds`/`payouts` ada di
[`API_CONTRACT.md` bagian 7](./API_CONTRACT.md#7-ringkasan-field-booking-semua-field-yang-bisa-muncul).

---

## 8. Stack Teknis (Final)

| Layer | Pilihan |
|---|---|
| Frontend | Nuxt 3 + Tailwind CSS. Layout dashboard (admin/mitra/user) pakai sidebar kiri, konten kanan — satu layout, dipilih otomatis berdasarkan prefix URL. |
| Backend API | Laravel 13. Auth: Sanctum (hybrid cookie session + Bearer token, lihat bagian 6). Role: Spatie Permission. |
| Database | MySQL |
| Payment Gateway | Xendit — Invoice API (pembayaran), Refund API, Disbursement API (payout mitra). Semua interaksi dibungkus `XenditService`, tidak pernah dipanggil langsung dari controller. |
| File Storage | Local / S3-compatible |
| Scheduled Job | Laravel Scheduler: `bookings:advance-completed-stays` (harian, transisi `dikonfirmasi→checked_in→selesai`), `payouts:run` (tanggal 1 & 15 jam 02:00), `bookings:send-checkin-reminders` (harian jam 09:00) |
| Notifikasi | In-app (tabel `notifications`) + Email (SMTP, `Mail::raw` untuk notifikasi umum; `Mail\BookingPaymentConfirmed` khusus untuk voucher/receipt dengan lampiran PDF). WhatsApp API **tidak diimplementasikan** (sempat disebut opsional di draft awal). |
| Dokumen PDF | `barryvdh/laravel-dompdf`, dibungkus `BookingDocumentService` (satu sumber untuk endpoint download maupun lampiran email). |
| Deployment | Ubuntu server, nginx, PM2 (Nuxt), PHP-FPM (Laravel) |

---

## 9. Hal yang Belum Lengkap / PERLU KONFIRMASI

Daftar celah yang ditemukan saat audit kode ini, supaya tidak jadi asumsi
tersembunyi buat siapa pun yang mengerjakan modul berikutnya:

- ⚠️ **Admin dashboard stats** (`GET /api/admin/stats`) hanya menghitung
  data kategori **Villa** untuk breakdown per-kategori (`villas.total`,
  `villas.published`, dst) — 5 kategori lain tidak muncul di ringkasan
  dashboard admin, meski data booking `bookings.*` sudah menghitung lintas
  semua kategori dengan benar. Kemungkinan besar tertinggal saat kategori
  baru ditambahkan satu-satu.
- ⚠️ Booking `pending_payment` yang tidak pernah dibayar tidak punya jalan
  keluar otomatis (lihat bagian 4.3).
- ⚠️ Login Google untuk mobile belum ada endpoint-nya (lihat bagian 6).
- ⚠️ Tidak ada fitur wishlist meski sempat direncanakan di draft awal.
- ⚠️ `ReviewController` mengirim notifikasi ke mitra dengan judul hardcoded
  "Review baru untuk **villa** kamu" terlepas dari kategori listing yang
  sebenarnya direview (Glamping/Homestay/dll) — teks judul notifikasi,
  bukan data, jadi dampaknya kosmetik saja.
- ⚠️ Redirect URL pembayaran mobile (`platform=mobile`) tidak berlaku
  retroaktif untuk invoice `pending` yang sudah dibuat lebih dulu dengan
  `platform=web` (atau sebaliknya) — lihat catatan di
  [`API_CONTRACT.md` bagian 0](./API_CONTRACT.md).

---

## 10. Alur Kerja dengan Claude Code

Tidak berubah dari sebelumnya — lihat `CLAUDE.md` di root repo untuk
konvensi coding, dan kerjakan satu modul per sesi.

---

## Riwayat Perubahan dari Versi Sebelumnya

Ringkasan poin-poin paling signifikan yang berubah dari draft PRD pertama
(dokumen ini menggantikannya sepenuhnya):

1. **Konfirmasi booking oleh mitra dihapus total** — draft awal
   mendeskripsikan mitra harus terima/tolak booking dalam 24 jam dengan
   auto-cancel + refund 100% kalau timeout. Digantikan konfirmasi otomatis
   begitu pembayaran sukses (lihat bagian 4.2).
2. **Kebijakan refund disederhanakan** — dari 2 skenario (100% sebelum
   konfirmasi mitra, 85%/0% sesudahnya) menjadi 1 skenario saja: 85% jika
   ≥ H-2, 0% jika kurang (lihat bagian 4.3).
3. **Kategori listing dari 1 (Villa) jadi 6** — Glamping, Homestay,
   Apartment, Lokasi Gathering, dan Transport ditambahkan belakangan,
   masing-masing dengan pola booking sedikit berbeda (lihat bagian 1).
4. **Sistem kupon/diskon** ditambahkan — tidak ada di draft awal (bagian
   4.4).
5. **Voucher & Receipt PDF otomatis** ditambahkan — tidak ada di draft awal
   (bagian 4.5).
6. **Komisi platform bisa berbeda per mitra** — draft awal mengasumsikan
   flat 10%/90% untuk semua (bagian 5).
7. **Jadwal payout tanggal 1 & 15 sudah final**, bukan lagi draft/asumsi
   (bagian 4.6).
8. **Auth mendukung mobile (Bearer token) sejak 2026-08-05** — sebelumnya
   backend murni SPA cookie-session, tidak bisa dipakai app native tanpa
   penyesuaian (bagian 6).
