# CLAUDE.md — Platform Booking Villa

Dokumen ini adalah konteks utama untuk Claude Code saat bekerja di repo ini. Baca dulu sebelum mengerjakan task apa pun.

## Tentang Project

Web app marketplace booking villa/penginapan dengan 3 role:
- **User** — pencari & pemesan villa
- **Mitra** — pemilik/pengelola villa yang mendaftarkan properti
- **Admin** — pengelola platform (approval, monitoring, komisi)

Detail fitur lengkap & alur bisnis ada di `PRD-Platform-Booking-Villa.md` di root repo — baca file itu untuk konteks fitur sebelum implementasi modul baru.

## Stack Teknis (Final)

- Frontend: Nuxt 3 + Tailwind CSS
- Backend API: **Laravel** (Sanctum untuk auth, Spatie Permission untuk role)
- Database: MySQL
- Payment Gateway: **Xendit** (Invoice API untuk pembayaran, Refund API, Disbursement API untuk payout mitra)
- File Storage: local / S3-compatible
- Scheduled Job: Laravel Scheduler (untuk auto-cancel booking yang tidak dikonfirmasi mitra)
- Notifikasi: Email (SMTP) + WhatsApp API opsional (Fonnte/Wablas via n8n)

## Aturan Bisnis Kunci (Wajib Dipatuhi di Semua Implementasi)

### Komisi Platform
- **10%** dari `total_price` setiap booking menjadi komisi platform
- **90%** menjadi hak mitra (`mitra_payout_amount`)
- Hitung & simpan kedua nilai ini saat booking dibuat, jangan hitung ulang secara implisit di tempat lain

### Konfirmasi Booking oleh Mitra
- Setelah pembayaran user berhasil (webhook Xendit sukses), status booking → `menunggu_konfirmasi`
- Mitra **wajib konfirmasi manual** (terima/tolak) dalam batas waktu **24 jam** sejak status `menunggu_konfirmasi`
- Jika mitra tolak, ATAU tidak merespon sampai deadline (24 jam) → booking otomatis `dibatalkan_mitra` + **refund 100%** ke user (bukan kesalahan user)
- Gunakan Laravel Scheduler + Queue Job (jalan berkala, misal tiap 15-30 menit) untuk cek booking yang sudah lewat `mitra_confirmation_deadline` dan belum ada keputusan mitra

### Kebijakan Cancellation oleh User

Ada dua skenario berbeda tergantung status booking saat user cancel:

| Status booking saat cancel | Kebijakan Refund |
|---|---|
| `menunggu_konfirmasi` (mitra belum putuskan) | **Refund 100%** — belum ada komitmen dari mitra, user tidak dirugikan menunggu |
| `dikonfirmasi` (mitra sudah terima) | Hitung selisih hari ke `check_in_date`: **≥ H-2 → refund 85%**, **< H-2 → refund 0%** |

> Catatan implementasi: kondisi `menunggu_konfirmasi` → refund 100% ini asumsi wajar (belum ada layanan yang dikonfirmasi mitra, jadi user tidak seharusnya kena penalti). Konfirmasi ke product owner kalau ini perlu diubah, tapi ini default yang aman untuk mulai development.

Pembatalan oleh **mitra** (tolak/timeout) selalu refund 100% dan statusnya `dibatalkan_mitra` — beda alur & beda status dengan pembatalan oleh **user** (`dibatalkan_user`). Jangan campur logic keduanya.

Simpan `refund_percentage`, `refund_amount`, dan `cancellation_reason` (enum: `user_cancel_pending` / `user_cancel_confirmed` / `mitra_reject` / `mitra_timeout`) di record terkait untuk audit.

### Status Booking (gunakan enum ini secara konsisten)
```
pending_payment → menunggu_konfirmasi → dikonfirmasi → checked_in → selesai
                        ↓         ↓            ↓
              dibatalkan_user  dibatalkan_mitra  dibatalkan_user
```

