# ThriftVerse — Web Preloved (Tawar · Barter · COD)

Laravel 10 + MySQL. Tampilan: Blade + Tailwind (CDN) + Alpine.js (CDN) → **tidak perlu `npm install`**, tetapi butuh internet saat membuka halaman (CDN Tailwind/Alpine/Google Fonts).

## Cara menjalankan di Laragon (Windows)
1. Ekstrak folder ini ke `C:\laragon\www\thriftverse` (Laragon otomatis membuat `http://thriftverse.test`).
2. Klik **Start All** di Laragon (Apache/Nginx + MySQL). PHP minimal 8.1 dengan ekstensi `pdo_mysql`, `mbstring`, `gd`/`fileinfo` (bawaan Laragon).
3. Buka **Terminal** Laragon di folder proyek, jalankan: `setup.bat`
   (atau manual: `copy .env.example .env` → `php artisan key:generate` → buat database `thriftverse` → `php artisan migrate:fresh --seed` → `php artisan storage:link`).
4. Buka `http://thriftverse.test` (atau `php artisan serve` → `http://127.0.0.1:8000`).
5. Opsional (jadwal otomatis kedaluwarsa tawaran/pesanan): `php artisan schedule:work`, atau Task Scheduler Windows tiap menit: `php artisan schedule:run`. Bisa juga manual: `php artisan thriftverse:housekeeping`.

## Akun demo (sandi `password123`)
| Peran | Email | Catatan |
|---|---|---|
| Admin/Kurator | admin@thriftverse.app | Portal `/admin` |
| Penjual+pembeli | rayhan@thriftverse.app | Punya antrean 3 tawaran di hoodie Nike |
| Pembeli | dimas@thriftverse.app, reza@…, kolektor@… | Area Jakarta Selatan/Pusat |
| Penjual | archive@…, grunge@…, bandung@thriftverse.app | |

## Alur yang sudah berjalan (sesuai SRS v1.1)
- **Akun**: registrasi, login email/username, profil (area, mode penjual, rekening), satu akun = pembeli + penjual.
- **Listing & grading**: form checklist → grade A/B/C otomatis (FR-011/012), 4 foto wajib + foto minus untuk B/C, opsi transaksi (Harga Tetap / Bisa Tawar / Barter / COD) bisa dikombinasikan (FR-019), kurasi admin + lencana terverifikasi (FR-017).
- **Katalog**: pencarian kata kunci, filter (mode, grade, harga, kategori, ukuran, area+radius), urutan, wishlist, riwayat harga + estimasi harga layak.
- **Tawar ala inDrive**: indikator Terlalu Rendah/Wajar/Tinggi, minimum 30% harga, 5 putaran × 24 jam, tawar balik dua arah, banyak penawar → penjual pilih satu & yang lain otomatis ditutup, auto-terima harga minimum rahasia, penahanan barang 24/48 jam, batas 10 tawaran aktif.
- **Pesanan**: beli langsung, checkout (ekspedisi Rp24.000 simulasi / COD Rp0), rekber (pembayaran **simulasi sandbox**), input resi, konfirmasi terima / auto-selesai, batal otomatis (belum bayar & tidak dikirim 2×24 jam + penalti skor), komplain + bukti foto → admin, tab "Pesanan Saya/Masuk", ulasan + kesesuaian grade.
- **Barter / tukar tambah**: 1–3 barang, indikator selisih nilai (Seimbang ≤15%, Perlu Tambahan ≤30%, Timpang), uang tambahan dinegosiasikan, ajukan balik (5 putaran × 48 jam), persetujuan ganda, barang dikunci selama barter aktif, serah terima ketemuan.
- **COD**: radius COD dari area (default 10 km), titik temu aman, jadwal usul/konfirmasi, **kode 6 digit + QR hanya terlihat pembeli (terenkripsi di DB)**, penjual memasukkan kode → selesai, "Saya Sudah Tiba" (GPS opsional), tolak di tempat (foto bukti), gagal bertemu >30 menit, laporan masalah, nomor telepon disamarkan sampai jadwal disepakati.
- **Admin**: statistik, antrean kurasi grade (setujui/turunkan/tolak), sengketa & laporan (refund / cairkan / tutup), manajemen pengguna (suspend).
- Parameter bisnis ada di `config/thriftverse.php`.

## Belum diimplementasikan / disederhanakan (jujur)
- Keranjang multi-item per penjual (FR-040): saat ini 1 barang = 1 pesanan (barang preloved umumnya stok 1).
- Payment gateway nyata & API ongkir nyata: pembayaran dan ongkir **disimulasikan** (`OrderService::fund`, `config('thriftverse.shipping_flat')`).
- Barter metode **kirim** (FR-087) dan kode serah terima dua arah (FR-088): saat ini hanya ketemuan dengan satu kode.
- Chat dalam platform (FR-050), voucher (FR-049), unggah massal CSV, login Google/Apple, auto price drop & saran turun harga (FR-033/035), grafik harga masih garis sederhana, UI pengaturan parameter admin, balasan penjual atas komplain (FR-047), tombol lapor darurat.
- Filter radius memakai kotak pembatas (perkiraan), bukan jarak melingkar presisi.
- Foto produk seed memakai placeholder (unggah foto asli lewat form listing).

## Pengujian
`php artisan test` — 13 tes alur (tawar, barter, COD, ekspedisi, sengketa, admin). Sudah dijalankan lolos di MySQL/MariaDB 10.11 dan SQLite.


## Fitur tambahan (update)

- **Peta interaktif** (Leaflet + OpenStreetMap, tanpa API key): checkout COD, form listing, dan halaman jadwal COD. Klik penanda untuk memilih titik temu; lingkaran hijau = radius COD penjual.
- **Auto price drop**: penjual mengaktifkan di form listing (turun tiap 3/7/14 hari, 5-20%, sampai harga terendah). Dijalankan oleh `php artisan thriftverse:housekeeping` (terjadwal tiap 5 menit lewat scheduler). Tercatat di riwayat harga (`source = auto`) dan wishlist diberi tahu.
- **Voucher**: persen/nominal, minimal belanja, maksimal potongan, kuota, masa berlaku, sekali pakai per pengguna. Admin mengelola di menu Voucher. Voucher dikembalikan bila seluruh pesanan yang memakainya dibatalkan. Demo: `THRIFT10`, `HEMAT25K`.
- **Chat** per listing (pembeli ↔ penjual), refresh tiap 5 detik (polling, belum websocket). Nomor telepon dan tautan WhatsApp/Telegram disamarkan otomatis.
- **Keranjang multi-item**: hanya barang berharga tetap. Satu pesanan dibuat per barang; ongkir dan biaya layanan sekali per penjual; COD per penjual (bayar via rekber); voucher dibagi proporsional; ada tombol "Bayar semua".

Batasan: pembayaran/ongkir masih simulasi; COD tunai tidak tersedia di keranjang; chat belum real-time (polling); upload gambar di chat belum ada.
