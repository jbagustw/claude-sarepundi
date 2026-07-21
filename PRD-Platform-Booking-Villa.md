# PRD: Platform Booking Villa/Penginapan

## 1. Ringkasan Produk

Platform web app untuk pemesanan villa/penginapan yang menghubungkan pencari villa (user), pemilik/pengelola villa (mitra), dan admin platform. Model bisnis: marketplace dengan komisi 10% per transaksi ke platform.

---

## 2. Role & Aktor

| Role | Deskripsi |
|---|---|
| **User** | Pencari & pemesan villa. Bisa browsing, booking, bayar, review. |
| **Mitra** | Pemilik/pengelola villa. Mendaftarkan properti, atur harga & ketersediaan, konfirmasi booking masuk. |
| **Admin** | Pengelola platform. Approve mitra & listing, monitor transaksi, kelola komisi, CS, laporan. |

---

## 3. Fitur per Role

### 3.1 User
- Registrasi/login (email, Google OAuth opsional)
- Pencarian villa: lokasi, tanggal check-in/out, jumlah tamu, budget
- Filter: fasilitas (kolam renang, AC, wifi, dapur, dll), tipe properti, rating
- Halaman detail villa: galeri foto, deskripsi, fasilitas, peraturan, kalender ketersediaan, harga per malam
- Booking flow: pilih tanggal → cek ketersediaan → isi data tamu → pembayaran → **menunggu konfirmasi mitra**
- Metode pembayaran: transfer VA, e-wallet, kartu kredit (via Xendit)
- Riwayat booking & status (pending_payment, menunggu_konfirmasi, dikonfirmasi, ditolak, selesai, dibatalkan)
- Cancel booking (refund 100% jika masih `menunggu_konfirmasi`, atau sesuai kebijakan H-2/85% jika sudah `dikonfirmasi`)
- Beri review & rating setelah check-out
- Wishlist/simpan villa favorit
- Notifikasi (email/WA/in-app): konfirmasi pembayaran, status konfirmasi mitra, reminder H-1, dll

### 3.2 Mitra
- Registrasi mitra (perlu approval admin) — upload dokumen legalitas
- Kelola profil bisnis (termasuk data rekening untuk payout)
- CRUD listing villa: nama, deskripsi, alamat, foto (multi-upload), fasilitas, kapasitas, jumlah kamar
- Atur harga: harga dasar, harga weekend/high season, minimum stay
- Kalender ketersediaan (block-out tanggal, sinkronisasi manual)
- **Konfirmasi manual booking masuk**: terima atau tolak booking yang sudah dibayar user, dalam batas waktu **24 jam** sebelum otomatis dibatalkan & direfund penuh ke user
- Dashboard: pendapatan (setelah potong komisi 10%), jumlah booking, occupancy rate
- Laporan payout (pencairan dana dari platform)
- Balas review dari user

### 3.3 Admin
- Approve/reject pendaftaran mitra
- Approve/reject listing villa baru (moderasi konten & foto)
- Kelola user & mitra (suspend, verifikasi)
- Monitoring semua transaksi & status pembayaran (via Xendit dashboard/webhook log)
- Kelola komisi platform (default 10%, bisa di-override per mitra bila diperlukan)
- Kelola payout ke mitra
- Laporan & analytics (revenue, growth, top villa, dll)
- Kelola konten CMS (banner promo, artikel, FAQ)
- Handling komplain/dispute, termasuk booking yang tidak dikonfirmasi mitra tepat waktu
- Kelola kategori/fasilitas master data

---

## 4. Alur Bisnis Kunci

### 4.1 Alur Booking (dengan Konfirmasi Manual Mitra)
```
User cari villa → pilih tanggal → cek ketersediaan (real-time)
→ isi data pemesan → bayar via Xendit (VA/e-wallet/kartu)
→ [Xendit webhook: payment success] → status booking: "menunggu_konfirmasi"
→ notifikasi ke mitra: ada booking baru menunggu konfirmasi
→ Mitra konfirmasi dalam batas waktu (misal 24 jam):
   ├─ DITERIMA → status: "dikonfirmasi" → notifikasi ke user
   └─ DITOLAK / timeout tidak direspon → status: "dibatalkan_mitra"
       → auto refund 100% ke user via Xendit → notifikasi ke user
→ H-1: reminder → check-in → check-out → status: "selesai"
→ dana masuk saldo mitra (dikurangi komisi 10%)
→ user bisa beri review
```

### 4.2 Alur Pembatalan oleh User (Kebijakan Refund)

Ada dua skenario tergantung status booking saat user mengajukan cancel:

**A. Booking masih "menunggu_konfirmasi" (mitra belum putuskan)**
```
User request cancel → status booking masih "menunggu_konfirmasi"
→ refund 100% dari total pembayaran (belum ada komitmen dari mitra)
→ update status booking: "dibatalkan_user"
→ notifikasi ke mitra (booking dibatalkan sebelum sempat dikonfirmasi) & user
```

**B. Booking sudah "dikonfirmasi" oleh mitra**
```
User request cancel → status booking sudah "dikonfirmasi"
→ Sistem cek selisih hari ke check_in_date:
   ├─ Cancel ≥ H-2 (2 hari atau lebih sebelum check-in) → refund 85% dari total pembayaran
   └─ Cancel < H-2 (kurang dari 2 hari) → tidak ada refund (0%)
→ Proses refund via Xendit (jika berlaku) → update status booking: "dibatalkan_user"
→ update ketersediaan kalender villa (tanggal dibuka kembali)
→ notifikasi ke mitra & user
```
> Catatan: pembatalan oleh **mitra** (menolak/tidak merespon dalam 24 jam) selalu refund 100% ke user karena bukan kesalahan user — beda status (`dibatalkan_mitra`) dari pembatalan oleh user (`dibatalkan_user`).