### Payout ke Mitra
- **Otomatis via Xendit Disbursement API** — tidak manual
- Trigger: booking berstatus `selesai` (setelah check-out) → dana `mitra_payout_amount` (90%) masuk saldo mitra → dicairkan sesuai jadwal payout (misal tiap tanggal 1 & 15) via Disbursement API
- Simpan `xendit_disbursement_id` di tabel `payouts` untuk tracking status pencairan (pending/completed/failed)
- Siapkan handling untuk kasus disbursement gagal (saldo Xendit kurang, rekening mitra invalid, dll) — jangan biarkan gagal senyap, harus ada notifikasi ke admin

## Struktur Folder

<!-- Update setelah skeleton project dibuat -->
```
/frontend         → Nuxt 3 app
/backend          → Laravel API
/docs             → PRD, skema, dokumen referensi
/design           → asset export dari Figma (screenshot per halaman)
```

## Konvensi Coding

- Bahasa penamaan variabel/fungsi: **Inggris**
- Bahasa komentar & commit message: **Bahasa Indonesia** boleh, tapi tetap jelas
- Format tanggal: `YYYY-MM-DD` (ISO) di backend, tampilkan ke user dalam format lokal Indonesia
- Mata uang: simpan sebagai integer (rupiah, tanpa desimal) di database
- Validasi input di sisi backend (Laravel Form Request), jangan andalkan validasi frontend saja
- Setiap endpoint API yang butuh role tertentu harus pakai middleware/policy role check eksplisit (Spatie Permission)
- Semua interaksi dengan Xendit (create invoice, webhook, refund, disbursement) dibungkus di service class terpisah, jangan panggil API Xendit langsung dari controller

## Role & Auth

- Role disimpan via Spatie Permission: `user`, `mitra`, `admin`
- Mitra baru berstatus `pending` sampai di-approve admin — jangan tampilkan listing dari mitra yang belum approved
- Villa baru berstatus `pending_review` sampai admin approve — jangan tampilkan di pencarian publik sebelum `published`

## Database

Skema awal ada di `PRD-Platform-Booking-Villa.md` bagian 5. Tabel inti: `users`, `mitra_profiles`, `villas`, `villa_images`, `villa_availability`, `bookings`, `payments`, `refunds`, `payouts`, `reviews`.

Saat membuat migration baru, cek dulu skema ini supaya konsisten dengan relasi yang sudah direncanakan, terutama field terkait komisi dan refund di tabel `bookings`.

## Alur Kerja dengan Claude Code

- Kerjakan **satu modul per sesi** (lihat urutan milestone di PRD bagian 7), jangan minta banyak modul sekaligus
- Sebelum mulai modul baru, jelaskan dulu ke Claude Code modul apa yang mau dikerjakan + rujuk bagian PRD yang relevan
- Setelah fitur selesai, minta dibuatkan test dasar (khususnya untuk logic komisi, refund, dan auto-cancel timeout — ini rawan bug kalau tidak ditest)
- Commit di titik-titik stabil (per fitur selesai), bukan di tengah pekerjaan setengah jadi

## Referensi Desain (Figma)

- Asset/screenshot per halaman ada di folder `/design`
- Saat mengerjakan UI suatu halaman, rujuk screenshot yang relevan agar hasil sesuai desain
- Ikuti design token (warna, spacing, font) yang sudah ada — jangan improvisasi warna/style baru tanpa referensi

## Yang Masih Perlu Dikonfirmasi

- [ ] Apakah refund 100% untuk cancel saat status `menunggu_konfirmasi` sudah sesuai keinginan bisnis? (ini asumsi default, belum eksplisit kamu tentukan — cek bagian "Kebijakan Cancellation oleh User" di atas)
- [ ] Jadwal payout otomatis: tiap tanggal berapa? (draft ini asumsikan tiap tanggal 1 & 15)
