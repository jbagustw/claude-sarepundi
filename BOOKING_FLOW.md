# Alur Booking Sarepundi — Versi Terkini (per kode aktual)

> Dokumen ini bercerita, bukan referensi teknis endpoint — untuk itu lihat
> [`API_CONTRACT.md`](./API_CONTRACT.md). Ditulis berdasarkan pembacaan kode
> backend per **2026-08-04**. Berbeda cukup signifikan dari
> `PRD-Platform-Booking-Villa.md` — perbedaannya dirangkum di bagian akhir
> dokumen ini dan di `API_CONTRACT.md` bagian 10.
>
> Bagian yang tidak bisa dipastikan dari kode ditandai **⚠️ PERLU KONFIRMASI**.

---

## Poin Paling Penting di Atas Segalanya

**Mitra TIDAK PERNAH menerima atau menolak booking satu per satu.** Ini
perubahan kebijakan bisnis dari investor yang menggantikan desain awal di
PRD. Peran mitra hanya menyediakan jadwal ketersediaan (kalender villa, slot
lokasi gathering, dst) di awal — begitu jadwal itu terposting sebagai
tersedia, itu sudah jadi komitmen mitra. Begitu user selesai bayar, booking
langsung terkonfirmasi secara otomatis oleh sistem, tanpa ada keputusan
manusia dari sisi mitra.

Satu-satunya pihak yang bisa membatalkan booking setelah terkonfirmasi adalah
**user**, bukan mitra.

---

## Ringkasan Alur (satu gambar besar)

```
User cari & pilih listing
        │
        ▼
Cek ketersediaan & harga (real-time)
        │
        ▼
User isi jumlah tamu, (opsional) kode kupon → submit
        │
        ▼
Booking dibuat, status: pending_payment
        │
        ▼
User bayar via Xendit (VA / e-wallet / kartu / dll — user pilih di halaman Xendit)
        │
        ▼
   Xendit kirim webhook ke server
        │
        ├── Sukses (PAID) ──► status: dikonfirmasi (OTOMATIS)
        │                     │
        │                     ├── Email ke user: voucher + receipt (PDF)
        │                     ├── Notifikasi in-app ke user & mitra
        │                     │
        │                     ▼
        │              Tanggal check-in lewat ──► status: checked_in
        │                     │
        │                     ▼
        │              Tanggal check-out lewat ──► status: selesai
        │                     │
        │                     ├── Notifikasi "yuk beri review" ke user
        │                     ├── Booking masuk antrean payout mitra
        │                     ▼
        │              User bisa beri rating & ulasan
        │
        └── Gagal/Expired ──► status payment: failed
                              (status booking TETAP pending_payment,
                               tidak ada auto-cancel — user harus coba bayar lagi
                               lewat tombol yang sama)

Kapan saja selagi status masih "dikonfirmasi", user boleh membatalkan:
        │
        ▼
User klik batalkan
        │
        ├── Sisa hari ke check-in ≥ 2 hari (H-2 atau lebih) → refund 85%
        └── Sisa hari ke check-in < 2 hari                  → refund 0%
        │
        ▼
status: dibatalkan_user (final, tidak bisa diapa-apakan lagi)
```

---

## Langkah demi Langkah

### 1. User mencari listing

User mencari salah satu dari 6 kategori: **Villa, Glamping, Homestay,
Apartment, Lokasi Gathering (Gathering Venue), atau Transport**. Pencarian
bisa difilter kota, jumlah tamu/kapasitas, rentang harga, dan fasilitas.

Hanya listing berstatus `published` dari mitra yang sudah `approved` dan
akunnya `active` yang muncul di pencarian publik — listing yang masih
`draft`/`pending_review`/`rejected`, atau milik mitra yang belum di-approve
admin, tidak akan pernah tampil ke user.

### 2. User membuka halaman detail & cek ketersediaan

Di halaman detail, user memilih tanggal (check-in/check-out untuk Villa/
Glamping/Homestay/Apartment/Transport, atau tanggal + sesi untuk Lokasi
Gathering) dan jumlah tamu, lalu sistem mengecek ketersediaan **secara
real-time** — mengembalikan tersedia/tidak, jumlah malam, dan rincian harga
(subtotal, diskon kupon jika ada kode kupon dimasukkan, total, sampai berapa
komisi platform dan bagian mitra).

Khusus **Villa**: harga bisa berbeda per tanggal (mitra bisa set harga custom
untuk tanggal tertentu, mis. musim tinggi) dan ada aturan minimum menginap
per tanggal. Kategori lain (Glamping/Homestay/Apartment) pakai harga flat
per malam — belum ada kalender harga custom untuk mereka.

