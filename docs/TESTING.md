# Hasil dan panduan pengujian

Verifikasi dilakukan pada 2 Oktober 2026 dengan PHP 8.2.32, Laravel 12.69.3, dan MariaDB 10.11.18 (InnoDB). Hasil PHPUnit: **30 tests, 160 assertions, lulus**. Database yang dipakai adalah MariaDB asli, bukan mock/SQLite.

## Bukti pengujian otomatis

| Area | Hasil |
| --- | --- |
| Tambah barang, kode dinormalisasi, stok 0, UUID, QR SVG/label | Lulus |
| Duplikat kode dan posisi barang aktif | Ditolak; constraint database juga diuji langsung |
| IN 10 kemudian OUT 3 | Stok akhir 7; scan dan draft quantity tidak mengubah stok |
| OUT stok 0 | STOCK_EMPTY, tanpa pending baru |
| OUT melebihi stok | STOCK_NOT_ENOUGH; stok dan status pending tetap |
| Quantity 0, negatif, teks, kosong, null, pecahan, overflow | INVALID_QUANTITY; stok tetap |
| Double scan kedua mode | Ditolak selama pending; boleh lagi setelah final |
| Cancel, approve ulang, cancel setelah approve | State transition ilegal ditolak |
| Stok menipis pada 2 | Status benar; label moving tetap manual |
| Default A–Z, FAST saja, SLOW saja, filter gabungan | Lulus |
| Total OUT dan urutan stok | Hanya OUT APPROVED dihitung |
| Lokasi numerik dan snapshot histori sesudah pindah/ganti kode | Lulus |
| Barang nonaktif, posisi dilepas, aktivasi posisi terisi | Sesuai aturan |
| Metadata/QR diblokir saat pending; nonaktif stok nonzero ditolak | Lulus |
| Token lama sesudah regenerate | Ditolak |
| Constraint stok negatif, posisi ganda, pending ganda | Ditolak database |
| Login, scanner dan endpoint private | Sesi wajib |
| Semua halaman, pending JSON, histori dan CSV | Respons sukses |
| Seeder dijalankan ulang | Tidak menggandakan produk/stok/histori |
| Audit rantai stok | Cocok |

### Concurrency nyata

Dua proses PHP dijalankan bersamaan, dengan koneksi database terpisah dan barrier waktu:

1. Scan/scan barang sama: satu berhasil, satu TRANSACTION_PENDING; hanya satu row pending.
2. Approve/approve transaksi sama: satu berhasil, satu TRANSACTION_ALREADY_PROCESSED; stok berubah sekali.
3. Approve/cancel: satu pemenang; status dan stok konsisten dengan pemenang.

### Rollback nyata

Test memasang trigger sementara yang sengaja melempar SQL error saat UPDATE produk atau UPDATE stock_transactions. Sesudah kegagalan approval, stok tidak berubah dan transaksi tetap PENDING. Trigger hanya dipasang di database test dan dihapus dalam `finally`.

### HTTP dan JavaScript end-to-end

Pengujian tambahan menjalankan Laravel HTTP server, dua cookie jar/sesi terpisah, dan JavaScript UI pada DOM jsdom. Ini **bukan pengujian visual browser**. Canvas native dipakai untuk menjalankan decoder QR asli dari bundle html5-qrcode.

- QR SVG backend dirasterkan menjadi PNG, kemudian didekode melalui `Html5Qrcode.scanFile()` asli; token menghasilkan pending lewat UI scanner.
- Pending muncul pada DOM komputer melalui polling tanpa refresh manual.
- Quantity 10 tetap terisi setelah polling berikutnya; preview stok benar; approve IN 10.
- Ganti mode OUT, kirim token, preview dan approve OUT 3; hasil stok 7.
- Quantity OUT 11 ketika stok 10 membuat tombol approve nonaktif; backend juga diuji terpisah untuk penolakan OUT berlebih.
- Cancel dari UI berfungsi; audit seluruh histori tetap cocok.
- Request HTTP tanpa CSRF ditolak 419; client tanpa session ditolak 401.
- Dropdown Ruangan → Blok → Rak → Posisi menghasilkan A.02.01.
- Tidak ditemukan error JavaScript dalam skenario tersebut.

