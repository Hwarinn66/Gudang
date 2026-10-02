# Warehouse Stock — Sistem Stok Gudang QR

Aplikasi Laravel untuk satu operator, komputer Windows/Laragon, dan satu HP scanner. Semua perangkat menggunakan backend dan database yang sama. Bahasa UI Indonesia.

**Scan → PENDING → input quantity di komputer → APPROVE/CANCEL.** Scan tidak mengubah stok. Stok berubah melalui IN, OUT, atau adjustment yang tercatat.

## Isi paket

- Laravel 12, PHP, Blade, Tailwind CSS hasil build, dan JavaScript lokal.
- Master kategori, ruangan, blok, rak, posisi, dan barang; foto opsional.
- Kode barang unik; satu posisi untuk satu barang aktif; QR UUID per barang.
- QR SVG untuk download/cetak/regenerate; scanner kamera belakang, unggah foto, dan input scanner fisik.
- Mode IN/OUT, feedback beep/getar bila browser mendukung, session login dan CSRF.
- Pending realtime melalui polling 3 detik; approve, cancel, preview stok.
- Histori IN dan OUT terpisah, adjustment, dashboard, search/filter/sort/pagination, CSV mengikuti filter.
- Migration, seeder demo, `database.sql`, `.env.example`, pengujian MariaDB, dan konfigurasi Laragon.

**Node.js hanya diperlukan jika mengubah/membangun aset CSS/JS.** Backend berjalan seluruhnya dengan PHP/Laravel. Paket sudah berisi aset build di `public/assets`; instalasi biasa tidak perlu Node, Vite, Redis, worker antrean, atau akses CDN. Dependensi Composer diinstal melalui `composer install` menggunakan lockfile.

## Persyaratan

| Komponen | Persyaratan |
| --- | --- |
| PHP | 8.2 atau lebih baru yang didukung Laravel 12; 64-bit |
| Ekstensi PHP | PDO, pdo_mysql, mbstring, openssl, tokenizer, xml, dom, ctype, fileinfo, filter, session, iconv, curl; zip untuk Composer |
| Composer | Composer 2 |
| Framework | Laravel 12, versi terikat dalam `composer.lock` |
| Database | MySQL 8.0.16+ atau MariaDB 10.6+, InnoDB, utf8mb4 |
| Server | Laragon Windows, Apache direkomendasikan; Nginx juga dapat digunakan |
| Browser desktop | Browser modern dengan JavaScript |
| Browser HP | Chrome Android atau Safari iOS modern; kamera melalui HTTPS terpercaya |
| Build aset opsional | Node.js dan npm; bukan backend |

Pengujian yang dilakukan dicatat di [docs/TESTING.md](docs/TESTING.md). Pengujian Windows/Laragon serta kamera fisik tetap dilakukan di perangkat Anda.

## Instalasi Laragon — migration (direkomendasikan)

1. Instal/buka Laragon. Pilih versi PHP yang sesuai. Klik **Start All** untuk Apache dan MySQL/MariaDB.
2. Ekstrak folder `warehouse-stock` ke `C:\laragon\www\warehouse-stock`. Pastikan `artisan` berada langsung di dalam folder itu, bukan folder ZIP bertingkat.
3. Buka **Terminal Laragon** agar PHP dan Composer memakai versi Laragon:

```bat
cd C:\laragon\www\warehouse-stock
php -v
composer --version
composer install
copy .env.example .env
```

4. Buat database kosong melalui HeidiSQL/phpMyAdmin atau terminal MySQL:

```sql
CREATE DATABASE warehouse_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. Edit `.env`:

```dotenv
APP_NAME="Warehouse Stock"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://warehouse-stock.test
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=warehouse_stock
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

`DB_CONNECTION=mysql` bekerja untuk MySQL maupun MariaDB. Sesuaikan port/password dengan Laragon. `root` tanpa password adalah contoh development lokal, bukan konfigurasi server publik.

6. Jalankan:

```bat
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan warehouse:admin
php artisan warehouse:audit
```

`warehouse:admin` meminta nama, username, email, dan password minimal 12 karakter. Password di-hash; **tidak ada password bawaan**. Satu akun dapat login di komputer dan HP. Jika tidak membutuhkan demo, lewati `db:seed`; tambah master lokasi/kategori dari UI setelah login.