### 4.3 Alur Payout ke Mitra (Otomatis via Xendit Disbursement)
```
Booking selesai (check-out, status "selesai")
→ dana booking (90% dari total, setelah potong komisi 10%) masuk saldo mitra
→ payout otomatis terjadwal (misal tiap tanggal 1 & 15) mengumpulkan semua saldo tertunda
→ sistem panggil Xendit Disbursement API → transfer ke rekening mitra
→ catat sebagai payout (status: pending/completed/failed), simpan xendit_disbursement_id
→ jika gagal (saldo Xendit kurang, rekening invalid, dll) → notifikasi ke admin untuk ditindaklanjuti manual
```

---

## 5. Skema Database (Draft Awal)

### Tabel Utama

**users**
- id, name, email, phone, password, role (user/mitra/admin), avatar, email_verified_at, status (active/suspended), created_at

**mitra_profiles**
- id, user_id (FK), business_name, business_address, legal_document_url, bank_account, bank_name, status (pending/approved/rejected), approved_by, approved_at

**villas**
- id, mitra_id (FK), name, slug, description, address, city, province, latitude, longitude, capacity_guest, bedroom_count, bathroom_count, base_price, status (draft/pending_review/published/rejected/inactive), created_at

**villa_images**
- id, villa_id (FK), image_url, is_primary, sort_order

**facilities** (master data)
- id, name, icon, category

**villa_facilities** (pivot)
- villa_id (FK), facility_id (FK)

**villa_availability**
- id, villa_id (FK), date, is_available, custom_price (nullable, override base_price), min_stay

**bookings**
- id, booking_code, user_id (FK), villa_id (FK), check_in_date, check_out_date, guest_count, total_price, commission_amount (10% dari total_price), mitra_payout_amount (90% dari total_price), status (pending_payment / menunggu_konfirmasi / dikonfirmasi / dibatalkan_mitra / dibatalkan_user / checked_in / selesai), mitra_confirmed_at, mitra_confirmation_deadline (created_at + 24 jam), cancellation_reason (user_cancel_pending / user_cancel_confirmed / mitra_reject / mitra_timeout), cancelled_at, refund_amount, refund_percentage, created_at

**payments**
- id, booking_id (FK), payment_method, xendit_invoice_id, xendit_payment_id, amount, status (pending/success/failed/refunded/partial_refunded), paid_at

**refunds**
- id, booking_id (FK), payment_id (FK), amount, percentage, reason (user_cancel/mitra_reject/mitra_timeout), xendit_refund_id, status, processed_at

**payouts**
- id, mitra_id (FK), amount, period_start, period_end, xendit_disbursement_id, status (pending/completed/failed), processed_at

**reviews**
- id, booking_id (FK), user_id (FK), villa_id (FK), rating, comment, mitra_reply, created_at

**notifications**
- id, user_id (FK), type, title, message, is_read, created_at

**cms_contents**
- id, type (banner/faq/article), title, content, image_url, is_active, sort_order

---

## 6. Stack Teknis (Final)

| Layer | Pilihan |
|---|---|
| Frontend | Nuxt 3 (Vue) + Tailwind CSS |
| Backend API | **Laravel** — auth (Sanctum), role management (Spatie Permission), queue job untuk notifikasi & auto-cancel timeout |
| Database | MySQL |
| Payment Gateway | **Xendit** — Invoice API (pembayaran), Disbursement API (payout ke mitra), Refund API (webhook untuk konfirmasi status) |
| File Storage | Local/S3-compatible (foto villa & dokumen mitra) |
| Notifikasi | Email (SMTP) + WhatsApp API (opsional, misal Fonnte/Wablas) via n8n |
| Scheduled Job | Laravel Scheduler untuk auto-cancel booking yang tidak dikonfirmasi mitra dalam batas waktu |
| Deployment | VPS |

---

## 7. Urutan Pengerjaan (Milestone)

1. **Setup & Auth** — project skeleton Laravel + Nuxt, role-based login (user/mitra/admin)
2. **Modul Villa** — CRUD listing untuk mitra, moderasi admin, browsing publik untuk user
3. **Modul Booking & Ketersediaan** — kalender, cek konflik tanggal, booking flow sampai status "menunggu_konfirmasi"
4. **Integrasi Xendit** — payment (Invoice API) sandbox dulu, lalu live; webhook handler
5. **Modul Konfirmasi Mitra** — accept/reject, scheduled job untuk auto-cancel timeout + auto refund 100%
6. **Modul Cancellation & Refund oleh User** — hitung H-2 & 85%, integrasi Xendit Refund API
7. **Dashboard Admin** — approval, monitoring, komisi, payout
8. **Dashboard Mitra** — laporan pendapatan, kelola booking
9. **Payout ke Mitra** — via Xendit Disbursement atau manual, dengan pencatatan
10. **Review & Notifikasi**
11. **Polish UI** sesuai Figma, per halaman, responsive check

---

## 8. Catatan untuk Claude Code

Ringkasan dokumen ini + konvensi coding proyek ada di `CLAUDE.md` di root repo — pastikan file itu selalu jadi rujukan pertama di setiap sesi kerja dengan Claude Code.
