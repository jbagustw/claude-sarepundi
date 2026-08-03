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
- Scheduled Job: Laravel Scheduler (advance booking checked_in → selesai, payout run tanggal 1 & 15, reminder H-1 check-in)
- Notifikasi: Email (SMTP) + WhatsApp API opsional (Fonnte/Wablas via n8n)

## Aturan Bisnis Kunci (Wajib Dipatuhi di Semua Implementasi)

### Komisi Platform
- **10%** dari `total_price` setiap booking menjadi komisi platform
- **90%** menjadi hak mitra (`mitra_payout_amount`)
- Hitung & simpan kedua nilai ini saat booking dibuat, jangan hitung ulang secara implisit di tempat lain

### Konfirmasi Booking (Update: mitra tidak lagi approve manual per booking)
> **Perubahan kebijakan bisnis** (permintaan investor, menggantikan desain awal di bawah): mitra **tidak pernah** approve/tolak booking satu per satu. Peran mitra hanya menyediakan **jadwal ketersediaan** di awal (villa availability calendar / slot lokasi gathering / dst) — begitu jadwal itu terposting, itu adalah komitmen mitra. Mitra **tidak boleh menolak atau membatalkan** booking yang sudah dibayar.
>
> Alur: Mitra buka jadwal available → User booking (`pending_payment`) → User bayar, webhook Xendit sukses → status langsung `dikonfirmasi` (otomatis, tanpa keputusan mitra, asalkan jadwalnya memang tersedia saat booking dibuat).
>
> Konsekuensi: tidak ada lagi status `menunggu_konfirmasi`/`dibatalkan_mitra`, tidak ada lagi batas waktu 24 jam, tidak ada lagi job terjadwal untuk auto-cancel akibat mitra tidak merespon. Satu-satunya pihak yang bisa membatalkan booking setelah dikonfirmasi adalah **user**.

### Kebijakan Cancellation oleh User

Karena booking selalu langsung `dikonfirmasi` setelah bayar, hanya ada satu aturan refund untuk pembatalan oleh user:

| Status booking saat cancel | Kebijakan Refund |
|---|---|
| `dikonfirmasi` | Hitung selisih hari ke `check_in_date`: **≥ H-2 → refund 85%**, **< H-2 → refund 0%** |

Simpan `refund_percentage`, `refund_amount`, dan `cancellation_reason` (`user_cancel_confirmed`) di record terkait untuk audit.

### Status Booking (gunakan enum ini secara konsisten)
```
pending_payment → dikonfirmasi → checked_in → selesai
                        ↓
                 dibatalkan_user
```

> Nilai enum `menunggu_konfirmasi`, `dibatalkan_mitra`, dan alasan pembatalan `mitra_reject`/`mitra_timeout`/`user_cancel_pending` masih ada di skema database (kolom `status`/`cancellation_reason`) untuk kompatibilitas mundur, tapi kode aplikasi tidak pernah lagi memproduksinya — jangan pakai nilai-nilai itu di kode baru.

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
- Setelah fitur selesai, minta dibuatkan test dasar (khususnya untuk logic komisi dan refund — ini rawan bug kalau tidak ditest)
- Commit di titik-titik stabil (per fitur selesai), bukan di tengah pekerjaan setengah jadi

## Referensi Desain (Figma)

- Asset/screenshot per halaman ada di folder `/design`
- Saat mengerjakan UI suatu halaman, rujuk screenshot yang relevan agar hasil sesuai desain
- Ikuti design token (warna, spacing, font) yang sudah ada — jangan improvisasi warna/style baru tanpa referensi

## Yang Masih Perlu Dikonfirmasi

- [ ] Jadwal payout otomatis: tiap tanggal berapa? (draft ini asumsikan tiap tanggal 1 & 15)
