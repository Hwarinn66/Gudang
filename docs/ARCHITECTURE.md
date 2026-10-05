# Arsitektur dan integritas data

## Sumber kebenaran

MySQL/MariaDB InnoDB adalah sumber kebenaran. Browser mengirim token/mode atau quantity; tidak pernah menghitung stok otoritatif. `StockTransactionService` menangani scan, draft quantity, approve, cancel, dan adjustment. Tidak ada endpoint untuk menulis `products.stock` secara bebas.

## Urutan lock

- **Scan:** mulai transaction → cari produk berdasarkan token dan `lockForUpdate()` → cek aktif/pending/stok → ambil nomor transaksi → insert pending → commit.
- **Approve/cancel/quantity:** baca product_id immutable dari transaksi → lock product → lock transaksi → cek PENDING → proses → commit. Pengecekan status dilakukan ulang setelah lock, bukan memakai model dari route binding yang mungkin kedaluwarsa.
- **Approval:** ambil stock terbaru setelah lock; validasi quantity dan batas; simpan produk dan transaksi dalam transaction yang sama. Pengecualian mana pun menyebabkan rollback.
- **Adjustment:** lock product → periksa UUID request belum dipakai, aktif, tanpa pending, expected_stock sesuai → insert histori dengan delta bertanda → update stock → commit.
- **Master data:** lock row tunggal `warehouse_mutexes.id=1` → lock produk/posisi yang relevan → validasi → simpan. Ini menyerialkan klaim posisi, perubahan kategori, dan penonaktifan lokasi; tidak menserialkan semua transaksi stok.
- **Nomor transaksi:** `transaction_sequences` mempunyai primary key (tanggal, tipe). `insertOrIgnore`, lalu `lockForUpdate()` dan increment di dalam transaction induk. Format IN/OUT/ADJ-YYYYMMDD-NNNN; setelah 9999 nomor bertambah menjadi lima digit, tanpa terpotong. Index unique pada nomor transaksi adalah pengaman tambahan.
- Retry deadlock database sampai 5 kali untuk operasi stok/master. Tidak ada email/side effect eksternal dalam closure yang bisa terulang.

Tidak pernah menyimpan lock QR di memori/timer. PENDING di database adalah lock logis. Sesudah APPROVED/CANCELLED, generated pending key menjadi NULL sehingga barang dapat discan lagi.

## Constraint database

| Invariant | Implementasi |
| --- | --- |
| Kode barang tidak duplikat | UNIQUE products.product_code, collation case-insensitive; input UI dinormalisasi uppercase |
| Token unik | UNIQUE products.qr_token |
| Satu posisi per barang aktif | Generated `active_position_id = CASE WHEN active=1 THEN rack_position_id ELSE NULL END`, UNIQUE |
| Satu pending per barang | Generated `pending_product_id = CASE WHEN status='PENDING' THEN product_id ELSE NULL END`, UNIQUE |
| Stok tidak negatif/overflow | Unsigned stock, CHECK rentang 0–2147483647, validasi service |
| Quantity/histori approved konsisten | CHECK status APPROVED harus memiliki quantity, before/after, approved_at, arah perhitungan sesuai tipe |
| Struktur lokasi unik | UNIQUE (room_id,code), (block_id,rack_number), (rack_id,position_number) |
| Histori tidak hilang karena master dihapus | Foreign keys RESTRICT; tidak tersedia route hard-delete |
| Adjustment tidak terulang | UNIQUE request_key + pemeriksaan dalam lock |

Generated columns sengaja tidak diisi oleh aplikasi atau SQL seed. NULL boleh berulang pada unique index, sehingga produk nonaktif dan transaksi final tetap tersimpan.

Constraint tidak menggantikan hak akses database. Akses SQL langsung oleh administrator bisa mengubah data; akun aplikasi sebaiknya dibatasi dan tidak digunakan di luar aplikasi. State machine PENDING → APPROVED/CANCELLED ditegakkan oleh service yang menjadi satu-satunya jalur mutasi HTTP. Tidak ada endpoint kembali ke PENDING.

## Histori dan query

`stock_transactions` menyimpan FK posisi serta snapshot barang/ruangan/lokasi/satuan. Histori menampilkan snapshot. Relasi produk hanya untuk navigasi ke detail terbaru. Periode filter histori memakai `created_at` (tanggal dibuat/scan); kolom scanned_at/approved_at/cancelled_at terpisah dan eksplisit.