Khusus **Lokasi Gathering**: dipesan per slot waktu per hari (mis. "Sesi
Pagi 08:00–12:00"), bukan per malam. Endpoint cek ketersediaannya cuma
menampilkan daftar slot beserta status tersedia/tidak — belum menghitung
diskon kupon di tahap ini (kupon baru dihitung ulang saat booking benar-benar
dibuat).

Khusus **Transport**: user juga harus memilih "lepas kunci" (self-drive) atau
"dengan sopir" — sebagian kendaraan cuma menyediakan salah satu opsi.

### 3. User membuat booking

Setelah yakin dengan tanggal/slot dan harga, user menekan tombol pesan.
Sistem membuat baris booking baru dengan status **`pending_payment`** —
harga (subtotal, diskon, total, komisi, bagian mitra) dihitung ulang di sisi
server (bukan dipercaya dari input klien) dengan logika yang **sama persis**
dengan cek ketersediaan di langkah 2, supaya tidak mungkin berbeda.

Kalau ternyata di detik terakhir tanggal/slot itu sudah keburu dipesan orang
lain (race condition), booking gagal dibuat dan user diberi tahu alasannya.

### 4. User membayar

Sistem meminta Xendit membuatkan halaman invoice pembayaran (VA bank,
e-wallet, kartu kredit, dll — pilihan metode ada di halaman Xendit itu
sendiri, bukan dipilih di app). User diarahkan ke halaman itu untuk
menyelesaikan pembayaran.

Kalau user menutup halaman pembayaran tanpa bayar, atau invoice-nya expired,
**booking TIDAK otomatis dibatalkan**. Booking tetap menggantung berstatus
`pending_payment` — user harus kembali dan membuka ulang invoice yang sama
(sistem akan memakai ulang invoice pending yang sudah ada, tidak membuat
invoice baru selama yang lama masih berlaku) untuk mencoba bayar lagi.

> ⚠️ **PERLU KONFIRMASI**: tidak ada job/scheduler yang membersihkan booking
> `pending_payment` yang dibiarkan menggantung selamanya, dan tidak ada
> tombol "batalkan" untuk booking di status ini dari sisi user. Apakah ini
> memang disengaja (belum jadi prioritas) atau kekurangan yang perlu
> ditambal, sebaiknya dikonfirmasi ke pemilik produk sebelum dipakai sebagai
> acuan desain mobile.

### 5. Pembayaran sukses → booking otomatis terkonfirmasi

Begitu Xendit mengonfirmasi pembayaran sukses lewat webhook ke server (bukan
dari sisi klien/app — jadi keamanannya tidak bergantung pada app), sistem
langsung:

1. Mengubah status booking jadi **`dikonfirmasi`** — **tanpa ada langkah
   persetujuan dari mitra sama sekali**.
2. Mengirim **email** ke user berisi 2 lampiran PDF: **Voucher** (untuk
   ditunjukkan ke mitra saat check-in/serah terima) dan **Receipt** (bukti
   pembayaran resmi). Email ini terpisah dari notifikasi generik, dan kedua
   PDF-nya juga bisa diunduh ulang kapan saja dari halaman detail booking.
3. Mengirim notifikasi in-app ke **user** ("pembayaran berhasil") dan ke
   **mitra** ("ada booking baru yang sudah dikonfirmasi otomatis").

Kalau pembayaran gagal/kadaluarsa, sistem hanya menandai catatan
pembayarannya sebagai gagal — status booking-nya sendiri tidak berubah dari
`pending_payment` (lihat catatan di langkah 4).

### 6. Masa menunggu check-in

Selama status masih `dikonfirmasi`, ada dua hal otomatis berjalan di
belakang layar:

- **H-1 sebelum check-in**: user dikirimi notifikasi pengingat.
- **User bisa membatalkan kapan saja** selama status masih `dikonfirmasi`
  (lihat bagian Pembatalan di bawah). Setelah dibatalkan, tidak bisa
  diapa-apakan lagi (status final).

### 7. Check-in & check-out (otomatis berdasarkan tanggal)

Setiap hari, sistem menjalankan pengecekan terjadwal:

- Booking `dikonfirmasi` yang tanggal check-in-nya sudah lewat → otomatis
  jadi **`checked_in`**.
- Booking `dikonfirmasi`/`checked_in` yang tanggal check-out-nya sudah lewat
  → otomatis jadi **`selesai`**, sekaligus mengirim notifikasi ajakan
  memberi review ke user.

Tidak ada tombol "check-in"/"check-out" manual yang ditekan mitra atau user
di sistem ini — murni berdasarkan tanggal yang lewat.

### 8. Setelah selesai — review & payout mitra

- User bisa memberi **rating (1–5) & ulasan** untuk booking yang sudah
  `selesai` (satu kali per booking). Mitra bisa membalas ulasan tersebut.
- Booking yang `selesai` masuk antrean payout mitra: bagian pendapatan mitra
  (`total_price` dikurangi komisi platform) akan dicairkan ke rekening mitra
  lewat Xendit Disbursement API pada jadwal payout berikutnya (**tanggal 1
  atau 15**, mana yang lebih dulu tiba) — proses ini otomatis, mengumpulkan
  semua booking `selesai` milik mitra tsb dari **semua kategori listing**
  sekaligus jadi satu pencairan.
- Kalau pencairan ke rekening mitra gagal (mis. data rekening salah, saldo
  platform di Xendit kurang), tidak dibiarkan gagal diam-diam — tercatat
  sebagai payout gagal yang bisa dilihat & dicoba ulang oleh admin.

### 9. Pembatalan oleh user

Selama status booking masih `dikonfirmasi`, user boleh membatalkan kapan
saja. Setelah tombol batal ditekan:

1. Sistem menghitung selisih hari dari **hari ini** ke `check_in_date`.
2. **Selisih ≥ 2 hari (H-2 atau lebih)** → user mendapat refund **85%** dari
   total yang sudah dibayar.
3. **Selisih < 2 hari** → **tidak ada refund** (0%), karena mitra sudah
   berkomitmen atas jadwal itu terlalu dekat dengan waktunya.
4. Status booking berubah jadi **`dibatalkan_user`** — final, tidak ada jalan
   balik.
5. Kalau ada refund yang harus dibayar (kasus poin 2), sistem otomatis minta
   Xendit memprosesnya. Kalau ternyata 0% (kasus poin 3), tidak ada
   permintaan refund yang dikirim ke Xendit sama sekali — cukup ubah status.
6. Mitra mendapat notifikasi bahwa booking-nya dibatalkan user, beserta
   persentase refund yang berlaku.

**Mitra sama sekali tidak punya opsi untuk membatalkan booking** — begitu
booking terkonfirmasi (otomatis via pembayaran), komitmennya mengikat dari
sisi mitra.

---

## Pertanyaan yang Sering Muncul (untuk tim non-teknis)

**Q: Apakah mitra harus menekan tombol "terima" dulu sebelum tamu bisa
datang?**
Tidak. Sejak perubahan kebijakan investor, tidak ada lagi langkah
persetujuan manual dari mitra per booking. Kalau tanggal/slot itu tersedia
saat user booking, dan user berhasil bayar, itu otomatis terkonfirmasi.

**Q: Kalau user telat bayar atau batal di tengah jalan bayar, apa yang
terjadi ke jadwalnya?**
Tanggal/slot itu masih dianggap "terpakai" (booking tetap ada di database
dengan status `pending_payment`, dan tanggal itu dianggap tidak tersedia
untuk booking lain) sampai — ⚠️ **PERLU KONFIRMASI**: sejauh riset kode,
tidak ditemukan mekanisme yang membebaskan tanggal ini secara otomatis kalau
user tidak jadi bayar. Ini titik yang perlu didiskusikan dengan pemilik
produk.

**Q: Kalau mitra berubah pikiran setelah booking terkonfirmasi (mis. villa
rusak, double-booking manual di luar sistem), apa yang bisa dilakukan?**
Tidak ada mekanisme di sistem untuk mitra membatalkan dari sisinya. Ini
perlu ditangani manual oleh admin/CS di luar alur normal aplikasi (⚠️ **PERLU
KONFIRMASI** ke tim produk soal SOP-nya, karena tidak ada endpoint/fitur
untuk skenario ini di kode saat ini).

**Q: Apa bedanya Voucher dan Receipt?**
Voucher adalah dokumen yang **ditunjukkan ke mitra** saat check-in/serah
terima (berisi detail listing, tanggal, nama tamu). Receipt adalah **bukti
pembayaran** resmi (rincian harga, status lunas) untuk keperluan
administrasi/pajak pengguna. Keduanya file PDF terpisah, dikirim otomatis
lewat email begitu pembayaran sukses, dan bisa diunduh ulang kapan saja dari
halaman detail booking.

---

## Perbedaan Signifikan dari Dokumen Lama

Ringkasan singkat (detail teknis lengkap ada di `API_CONTRACT.md` bagian 10):

1. **Konfirmasi mitra manual dihapus total.** PRD lama menjelaskan alur
   "mitra terima/tolak dalam 24 jam, auto-cancel + refund 100% kalau
   timeout". Ini semua sudah tidak ada di kode — digantikan konfirmasi
   otomatis begitu bayar sukses.
2. **Kebijakan refund jadi lebih sederhana.** PRD lama punya 2 skenario
   refund (100% kalau masih "menunggu konfirmasi", 85%/0% kalau sudah
   "dikonfirmasi"). Sekarang cuma ada 1 skenario karena status "menunggu
   konfirmasi" sudah tidak pernah terjadi: **85% jika H-2 atau lebih, 0%
   jika kurang dari itu.**
3. **Kategori listing bertambah dari 1 jadi 6.** PRD lama hanya bicara soal
   Villa. Sekarang ada Villa, Glamping, Homestay, Apartment, Lokasi
   Gathering, dan Transport — masing-masing dengan sedikit perbedaan pola
   booking (per-malam vs per-hari vs per-slot).
4. **Sistem kupon/diskon** — fitur baru yang tidak pernah disebut di PRD.
5. **Voucher & Receipt PDF otomatis** — fitur baru yang tidak pernah disebut
   di PRD/CLAUDE.md.
6. **Komisi platform bisa berbeda per mitra** (admin bisa override dari
   default 10%) — PRD/CLAUDE.md menyiratkan komisi selalu flat 10%.
7. **Jadwal payout tanggal 1 & 15 sudah final** (dikonfigurasi langsung di
   scheduler) — bukan lagi sekadar "misal" seperti tertulis di CLAUDE.md.