7. Klik **Reload** Laragon. Buka `http://warehouse-stock.test`. Pastikan document root menunjuk ke **`C:/laragon/www/warehouse-stock/public`**, bukan root proyek. Jika automatic virtual host Laragon belum benar, gunakan contoh [warehouse-http.conf](docs/laragon/warehouse-http.conf). Aktifkan `mod_rewrite` dan `AllowOverride All`. Hindari dua virtual host dengan `ServerName` sama. Laragon mengatur domain `.test` di komputer; domain ini tidak otomatis dikenali HP.
8. Login dengan akun yang baru dibuat. Desktop menuju dashboard; user agent mobile menuju scanner, atau buka `/scanner` secara langsung.

Tidak perlu `php artisan storage:link`: foto disimpan di `storage/app/private/products` dan disajikan oleh route yang memerlukan login. Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh proses PHP.

## Alternatif instalasi — import database.sql

Gunakan **satu metode** untuk database awal: migration, atau import SQL.

1. Instal dependensi, buat `.env`, `APP_KEY`, dan database kosong seperti di atas.
2. Pilih database `warehouse_stock` di HeidiSQL/phpMyAdmin, lalu import `database.sql`. Alternatif dari CMD:

```bat
mysql -u root -p warehouse_stock < database.sql
```

Untuk password kosong, gunakan pengaturan koneksi Laragon yang sesuai. `mysql` tersedia melalui Terminal Laragon; nama executable dapat `mariadb` pada instalasi tertentu.

3. SQL memuat tabel, foreign key, index, generated unique columns, CHECK constraints, data demo, histori IN demo, serta tabel `migrations` yang sinkron. SQL tidak melakukan DROP pada tabel lama dan harus diimport ke database kosong. Tabel `users` sengaja kosong.
4. Jalankan:

```bat
php artisan warehouse:admin
php artisan migrate:status
php artisan warehouse:audit
```

Jangan menjalankan `migrate:fresh` pada database berisi data operasional. Untuk pembaruan berikutnya gunakan migration tambahan dan `php artisan migrate`. Seeder idempotent: tidak menambahkan stok lagi pada barang demo yang sudah ada.

## Data demo

| Kode | Nama | Spesifikasi | Lokasi | Stok | Moving |
| --- | --- | --- | --- | ---: | --- |
| A0001 | Ember | 1 KG | A.01.01 | 25 PCS | FAST |
| A0002 | Ember | 1.2 KG | A.01.02 | 2 PCS | FAST |
| A0003 | Ember | 2 KG | A.01.03 | 0 PCS | SLOW |

Ruangan 1 berisi Blok A dan B; rak A.01, A.02, B.01; posisi A.01.01–03, A.02.01–02, B.01.01. Stok demo 25 dan 2 dibuat melalui transaksi IN APPROVED dari stok awal 0. Stok 0 tidak memerlukan transaksi palsu. QR demo memakai UUID.

Karena A0001 sudah ada dalam demo, gunakan kode baru untuk mencoba tambah barang, atau gunakan database kosong tanpa seeder saat mengikuti skenario tambah A0001.

## Akses HP pada jaringan yang sama

1. Hubungkan HP dan komputer ke Wi-Fi/LAN yang sama. Hindari guest Wi-Fi dengan client/AP isolation.
2. Di komputer jalankan `ipconfig`. Ambil **IPv4** adapter Wi-Fi/Ethernet aktif, misalnya `192.168.1.10`.
3. Untuk memeriksa koneksi sebelum HTTPS, jalankan dari folder proyek:

```bat
php artisan serve --host=0.0.0.0 --port=8000
```

Komputer: `http://127.0.0.1:8000`; HP: `http://192.168.1.10:8000/scanner`. Server ini memakai `.env` dan database Laragon yang sama. Jangan memakai `localhost` di HP karena itu menunjuk ke HP sendiri. Tidak perlu menambahkan `/warehouse-stock/public` pada URL server Artisan.

4. Izinkan koneksi masuk TCP 8000 pada profil Windows Firewall **Private**, dengan remote scope LocalSubnet. Jangan membuka port database 3306 ke HP; hanya web server yang diakses HP.
5. Kamera live biasanya **tidak tersedia** pada HTTP alamat IP. HTTP ini hanya pengecekan konektivitas. Gunakan HTTPS berikut untuk login/scanner rutin. Fallback unggah foto tersedia ketika izin kamera ditolak atau QR sulit dibaca.