Hasil ringkas disimpan di `http-dom-results.json`. Output suite PHP ada di `phpunit-results.txt`.

### Instalasi database manual

`database.sql` dibuat dari SHOW CREATE TABLE skema hasil migration dan data demo yang tercatat melalui service. Import ke database kosong MariaDB berhasil; jumlah produk 3, posisi 6, histori IN 2, migration 4; stok 25/2/0 sesuai. Tabel users kosong, sehingga pengguna menetapkan password sendiri melalui `warehouse:admin`.

## Batas verifikasi

Belum diuji di Windows/Laragon milik pengguna, MySQL versi lain, smartphone fisik, kamera belakang nyata, izin kamera Android/iOS, trust certificate HP, audio/getaran perangkat, kualitas label cetak, atau layout visual pada browser grafis. Runtime browser grafis pada lingkungan pengerjaan tidak dapat dijalankan. Tidak ada hasil pengujian visual/perangkat yang diklaim di sini.

## Menjalankan suite di Laragon

1. Buat database kosong `warehouse_stock_test`.
2. Salin `.env.testing.example` menjadi `.env.testing`; sesuaikan host/port/password.
3. Jalankan `php artisan config:clear`, lalu `php artisan test`.
4. Suite hanya menerima database `warehouse_stock_test` dan mengosongkan data test antar skenario. Akun DB test membutuhkan CREATE/ALTER/DROP/TRIGGER. Subprocess concurrency memerlukan `proc_open` PHP.
5. Jalankan `php artisan warehouse:audit` pada database yang ingin diperiksa tanpa mengubahnya.

## Uji penerimaan Windows + satu HP

- [ ] Website memakai document root public; halaman login dan semua menu dapat diakses.
- [ ] Buat barang baru pada posisi kosong: stok 0, kode unik dan QR dicetak.
- [ ] Coba kode/posisi yang sama: ditolak dengan informasi barang pemilik posisi.
- [ ] Login HP melalui HTTPS yang dipercaya, izinkan kamera belakang.
- [ ] Scan IN → pending terlihat otomatis dalam sekitar 3 detik.
- [ ] Scan barang sama dengan IN/OUT sebelum selesai → ditolak pending.
- [ ] Quantity 10 → preview 0 ke 10 → approve → stok 10.
- [ ] Scan OUT, quantity 3 → stok 7.
- [ ] Cancel scan → stok tetap; QR bisa discan lagi.
- [ ] Uji stok 2 / quantity OUT 5 → ditolak dan stok tetap 2.
- [ ] Uji OUT stok 0 → STOK HABIS, tanpa pending.
- [ ] Uji stok 5 / OUT 3 → stok 2, label merah MENIPIS.
- [ ] Uji sorting lokasi A.01.01, A.01.02, A.01.10, A.02.01, B.01.01.
- [ ] Uji default nama serta FAST/SLOW terfilter; OUT terbanyak hanya APPROVED.
- [ ] Pindahkan barang dan edit kode; lokasi/kode di histori lama tetap.
- [ ] Adjustment 10 ke 8 dengan alasan → delta -2 dan histori tersimpan.
- [ ] Export mengikuti filter; buka CSV pada Excel.
- [ ] Ganti mode tanpa logout, beep/getaran bila didukung, fallback foto saat kamera ditolak.
- [ ] Putuskan Wi-Fi sementara; status sinkronisasi menampilkan kegagalan, stok tidak berubah tanpa approval sukses.
- [ ] Audit stok lulus dan backup/pemulihan berhasil pada database terpisah.