`ProductQuery` memakai query builder/Eloquent, join struktur lokasi, filter terparameterisasi, dan allowlist sorting. `approvedOut()` adalah relasi terfilter OUT APPROVED untuk agregasi. Penambahan periode statistik nantinya cukup menambahkan rentang `approved_at` pada relasi/query agregat; tidak mengubah moving_type.

CSV memakai query filter yang sama, mengambil seluruh hasil tanpa dibatasi halaman. Ekspor memakai streaming dan pembacaan batch 200. String berawalan formula spreadsheet dinetralkan. Nilai numeric tetap numeric. CSV adalah pembacaan live; untuk snapshot laporan audit yang presisi selama transaksi sangat aktif, jalankan saat aktivitas dihentikan atau tambahkan mekanisme consistent-read report.

## Kontrak endpoint AJAX

Semua route berikut didefinisikan di **routes/web.php** dengan `web` middleware, session auth, `auth.session`, dan CSRF. Prefix `/api` adalah nama URL, bukan API publik/stateless. Header `Accept: application/json` dan `X-CSRF-TOKEN` diambil dari halaman yang sudah login. Cookie sesi harus dikirim pada origin yang sama.

| Metode | Endpoint | Input |
| --- | --- | --- |
| POST | /api/scan | qr_token, mode IN/OUT |
| GET | /api/pending-transactions | — |
| PATCH | /api/transactions/{id}/quantity | quantity integer >0; opsional menyimpan draft, tidak mengubah stok |
| POST | /api/transactions/{id}/approve | quantity integer >0; server memvalidasi ulang |
| POST | /api/transactions/{id}/cancel | — |
| GET | /api/products/search | q, kategori/lokasi/moving/status/sort sesuai tabel barang; maksimal 30 hasil |

UI pending memegang draft quantity lokal sampai Approve; endpoint quantity disediakan bagi integrasi/draft tersimpan. Nilai quantity pada approval eksplisit agar operator menyetujui angka yang dilihatnya.

```json
{"qr_token":"550e8400-e29b-41d4-a716-446655440000","mode":"IN"}
```

```json
{"success":true,"message":"QR BERHASIL DIBACA","data":{"transaction_number":"IN-20261002-0001","status":"PENDING","mode":"IN","product":{"product_code":"A0001","name":"Ember","specification":"1 KG","room":"Ruangan 1","location":"A.01.01","stock":0,"unit":"PCS"}}}
```

```json
{"success":false,"code":"STOCK_NOT_ENOUGH","message":"STOK TIDAK MENCUKUPI","data":{"stock":3,"quantity":5}}
```

Domain errors: QR_NOT_FOUND (404), PRODUCT_INACTIVE, TRANSACTION_PENDING, STOCK_EMPTY, STOCK_NOT_ENOUGH, POSITION_OCCUPIED, PRODUCT_CODE_EXISTS, TRANSACTION_ALREADY_PROCESSED (409), INVALID_QUANTITY (422), STOCK_CHANGED, LOCATION_INACTIVE, STOCK_NOT_ZERO, STOCK_LIMIT. Validation errors memakai 422; sesi tidak valid 401; CSRF kedaluwarsa 419; rate limit 429. Error internal memberi pesan generik dan tercatat di log.

## Auth dan file

Session diregenerasi sesudah login/logout. Password di-hash melalui Laravel Hash. Login memiliki rate limit, scanner 60 request/menit per user/IP menurut throttle Laravel. Tidak ada registrasi publik/role kompleks. Perubahan password membatalkan sesi lama melalui `auth.session`.

Foto dibatasi MIME JPEG/PNG/WebP, image validation, ukuran 4MB, nama acak storage, dan disajikan melalui route berautentikasi. SVG upload tidak diizinkan. QR SVG dihasilkan dari UUID backend sendiri. Halaman Blade meng-escape input pengguna; JavaScript meng-escape data sebelum menghasilkan kartu HTML.

## Pengembangan realtime

Saat ini transport ringan: GET pending setiap 3 detik pada Dashboard/Pending; badge pada halaman lain setiap 10 detik. Tidak memerlukan proses background dan bekerja dengan Apache/PHP Laragon. Scanner tetap dapat diganti perangkat lain karena backend hanya memahami token dan mode.

Untuk SSE/WebSocket di masa depan, publish event **setelah commit**, lalu gunakan event hanya sebagai sinyal untuk fetch ulang pending. Pertahankan semua locking/validasi/index di service; event tidak menjadi sumber kebenaran stok.