## HTTPS lokal yang dipercaya HP (Apache Laragon)

`getUserMedia()` memerlukan secure context. Sertifikat yang hanya dipercaya komputer belum tentu dipercaya HP. Menekan “continue anyway” pada peringatan sertifikat bukan pengganti pemasangan CA yang benar.

1. Instal [mkcert resmi](https://github.com/FiloSottile/mkcert), misalnya dengan Chocolatey atau executable Windows resmi. Buka terminal yang memiliki akses menambahkan CA lokal:

```bat
mkcert -install
mkdir C:\laragon\etc\ssl\warehouse
mkcert -cert-file C:\laragon\etc\ssl\warehouse\warehouse.pem -key-file C:\laragon\etc\ssl\warehouse\warehouse-key.pem warehouse-stock.test localhost 127.0.0.1 192.168.1.10
mkcert -CAROOT
```

Ganti IP contoh dengan IPv4 komputer. Jika IP berubah, buat ulang sertifikat dengan IP baru. Reservasi DHCP pada router membantu menjaga alamat tetap.

2. Salin contoh [warehouse-https.conf](docs/laragon/warehouse-https.conf) ke `C:\laragon\etc\apache2\sites-enabled\warehouse-https.conf`. Sesuaikan IP dan path. Contoh memakai port **8443** untuk menghindari bentrok 443. Pastikan `Listen 8443` hanya didefinisikan sekali. Aktifkan modul Apache `ssl_module`, `socache_shmcb_module`, dan `rewrite_module` dari konfigurasi Laragon. Restart/Reload Apache.
3. Buat aturan Windows Defender Firewall inbound TCP **8443**, profil **Private**, scope **LocalSubnet**. Jangan mematikan firewall seluruhnya.
4. Dari folder yang ditunjukkan `mkcert -CAROOT`, kirim **hanya `rootCA.pem`** ke HP melalui cara lokal yang Anda percayai (bisa dinamai `rootCA.crt`). **Jangan menyalin `rootCA-key.pem` atau private key server ke HP.**
5. Android: pasang sebagai sertifikat CA melalui Settings → Security/Privacy → Encryption & credentials → Install a certificate → CA certificate; nama menu berbeda antarprodusen. Tutup dan buka kembali Chrome. Perangkat yang dikelola perusahaan mungkin melarang CA pengguna.
6. iOS: buka sertifikat, instal profile di Settings → Profile Downloaded, lalu aktifkan kepercayaan di Settings → General → About → Certificate Trust Settings. Tutup dan buka kembali Safari.
7. Ubah `.env` untuk penggunaan HTTPS:

```dotenv
APP_URL=https://192.168.1.10:8443
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

```bat
php artisan optimize:clear
```

8. Komputer dan HP membuka **`https://192.168.1.10:8443`**, login, kemudian `/scanner`. Pastikan tidak ada peringatan sertifikat. Pilih IN/OUT dan izinkan kamera. Domain `warehouse-stock.test` hanya dapat dipakai HP jika Anda juga mengatur DNS lokal; menggunakan IP bersertifikat menghindari kebutuhan itu.
9. Setelah cookie secure diaktifkan, gunakan HTTPS di kedua perangkat. Akses HTTP tidak akan membawa cookie login tersebut.

Bila menggunakan Nginx, contoh [warehouse-nginx.conf](docs/laragon/warehouse-nginx.conf) menyediakan document root `public`, TLS, dan `try_files`. Sesuaikan alamat/port PHP FastCGI dengan Laragon, lalu uji konfigurasi sebelum reload. Apache dan Nginx tidak boleh mendengarkan port yang sama secara bersamaan.

## Alur penggunaan

1. Buat kategori dan lokasi berurutan: ruangan, blok, rak, posisi. Blok memakai huruf kapital; nomor rak/posisi 1–999. Format minimal dua digit: `A.01.01`, `A.01.10`, `A.100.01`. Kode lokasi dapat sama di ruangan berbeda; UI/histori selalu menyertakan ruangan.
2. Tambah barang: kode unik, nama, spesifikasi/kapasitas, kategori, satuan, label manual FAST/SLOW jika diperlukan, dan posisi kosong. Stok awal otomatis 0.
3. Di detail barang buka Label QR. Download SVG atau Print QR (dapat memilih Save as PDF pada dialog browser). Atur skala cetak 100%, pertahankan ruang putih di sekeliling QR, dan uji hasil cetak. Label menampilkan kode barang besar, kapasitas, lokasi, dan ruangan.
4. HP login, pilih **BARANG MASUK** atau **BARANG KELUAR**, scan. QR hanya berisi UUID, bukan kode barang/lokasi/quantity/mode.
5. Backend mengunci produk, menolak barang tidak aktif, pending ganda, dan OUT stok 0. Jika valid, membuat PENDING dengan waktu server. QR terkunci untuk kedua mode.
6. Komputer membuka Transaksi Pending atau Dashboard. Kartu muncul dalam sekitar 3 detik saat tab aktif. Periksa kode, nama, kapasitas, ruangan/lokasi. Masukkan quantity integer >0, lihat preview, lalu Approve.
7. Backend membaca stok terkini dalam transaction + `lockForUpdate()`. Approval OUT berlebih ditolak, pending tetap tersedia untuk koreksi/cancel. Approval sukses mengubah stok dan histori dalam satu commit.
8. Batal mengubah status menjadi CANCELLED, mencatat waktu, dan membuka QR tanpa perubahan stok. Histori tetap tersimpan.
9. Setelah hasil scan, tekan **Scan Berikutnya** atau **Ganti Mode**. Ini menghindari pembacaan kamera terus-menerus; pengaman utama tetap pending di database, bukan timer.

Kamera default menggunakan arah belakang. Jika kamera gagal, scanner menampilkan pesan dan tombol coba kembali. Unggah foto QR untuk decoding lokal di browser. Scanner fisik model keyboard wedge dapat mengirim UUID ke kolom Token QR lalu Enter, dengan service backend yang sama. Jangan kirim kode `A0001` sebagai pengganti token.

Beep diaktifkan setelah interaksi memilih mode; getaran bergantung dukungan browser (tidak semua iPhone mendukung Vibration API). Ketika tab disembunyikan, kamera dihentikan; aktifkan kembali saat kembali ke halaman.

## Aturan master dan histori

- Tidak ada input stok awal/kolom edit stok. Backend menolak field `stock` dari form produk.
- FAST/SLOW murni label manual; statistik Total OUT tidak mengubah label.
- Total OUT = `SUM(quantity)` transaksi OUT APPROVED, sepanjang histori.
- Default barang aktif diurutkan `name ASC, product_code ASC`. Pilihan Fast/Slow A–Z memfilter hanya label tersebut.
- Sorting lokasi memakai ruangan, blok, nomor rak integer, lalu nomor posisi integer, bukan string mentah.
- Search, kategori, ruangan, blok, rak, moving type, stok, status aktif, dan sorting dapat dikombinasikan. Pagination 25/50/100 mempertahankan query.
- Stok 0 = HABIS; 1–2 = MENIPIS; >2 = AMAN. Filter “Stok ≤2” mencakup HABIS.
- Stok/IN/OUT total di dashboard adalah jumlah quantity lintas satuan; bukan penjumlahan berat/nilai ekonomi.
- Lokasi/kategori/barang tidak dihapus melalui UI. Lokasi yang berisi barang aktif tidak bisa dinonaktifkan. Nonaktifkan induk membuat anak tidak tersedia sampai induk aktif kembali.
- Barang harus stok 0 dan tanpa pending sebelum dinonaktifkan. Posisi dilepas oleh barang nonaktif, dan diperiksa lagi saat aktivasi ulang. Barang nonaktif tidak bisa discan.
- Edit master, pindah lokasi, dan regenerate QR diblokir selama ada pending. Pindah lokasi memerlukan konfirmasi dan posisi tujuan kosong.
- Histori menyimpan posisi FK dan snapshot kode lokasi, ruangan, kode barang, nama, spesifikasi, satuan. Mengedit barang tidak mengubah histori lama.
- Regenerate QR membuat UUID baru dan membatalkan token lama; ganti label fisik. Stok tidak berubah.
- Adjustment memakai stok fisik absolut, selisih bertanda, alasan wajib, waktu approval, operator, UUID permintaan. Stok yang berubah sejak formulir dibuka ditolak; muat ulang untuk memeriksa ulang. Double-submit adjustment tidak membuat transaksi ganda.
- Quantity/stok maksimum 2.147.483.647; server memvalidasi batas dan overflow.
- Seluruh waktu ditulis server Laravel dalam `APP_TIMEZONE=Asia/Jakarta`. Sinkronkan jam Windows. Jangan mengganti timezone ketika database operasional sudah berisi waktu tanpa migrasi terencana.

## Arsitektur dan API

Lihat [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) untuk urutan lock, constraint, dan kontrak endpoint.

```text
app/Http/Controllers/     Controller tipis untuk HTTP/view
app/Http/Requests/        Validasi input
app/Models/               Relasi Eloquent
app/Services/             StockTransactionService, ProductService, QR, penomoran
app/Queries/              Query filter/sort produk dan histori
app/Support/              Perhitungan stok dan CSV
resources/views/          Blade desktop + scanner mobile
resources/css/           Sumber Tailwind
public/assets/           CSS build + JS + scanner vendor lokal
routes/web.php           Route WEB dan API AJAX dengan session + CSRF
routes/console.php       Pembuatan akun, reset password, audit stok
database/migrations/     Sumber skema
database/seeders/        Data demo melalui service stok
```

Polling berada pada transport UI; aturan stok tidak bergantung kamera atau polling. Halaman tidak mengubah nilai input quantity ketika pending diperbarui. Polling berhenti ketika tab tersembunyi dan tidak mengirim request tumpang tindih. Error koneksi terlihat pada status sinkronisasi. Bila respons approve timeout, periksa pending/histori sebelum mengulang; backend menolak transaksi yang sudah selesai.

## Pengujian

Buat **database test terpisah** `warehouse_stock_test`. Pengujian membuat/menghapus tabel di database itu. Jangan memakai database operasional.

```sql
CREATE DATABASE warehouse_stock_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bat
copy .env.testing.example .env.testing
php artisan config:clear
php artisan test
```

Sesuaikan `.env.testing` dengan koneksi Laragon. `phpunit.xml` dan `Tests/TestCase.php` memaksa serta memeriksa nama database test. Uji concurrency memakai subprocess PHP terpisah, sehingga memerlukan `proc_open` aktif. Tests menjalankan DB MySQL/MariaDB, bukan SQLite.

```bat
php artisan warehouse:audit
```

Audit membaca rantai `stock_before → stock_after` transaksi APPROVED dan mencocokkan stok produk. Tidak mengubah data. Bila gagal, investigasi/restore backup; jangan memperbaiki melalui update SQL tanpa histori.

## Mengubah aset

Untuk mengubah Tailwind atau memperbarui scanner lokal:

```bat
npm ci
npm run build
```

Ini hanya menghasilkan aset statis. Aplikasi tetap dijalankan Apache/Nginx/PHP. Tailwind 3.4.17 dan html5-qrcode 2.3.8 terkunci pada `package-lock.json`; QR backend chillerlan/php-qrcode terkunci pada `composer.lock`. Jalankan `composer audit` secara berkala dan uji perubahan dependensi sebelum digunakan di gudang.

## Backup dan pemeliharaan

Backup database, `storage/app/private/products`, serta `.env` (khususnya APP_KEY) secara terpisah dan terlindungi. Contoh backup DB dari Terminal Laragon:

```bat
mysqldump -u root -p --single-transaction --routines --triggers warehouse_stock > warehouse-stock-backup.sql
```

Gunakan backup pada jam tanpa perubahan master/schema. Simpan salinan di media berbeda. Uji pemulihan pada database terpisah sebelum dibutuhkan. Jangan mengganti APP_KEY pada database aktif tanpa rencana karena sesi terenkripsi/cookie lama akan tidak berlaku. Foto tidak tersimpan dalam SQL.

## Troubleshooting

| Masalah | Langkah penanganan |
| --- | --- |
| MySQL connection refused / gagal connect | Start MySQL/MariaDB di Laragon; cek host `127.0.0.1`, port aktual, konflik service Windows. Jalankan `php artisan optimize:clear` setelah edit env. |
| Access denied MySQL | Periksa username/password dan hak akses database; password kosong hanya bila Laragon memang demikian. Jangan membuka database ke jaringan untuk menyelesaikannya. |
| Unknown database | Buat database sesuai `DB_DATABASE` sebelum migration/import. |
| Migration gagal | Pastikan MySQL 8.0.16+/MariaDB 10.6+, InnoDB, database awal kosong, dan tidak mencampur SQL import dengan migration awal. Cek `migrate:status` dan pesan detail pada log. Backup sebelum memperbaiki schema. |
| Composer dependency gagal | Cek PHP CLI dengan `php -v`/`php --ini`; aktifkan ekstensi yang disebut; pastikan Composer 2 dan internet tersedia. Jangan memakai `--ignore-platform-reqs`. |
| APP_KEY belum ada | Jalankan `php artisan key:generate` setelah menyalin `.env`. |
| Permission storage/cache | Berikan hak Modify pada user Windows yang menjalankan PHP untuk `storage` dan `bootstrap/cache`; pastikan bukan read-only hasil ekstraksi. |
| 404 selain halaman depan | DocumentRoot harus folder `public`; aktifkan rewrite dan AllowOverride, atau `try_files` pada Nginx. |
| Foto tidak tersimpan | Periksa izin storage dan `upload_max_filesize`/`post_max_size` PHP (gunakan >=8M); UI membatasi foto 4 MB. |
| HP tidak membuka komputer | Pastikan IP adapter benar, port benar, server bind ke LAN (`0.0.0.0` untuk Artisan), dan kedua perangkat satu jaringan. `.test` di PC tidak otomatis tersedia pada HP. |
| Firewall memblokir | Buat inbound rule port web yang dipakai, profil Private, remote LocalSubnet. Jangan mematikan seluruh firewall. |
| Wi-Fi berbeda / guest network | Hubungkan ke LAN yang sama dan matikan client isolation pada jaringan prototype sesuai kewenangan admin jaringan. |
| Kamera permission denied | Izinkan kamera dari site settings browser; tutup aplikasi pemakai kamera; tekan Aktifkan kamera kembali atau unggah foto. |
| Kamera perlu HTTPS | Gunakan sertifikat dengan SAN IP yang benar dan CA dipercaya HP; cek tidak ada certificate warning. HTTP IP LAN bukan secure context. |
| Scanner tidak aktif | Pilih mode; pastikan JS lokal `public/assets/html5-qrcode.min.js` tersedia. Cek izin/HTTPS, lalu tombol coba kembali. |
| QR tidak terdeteksi | Cukupkan cahaya, fokuskan kamera, jangan terlalu dekat, hindari pantulan, pertahankan quiet zone; gunakan foto tajam. |
| QR tidak terdaftar | Pastikan label dibuat dari proyek/database ini; label lama setelah regenerate tidak berlaku; kode barang/lokasi bukan token QR. |
| Barang masih pending | Buka Transaksi Pending di komputer, approve atau cancel. Menunggu beberapa detik tidak membuka lock. |
| Stok tidak cukup | Kurangi quantity atau cancel; tidak ada stok yang berubah pada kegagalan approval. |
| 419 / CSRF expired | Muat ulang dan login lagi; jangan mencampur HTTP/HTTPS, hostname/IP, atau port pada sesi yang sama. Jangan mengecualikan endpoint scan dari CSRF. |
| Login berulang setelah HTTPS | Pastikan kedua perangkat memakai HTTPS saat `SESSION_SECURE_COOKIE=true`; `SESSION_DOMAIN=null`; jalankan optimize:clear. |
| Polling tidak berjalan | Buka tab aktif, cek status sinkronisasi, koneksi LAN, session, endpoint GET `/api/pending-transactions`, dan console browser. Tunggu sekitar 3 detik; jangan menjalankan dua server dengan database berbeda. |
| Password lupa | Dari Terminal Laragon pada komputer pemilik, jalankan `php artisan warehouse:reset-password`. |
| Test gagal koneksi/izin | Sesuaikan `.env.testing`, buat database test, aktifkan proc_open dan hak membuat trigger untuk uji rollback. |

## Referensi resmi

- [Laravel database transactions](https://laravel.com/docs/12.x/database#database-transactions) dan [pessimistic locking](https://laravel.com/docs/12.x/queries#pessimistic-locking).
- [Laravel deployment/document root](https://laravel.com/docs/12.x/deployment).
- [Laragon Pretty URLs](https://laragon.org/docs/pretty-urls).
- [MDN getUserMedia dan secure context](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).
- [mkcert dan root CA mobile](https://github.com/FiloSottile/mkcert).
- [html5-qrcode API](https://scanapp.org/html5-qrcode-docs/docs/apis/classes/Html5Qrcode) dan [PHP QRCode v5](https://github.com/chillerlan/php-qrcode/tree/v5.0.5).
