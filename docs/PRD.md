# PRD: Sistem Peminjaman & Inventaris Barang Tim IT

| | |
|---|---|
| **Versi** | 1.0 (draf untuk persetujuan) |
| **Tanggal** | 22 Oktober 2026 |
| **Asal** | Diadaptasi dari PRD v5.4 proyek sebelumnya. Proyek ini dibangun dari nol di repo dan direktori baru. Tidak ada kode, data, atau migrasi yang dibawa dari proyek lama |
| **Stack** | Laravel 13 (API JSON, PHP 8.4) + Vue (Vite, Tailwind CSS) + MySQL 8.4 InnoDB |
| **Dokumen pendamping** | [`DESAIN.md`](DESAIN.md) (aturan bisnis, skema, arsitektur, API, UI), [`../AGENTS.md`](../AGENTS.md) (aturan kerja agent), [`../todo.md`](../todo.md) (task per fase) |
| **Pembaca** | Pengembang, petugas inventaris IT, kepala tim IT |

> **Cara membaca.** PRD ini menjawab *apa* yang dibangun dan *mengapa*, plus kriteria lulus uji. *Bagaimana* membangunnya, termasuk aturan bisnis `BR-xx`, ada di `DESAIN.md`. Aturan bisnis ditaruh di sana karena setiap aturan menempel pada tabel dan Service yang menegakkannya. Setiap aturan hanya didefinisikan satu kali.

---

## 1. Ringkasan

Aplikasi web untuk mencatat peminjaman dan pengembalian barang inventaris **tim IT**, menggantikan buku catatan. Berjalan di jaringan lokal kantor pada satu PC khusus. Dua mode:

- **Kiosk** (layar sentuh, tanpa login): karyawan dari divisi mana pun mencari barang, meminjam atau memesan untuk beberapa hari ke depan, mengecek batas waktu, memperpanjang, membatalkan pemesanan, dan mengklaim pengembalian.
- **Panel Admin** (wajib login): petugas IT mengelola barang, memverifikasi pengembalian, memantau peminjaman, menangani barang rusak, mengatur parameter, dan mencetak laporan.

Dua prinsip utama: **stok hanya berubah lewat transaksi yang divalidasi server**, dan **stok kembali hanya setelah petugas memeriksa barang fisiknya**.

## 2. Latar belakang

Peminjaman barang IT dicatat di buku. Data sulit dicari, stok sulit diverifikasi, dan keterlambatan tidak terlacak.

| Masalah pada pencatatan manual | Dampak |
|---|---|
| Tidak ada verifikasi saat barang kembali | Barang rusak atau kurang bisa tercatat "baik" |
| Peminjam tidak tahu kapan harus mengembalikan | Keterlambatan sulit ditagih |
| Pengembalian harus sekaligus | Tidak realistis, peminjaman 3 unit sering dikembalikan bertahap |
| Barang rusak langsung bisa dipinjam lagi | Peminjam berikutnya menerima barang tidak layak |

Alasan dibangun ulang: prototipe sebelumnya menghitung stok di browser, memeriksa login di sisi klien, menimpa seluruh data pada tiap sinkronisasi, dan menyimpan kredensial bawaan di kode. Semua itu diselesaikan dari rancangan, bukan ditambal.

## 3. Tujuan dan ukuran keberhasilan

| ID | Tujuan | Ukuran |
|---|---|---|
| G1 | Stok selalu konsisten | BR-01 lolos pada uji acak minimal 1.000 operasi berturut-turut. Perintah `inventaris:periksa-invarian` melaporkan 0 pelanggaran |
| G2 | Tidak ada peminjaman melebihi stok walau bersamaan | Dua permintaan serentak pada barang stok 1 menghasilkan tepat satu transaksi |
| G3 | Akses admin aman | 100% rute admin menolak akses tanpa login. Tidak ada kredensial di repo atau UI |
| G4 | Peminjaman cepat | Median penyelesaian form kiosk sampai mendapat kode ≤ 2 menit pada uji penerimaan |
| G5 | Laporan dapat diandalkan | Empat laporan PDF (rekap, belum dikembalikan, stok dan kondisi, riwayat pengembalian) sesuai data dan filter |
| G6 | Mudah dipasang | Aplikasi berjalan dari repo bersih dengan `composer install`, `.env`, `php artisan migrate --seed`, dan perintah pembuat admin |
| G7 | Verifikasi tidak menumpuk | Median klaim menunggu verifikasi ≤ 1 hari kerja |
| G8 | Stok cocok dengan fisik | Selisih stok sistem vs hitung fisik pada opname pertama ≤ 2% dari total unit |
| G9 | Buku catatan dihentikan | Peminjaman yang masih dicatat di luar sistem = 0 setelah bulan pertama |

Target G4, G7, G8, dan G9 adalah usulan, ditinjau ulang setelah sebulan pemakaian.

## 4. Pengguna dan hak akses

| Peran | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Peminjam** | Karyawan peminjam barang IT. Memakai kiosk, **tanpa akun** | Proses cepat, tahu batas waktu, bisa memperpanjang, bisa mengembalikan bertahap |
| **Admin** | Petugas IT, satu level peran. Login ke panel admin | Memverifikasi barang kembali, memantau keterlambatan, mengelola stok |
| **Pembaca laporan** | Kepala tim IT. Tidak punya akun | Menerima PDF dari admin |

| Fitur | Peminjam (kiosk) | Admin |
|---|---|---|
| Lihat katalog dan stok tersedia | Ya | Ya |
| Ajukan peminjaman dan pemesanan | Ya | – |
| Batalkan pemesanan | Milik sendiri, sebelum tanggal pinjam | Selama belum ada klaim, alasan wajib |
| Cek peminjaman | Hanya milik kode yang dimasukkan | Semua |
| Perpanjangan | Sesuai aturan BR-11 | Tanpa kuota, alasan wajib |
| Klaim pengembalian | Ya | – |
| Verifikasi klaim | – | Ya |
| Kelola barang, kategori, divisi | – | Ya (hapus barang = soft-delete, kategori dan divisi dinonaktifkan) |
| Penyesuaian stok, tandai rusak, perbaikan, afkir | – | Ya |
| Koreksi peminjaman | – | Ya, alasan wajib |
| Laporan, pengaturan, audit log | – | Ya |
| Akun admin | – | Kelola, plus reset lewat perintah artisan |

## 5. Ruang lingkup

Seluruh fitur di bawah dibangun. Prioritas Must/Should/Could (kolom P di bagian 8) hanya menentukan **urutan pembangunan**, bukan pemangkasan fitur.

### 5.1 Dalam cakupan

- Katalog kiosk dengan pencarian dan filter kategori, form peminjaman, pemesanan beberapa hari ke depan, struk dengan kode pinjam
- Pembatalan pemesanan oleh peminjam, pembatalan peminjaman oleh admin
- Cek peminjaman dengan kode pinjam, termasuk batas waktu dan hasil klaim
- Pengembalian parsial dengan klaim di kiosk dan verifikasi admin, catatan per klaim
- Perpanjangan oleh peminjam (dengan aturan) dan oleh admin
- Penanganan barang rusak: stok terpisah, tandai rusak, perbaikan, afkir
- Kelola barang, kategori, divisi, penyesuaian stok, koreksi peminjaman
- Dasbor, pemantauan peminjaman, laporan PDF, buku besar stok, jejak audit, pengaturan parameter
- Autentikasi admin server-side, backup terjadwal

### 5.2 Di luar cakupan

Notifikasi WhatsApp/email, barcode/QR, nomor seri per unit, kalender ketersediaan per tanggal, konfirmasi pengambilan dan pelepasan otomatis pemesanan, master data karyawan, banyak level peran admin, foto barang, denda, persetujuan peminjaman sebelum barang diambil.

### 5.3 Kategori, kondisi, dan tipe peminjam

- **Kategori** dikelola admin dan wajib untuk setiap barang. Dipakai sebagai filter di katalog kiosk dan laporan (BR-28, BR-31, BR-32, BR-33).
- **Kondisi barang** tidak disimpan. Diturunkan dari angka stok: Baik (`rusak = 0`), Sebagian rusak (`0 < rusak < total`), Semua rusak (`rusak = total` dan `tersedia = 0`). Hanya untuk tampilan ringkasan (BR-29).
- **Tipe peminjam** (Karyawan atau Magang) wajib di form kiosk. Murni klasifikasi untuk filter dan laporan, tidak mengubah kuota, durasi, atau aturan lain (BR-30).

## 6. Keputusan produk

| ID | Keputusan |
|---|---|
| D-1 | Klaim pengembalian **tidak langsung mengubah stok**. Stok berubah setelah admin memverifikasi |
| D-2 | Barang rusak **tidak masuk `tersedia`**. Masuk stok `rusak`, admin yang memindahkannya setelah diperbaiki atau mengafkirnya |
| D-3 | Catatan berlaku **per transaksi pengembalian** (satu catatan per klaim) |
| D-4 | Peminjam melihat batas waktu, sisa hari, dan hari terlambat lewat kode pinjam |
| D-5 | Perpanjangan oleh peminjam **otomatis disetujui** dalam batas aturan (BR-11). Di luar aturan, hanya admin. Alasan: admin sudah memikul verifikasi pengembalian, dan kuota membatasi penyalahgunaan |
| D-6 | Tanggal pinjam dapat dipilih, dari hari ini sampai `maks_booking_hari` ke depan. Tanggal lampau tidak diizinkan di kiosk |
| D-7 | Kode pinjam menjadi kunci identitas untuk cek, klaim, dan perpanjangan |
| D-8 | Keterlambatan berhenti dihitung saat klaim dikirim. Bila klaim ditolak, hitungan pulih |
| D-9 | Admin boleh mengubah kondisi yang dilaporkan peminjam. Catatan admin wajib bila berbeda atau menolak |
| D-10 | **Pemesanan menahan stok sejak dibuat.** Alternatifnya, kalender ketersediaan per tanggal, jauh lebih rumit. Harganya: unit yang dipesan tampak tidak tersedia selama masa tunggu (maksimal 7 hari) |
| D-11 | Divisi dipilih dari daftar yang dikelola admin, bukan diketik |
| D-12 | Setiap barang punya penanda **boleh diperpanjang** (bawaan ya) |
| D-13 | Basis data final **MySQL InnoDB**, satu mesin saja, tanpa mode ganda |
| D-14 | Backend **Laravel 13 sebagai API JSON** di `backend/`, frontend **Vue + Tailwind** di `frontend/`. Dua project dalam satu repo, hanya berkomunikasi lewat JSON. Hanya backend yang mengakses MySQL |
| D-15 | Peminjam tanpa akun. Hanya admin yang login |
| D-16 | Login admin memakai **Laravel Sanctum mode SPA** (sesi cookie `httpOnly`, `SameSite=Strict`), bukan token di browser |
| D-17 | Laporan PDF dibuat **di server** dari data database |

## 7. Asumsi

| ID | Asumsi |
|---|---|
| A-1 | Sistem khusus inventaris divisi IT. Peminjam berasal dari semua divisi |
| A-2 | Skala: ≤ 300 jenis barang, ≤ 30 peminjaman per hari, ≤ 5 klien bersamaan |
| A-3 | Satu PC kantor khusus, menyala selama jam operasional, menjadi server aplikasi dan MySQL. MySQL hanya menerima koneksi dari PC itu sendiri |
| A-4 | Ada petugas IT yang hadir pada jam kerja untuk memverifikasi |
| A-5 | Barang fisik yang dikembalikan diserahkan ke petugas IT |
| A-6 | Ada 1 admin aktif, ditambah satu akun cadangan (misalnya kepala tim IT) yang jarang dipakai |
| A-7 | Kiosk berlayar sentuh dengan keyboard fisik. Bukti selalu tampil di layar dan punya tata letak cetak. Pencetakan opsional lewat printer kiosk bila tersedia |
| A-8 | Mulai dari database kosong. Tidak ada data lama yang dimigrasikan |

## 8. Kebutuhan fungsional

Prioritas: **M** = Must, **S** = Should, **C** = Could. Kolom BR merujuk `DESAIN.md` bagian 2.

### 8.1 Mode Kiosk

| ID | Kebutuhan | BR | P |
|---|---|---|---|
| FR-K-01 | **Katalog dan cek stok.** Nama dan jumlah `tersedia`. Barang stok 0 tidak dapat dipilih. Pencarian instan berdasarkan nama. Filter kategori. Jumlah rusak atau menunggu tidak ditampilkan | 28, 31 | M |
| FR-K-02 | **Form peminjaman.** Nama (2–100 karakter), divisi (pilihan, wajib), tipe peminjam (wajib), telepon (opsional, 8–15 digit), tanggal pinjam (bawaan hari ini, dapat dipilih sampai `maks_booking_hari`), batas kembali, daftar barang dan jumlah. Bila tanggal pinjam di masa depan, layar menegaskan stok ditahan mulai sekarang. Validasi di klien dan server. Satu kunci idempotensi per form | 02, 20, 22, 23, 27, 30 | M |
| FR-K-03 | **Bukti peminjaman** di layar dan siap cetak: kode pinjam besar, nama, divisi, tanggal pinjam (penanda PEMESANAN bila masa depan), batas kembali, barang dan jumlah, petunjuk mengembalikan | 17 | M |
| FR-K-04 | **Cek peminjaman.** Input kode menampilkan kartu: per barang dipinjam, menunggu, terverifikasi, sisa. Batas kembali, penghitung mundur ("Sisa 3 hari" atau "Terlambat 2 hari"), perpanjangan terpakai dan sisa kuota, riwayat klaim. Untuk pemesanan: "Dipesan, mulai [tanggal]" dan tombol Batalkan sebagai ganti Kembalikan dan Perpanjang. Kode salah tidak membocorkan data. Terkunci setelah `kiosk_lookup_maks_gagal` gagal beruntun | 17, 18 | M |
| FR-K-05 | **Klaim pengembalian parsial.** Per barang: jumlah Baik, Rusak, Hilang, total ≤ sisa. Satu catatan per klaim (opsional, ≤ 500 karakter). Tombol nonaktif bila sisa 0 | 03, 04, 10 | M |
| FR-K-06 | **Bukti klaim**: kode klaim, isi klaim, status Menunggu Verifikasi, pengingat menyerahkan barang fisik ke petugas. Tata letak cetak | 17 | M |
| FR-K-07 | **Perpanjangan.** Pilih tambahan hari (1 sampai batas), alasan opsional. Tampilkan batas kembali lama dan baru sebelum konfirmasi. Bila tidak memenuhi BR-11, tombol nonaktif disertai alasan jelas ("Sudah terlambat, hubungi petugas IT") | 11, 12 | M |
| FR-K-08 | **Bukti perpanjangan** berisi batas kembali baru, dapat dicetak | 12 | S |
| FR-K-09 | **Hasil klaim terlihat peminjam.** Klaim terverifikasi menampilkan hasil (baik, rusak, hilang, ditolak) dan catatan admin. Form verifikasi admin memberi petunjuk bahwa catatan itu terlihat peminjam | 06 | S |
| FR-K-10 | **Auto-reset kiosk.** Setelah `kiosk_idle_detik` tanpa interaksi, hitung mundur lalu kembali ke beranda dan menghapus isian. Tidak ada data peminjam di penyimpanan browser | 18 | M |
| FR-K-11 | Elemen sentuh minimal 44×44 px, teks utama terbaca dari jarak lengan | – | S |
| FR-K-12 | **Batalkan pemesanan** selama hari ini < tanggal pinjam, dengan konfirmasi. Stok kembali | 24 | M |

### 8.2 Panel Admin

| ID | Kebutuhan | BR | P |
|---|---|---|---|
| FR-A-01 | **Login dan sesi.** Email dan password (hash). Percobaan gagal dibatasi (5 per 15 menit per IP dan email). Halaman login tidak menampilkan akun atau password. Logout. Sesi berakhir setelah `sesi_idle_menit` tanpa aktivitas, ditegakkan server | 19 | M |
| FR-A-02 | **Akun admin.** Ganti password sendiri, tambah admin, nonaktifkan admin lain. Tanpa registrasi publik. Akun pertama dibuat lewat perintah artisan yang meminta password | 19 | M |
| FR-A-03 | **Kelola barang.** Tambah, ubah, hapus (soft-delete). Saat ditambah `tersedia = total`, `rusak = 0`. Wajib kategori dan penanda boleh diperpanjang. Pencarian dan pagination. Kondisi tampil sebagai turunan stok | 14, 28, 29, 33 | M |
| FR-A-04 | **Antrean verifikasi.** Klaim menunggu, terlama di atas, lencana jumlah di menu. Detail: peminjam, batas kembali, hari terlambat saat klaim, catatan peminjam (disorot), isi klaim. Per baris admin membagi ke Baik, Rusak, Hilang, Ditolak. Aksi cepat **"Setujui sesuai laporan"**. Catatan admin wajib sesuai BR-06 | 05, 06, 07, 08 | M |
| FR-A-05 | **Pemantauan peminjaman.** Ringkasan Dipinjam, Terlambat, Menunggu Verifikasi, Selesai. Filter: status (termasuk Dipesan dan Dibatalkan), barang, kategori, divisi, tipe peminjam, rentang tanggal, teks, Ada catatan, Ada kerusakan atau kehilangan, Belum Dikembalikan. Pagination | 30, 32 | M |
| FR-A-06 | **Detail peminjaman** berupa lini masa: dibuat (tipe peminjam), perpanjangan, klaim (catatan peminjam), verifikasi (catatan admin), koreksi, pembatalan | – | M |
| FR-A-07 | **Koreksi peminjaman**, alasan wajib | 16 | M |
| FR-A-08 | **Perpanjangan oleh admin**, alasan wajib, boleh untuk yang terlambat | 13 | M |
| FR-A-09 | **Stok rusak.** Daftar barang `rusak > 0`. Aksi: tandai tersedia menjadi rusak, selesai diperbaiki, afkir | 09, 34 | M |
| FR-A-10 | **Penyesuaian stok** tambah atau kurang, alasan wajib | 15 | M |
| FR-A-11 | **Laporan PDF**: (1) rekap peminjaman mengikuti filter, termasuk kategori dan tipe peminjam, (2) belum dikembalikan plus pemesanan yang belum dimulai, (3) stok dan kondisi per kategori, (4) riwayat pengembalian dan verifikasi dengan catatan. Berisi judul, periode, waktu cetak | 32 | M |
| FR-A-12 | **Pengaturan parameter** (delapan kunci, dengan rentang nilai valid). Nilai baru langsung berlaku | – | S |
| FR-A-13 | **Jejak audit** terbaca admin: siapa, aksi, kapan, entitas | – | S |
| FR-A-14 | **Ekspor CSV/Excel** untuk daftar peminjaman dan stok | – | C |
| FR-A-15 | Lencana antrean verifikasi diperbarui berkala (30 detik) tanpa memuat ulang halaman | – | S |
| FR-A-16 | **Kelola divisi**: tambah, ubah nama, nonaktifkan | 27 | M |
| FR-A-17 | **Batalkan peminjaman** selama belum ada klaim, alasan wajib | 25 | M |
| FR-A-18 | **Kelola kategori**: tambah, ubah nama, nonaktifkan | 28, 33 | M |
| FR-A-19 | **Dasbor**: kartu angka dipinjam, terlambat, menunggu verifikasi, stok rusak. Daftar peminjaman terlambat. Angka sama dengan data tabel | – | M |

### 8.3 Sistem

| ID | Kebutuhan | P |
|---|---|---|
| FR-S-01 | Semua logika peminjaman, klaim, verifikasi, perpanjangan, dan stok berada di **server** dan berjalan dalam transaksi atomik. Klien hanya menampilkan data dan mengirim niat | M |
| FR-S-02 | Setiap perubahan stok menulis satu baris buku besar `stock_movements` dalam transaksi yang sama, sehingga jumlah seluruh perubahan sama dengan nilai stok sekarang | M |
| FR-S-03 | Perintah artisan `inventaris:periksa-invarian` menghitung ulang BR-01 dan mencocokkan dengan buku besar. Perintah `inventaris:periksa-lingkungan` memeriksa versi MySQL, `sql_mode`, dan charset | M |
| FR-S-04 | Backup harian `mysqldump --single-transaction` tanpa mematikan server, 14 salinan terakhir, satu salinan di perangkat lain, uji pulih berkala | M |
| FR-S-05 | Hasil build frontend disajikan pada **satu port**, dan aplikasi berjalan sebagai layanan yang menyala otomatis | S |
| FR-S-06 | Perintah artisan reset password admin dari konsol server. Reset mengakhiri semua sesi lama | M |

## 9. Prosedur operasional (SOP)

1. **Meminjam.** Karyawan mengisi form di kiosk, menyimpan kode pinjam, lalu mengambil barang. Untuk pemesanan, barang diambil pada tanggal pinjam. Pemesanan dapat dibatalkan lewat kiosk sebelum tanggal itu.
2. **Mengembalikan.** Karyawan membuka Cek Peminjaman, memasukkan kode, mengisi klaim, lalu **menyerahkan barang fisik ke petugas IT**.
3. **Verifikasi.** Petugas membuka antrean, memeriksa barang fisik, mencocokkan dengan klaim, lalu mengonfirmasi. Klaim tanpa barang fisik di meja ditolak dengan catatan.
4. **Kode hilang.** Peminjam menghubungi petugas. Petugas mencari peminjaman berdasarkan nama atau tanggal di panel admin dan membacakan kode. Kode tidak dikirim lewat kiosk.
5. **Barang rusak.** Disimpan terpisah dan diberi tanda. Setelah diperbaiki, petugas menandainya selesai di panel admin.
6. **Pemesanan tidak diambil.** Bila barang masih ada di penyimpanan setelah tanggal pinjam tiba, petugas membatalkan peminjaman di panel admin (alasan wajib) agar stok kembali tersedia.

## 10. Kebutuhan non-fungsional

| Aspek | Kebutuhan |
|---|---|
| **Keamanan** | Password di-hash (bcrypt atau argon2id bawaan Laravel). Cookie sesi `httpOnly`, `SameSite=Strict`. Proteksi CSRF. Seluruh rute `/api/admin/*` ditolak 401 tanpa sesi. Rate limit login dan lookup kode kiosk. Validasi server lewat Form Request. Perlindungan mass assignment. Escaping output. CORS dibatasi ke alamat frontend. `APP_DEBUG=false` di operasional. Tidak ada kredensial di kode, README, atau bundle. `.env` dan dump SQL tidak masuk Git. Akun database aplikasi berhak minimal. MySQL hanya `127.0.0.1`, port 3306 tidak dibuka di firewall |
| **Integritas data** | Transaksi InnoDB dengan `lockForUpdate` dalam urutan tetap, deadlock diulang otomatis. `CHECK` di database menegakkan BR-01 (butuh MySQL ≥ 8.0.16). Mode `STRICT_TRANS_TABLES`. Kunci idempotensi. Tanggal disimpan `YYYY-MM-DD` zona Asia/Jakarta, waktu kejadian UTC |
| **Kinerja** | Respons ≤ 500 ms untuk operasi umum pada skala A-2. Halaman daftar < 2 detik pada 5.000 transaksi |
| **Ketersediaan** | Dipakai pada jam kerja. MySQL dan aplikasi menyala otomatis setelah PC dihidupkan ulang, diuji dengan restart sungguhan |
| **Keterlacakan** | Buku besar stok, audit, dan riwayat koreksi tidak dapat diubah dari antarmuka |
| **Kegunaan** | Bahasa Indonesia. Kiosk dapat dipakai dengan layar sentuh maupun keyboard fisik. Pesan galat spesifik dan tidak menyalahkan |
| **Responsivitas** | Kiosk dioptimalkan untuk tablet atau lebih besar. Panel admin: desktop, tablet, ponsel |
| **Privasi** | Telepon opsional, hanya tampil untuk admin. Kiosk tidak menampilkan data peminjam lain |
| **Pemeliharaan** | Pemisahan Controller, Form Request, Service, Model. Migration satu-satunya sumber skema. Tes otomatis untuk aturan bisnis, konkurensi, dan idempotensi dijalankan sebelum setiap rilis |
| **Instalasi** | Windows dengan PHP 8.4 dan MySQL 8.4, tanpa Docker. Cara menjalankan ditetapkan di K-3 |
| **Kompatibilitas** | Chrome dan Edge versi terbaru |

## 11. User story

| ID | Cerita | Penerimaan |
|---|---|---|
| US-01 | Sebagai peminjam, saya ingin mencari barang dan melihat stok | Kata kunci memunculkan nama yang cocok. Stok 0 tidak dapat ditambahkan |
| US-02 | Sebagai peminjam, saya ingin meminjam beberapa barang tanpa akun | Form valid mengurangi stok dan menampilkan kode. Melebihi stok atau barang ganda ditolak |
| US-03 | Sebagai peminjam, saya ingin menyimpan kode dan struk | Struk tampil dan dapat dicetak tanpa elemen navigasi |
| US-04 | Sebagai peminjam, saya ingin mengecek batas waktu | Kode benar menampilkan kartu milik sendiri. Kode salah tidak membocorkan data |
| US-05 | Sebagai peminjam, saya ingin mengembalikan sebagian barang lebih dulu | Klaim melebihi sisa ditolak. Stok tidak berubah sampai diverifikasi |
| US-06 | Sebagai admin, saya ingin memverifikasi barang kembali | Empat hasil berjumlah sama dengan jumlah klaim. Hasil berbeda wajib catatan. Verifikasi ulang ditolak |
| US-07 | Sebagai admin, saya ingin login aman | Rute admin tanpa login dialihkan. Sesi berakhir saat idle. Percobaan dibatasi |
| US-08 | Sebagai admin, saya ingin mengelola barang, kategori, divisi | Barang yang sedang dipinjam tidak bisa dihapus. Kategori nonaktif tidak muncul di form, riwayat tetap terbaca |
| US-09 | Sebagai admin, saya ingin memantau keterlambatan | Filter dan badge status benar. Hari terlambat dihitung dari batas kembali |
| US-10 | Sebagai kepala tim IT, saya ingin laporan PDF | PDF memuat judul, periode, waktu cetak, dan mengikuti filter |

## 12. Kriteria penerimaan

Tes bertanda ⚙ wajib otomatis. Tes lain diuji manual.

| ID | Skenario | Lulus jika |
|---|---|---|
| T-01 ⚙ | Akses admin tanpa sesi | Halaman dialihkan ke login dan API admin membalas 401 |
| T-02 ⚙ | Peminjaman | `tersedia` berkurang. Melebihi `tersedia` ditolak oleh server (409) |
| T-03 ⚙ | Klaim lalu verifikasi | Klaim tidak mengubah stok. Verifikasi baik/rusak/hilang berefek sesuai BR-05. Verifikasi ulang 409 |
| T-04 ⚙ | Status dan hari terlambat | Dihitung server sesuai DESAIN bagian 4 dan BR-10 |
| T-05 ⚙ | Hapus barang | Ditolak bila ada unit Dipinjam atau Menunggu. Soft-delete berfungsi bila bersih |
| T-06 ⚙ | Privasi kiosk | Kode salah tidak menampilkan data. Terkunci setelah batas gagal |
| T-07 ⚙ | Pengembalian bertahap | Pinjam 3, klaim 2 terverifikasi, sisa tetap 1 |
| T-08 ⚙ | Klaim melebihi sisa | Ditolak server, termasuk bila unit sedang menunggu verifikasi |
| T-09 ⚙ | Kondisi campuran | Satu klaim berisi baik/rusak/hilang pada beberapa barang diproses benar |
| T-10 ⚙ | Invarian acak | 1.000 operasi acak (pinjam, pesan, klaim, verifikasi, batal, perpanjang, penyesuaian, tandai rusak, perbaikan, afkir). `periksa-invarian` melaporkan 0 pelanggaran |
| T-11 ⚙ | Klik ganda | Dua permintaan dengan kunci sama menghasilkan satu peminjaman dan satu klaim |
| T-12 ⚙ | Terlambat dan klaim | Klaim membekukan hari terlambat. Penolakan memulihkan hitungan |
| T-13 | Catatan peminjam | Catatan klaim tampil di antrean, detail, penanda Ada catatan, dan filter |
| T-14 ⚙ | Override admin | Hasil berbeda tanpa catatan admin ditolak. Dengan catatan, tercatat di buku besar dan audit |
| T-15 ⚙ | Stok rusak | Rusak tidak menambah `tersedia`. Perbaikan memindahkan. Afkir mengurangi `total` |
| T-16 | Cek batas waktu | Kode valid menampilkan batas kembali, sisa hari atau hari terlambat, hasil klaim, dan catatan admin |
| T-17 ⚙ | Perpanjangan sah | Batas baru = batas lama + n hari. Riwayat tercatat |
| T-18 ⚙ | Perpanjangan ditolak | Ditolak bila terlambat, kuota habis, n melebihi batas, tidak ada sisa, atau barang tidak boleh diperpanjang (pesan menyebut barangnya) |
| T-19 ⚙ | Perpanjangan admin | Peminjaman terlambat dapat diperpanjang dengan alasan. Pelaku tercatat admin |
| T-20 ⚙ | Keamanan dasar | Tidak ada kredensial di repo atau hasil build. Password berupa hash. Rate limit login berfungsi. Sesi idle berakhir di server sesuai `sesi_idle_menit` |
| T-21 ⚙ | Konkurensi | Dua peminjaman bersamaan (dua proses terpisah) untuk stok 1: tepat satu berhasil |
| T-22 ⚙ | Verifikasi paralel | Dua verifikasi pada satu klaim: satu berhasil, satu 409 |
| T-23 ⚙ | Pemesanan | Tanggal masa depan dalam batas: stok langsung berkurang, status Dipesan. Tanggal lampau atau melebihi `maks_booking_hari` ditolak |
| T-24 ⚙ | Batalkan pemesanan | Sebelum tanggal pinjam: stok kembali, status Dibatalkan. Pada atau sesudahnya: ditolak di kiosk |
| T-25 ⚙ | Klaim dan perpanjangan atas pemesanan | Keduanya ditolak (BR-04, BR-26) |
| T-26 ⚙ | Batalkan oleh admin | Berhasil dengan alasan bila belum ada klaim. Ditolak bila sudah ada klaim |
| T-27 ⚙ | Barang tidak boleh diperpanjang | Perpanjangan peminjam ditolak. Admin tetap dapat memperpanjang |
| T-28 ⚙ | Divisi | Form hanya menerima divisi aktif. Divisi nonaktif tidak muncul, riwayat tetap tampil |
| T-29 ⚙ | Reset password | Perintah konsol mengganti hash dan mengakhiri semua sesi lama |
| T-30 ⚙ | Kategori | Form barang menolak tanpa kategori. Kategori nonaktif tidak muncul di filter, barang berelasi tetap terbaca. Kategori tidak bisa dinonaktifkan selama masih punya barang aktif |
| T-31 ⚙ | Kondisi turunan | Baik, Sebagian rusak, Semua rusak sesuai angka stok |
| T-32 ⚙ | Tipe peminjam | Form menolak tanpa tipe. Nilai diterima `karyawan` atau `magang`. Filter admin tepat |
| T-33 ⚙ | Tandai rusak | `n` valid: `tersedia −n`, `rusak +n`, `total` tetap, satu mutasi `TANDAI_RUSAK` beralasan, BR-01 tetap benar. Nol, negatif, pecahan, atau > `tersedia` ditolak tanpa mengubah apa pun |
| T-34 ⚙ | Rollback | Kegagalan di tengah transaksi tidak meninggalkan data parsial |
| T-35 ⚙ | XSS | Teks `<script>` di form tampil sebagai teks, tidak dieksekusi |
| T-36 ⚙ | Pemeriksaan lingkungan | Perintah gagal bila versi MySQL terlalu rendah, `sql_mode` tidak strict, atau charset bukan `utf8mb4` |
| T-37 | Hak akses dan jaringan MySQL | Akun aplikasi tidak dapat `DROP` atau `ALTER`. Port 3306 tidak dapat dijangkau dari komputer lain |
| T-38 | Backup dan pulih | Dump dibuat saat server hidup, dipulihkan ke database terpisah, `periksa-invarian` lolos |
| T-39 | Cetak | Struk, bukti, dan laporan tercetak tanpa sidebar dan navbar |
| T-40 | Responsif | Kiosk di tablet dan panel admin di ponsel (360 dan 768 px) tidak terpotong |
| T-41 ⚙ | Laporan PDF | Empat jenis laporan berisi data sesuai filter |
| T-42 ⚙ | Buku besar | Untuk setiap barang, `total`, `tersedia`, `rusak` sama dengan jumlah `delta_*` di `stock_movements` |

## 13. Risiko

| Risiko | Kemungkinan | Dampak | Mitigasi |
|---|---|---|---|
| Admin lambat memverifikasi, stok tampak lebih sedikit dari kenyataan | Sedang | Tinggi | Aksi cepat "Setujui sesuai laporan", lencana antrean, SOP serah barang langsung |
| Kode pinjam hilang | Tinggi | Rendah | Admin mencari dan membacakan kode (SOP 4) |
| Lalu lintas jaringan lokal tidak terenkripsi (HTTP) | Sedang | Sedang | Batasi akses ke jaringan kantor. Pertimbangkan HTTPS dengan sertifikat lokal |
| Satu PC server jadi titik kegagalan | Sedang | Tinggi | Backup harian ke lokasi kedua, prosedur pulih teruji |
| Race condition stok | Sedang | Tinggi | `lockForUpdate`, pembaruan bersyarat, tes konkurensi T-21 dan T-22 |
| Peminjam sengaja melaporkan kondisi keliru | Sedang | Sedang | Verifikasi fisik oleh admin, catatan terekam |
| Pemesanan tidak diambil, stok tertahan | Sedang | Sedang | Rentang 7 hari, peminjam dan admin dapat membatalkan |
| Satu admin: antrean macet saat berhalangan, password terlupa | Sedang | Tinggi | Akun cadangan, perintah reset password |
| Kode pinjam ditebak orang lain | Rendah | Sedang | Kode acak kriptografis (sekitar 887 juta kombinasi) dan pembatasan percobaan |
| Lingkungan campuran (PHP dari satu alat, MySQL dari alat lain) | Sedang | Sedang | Tetapkan satu cara menjalankan (K-3) dan catat di panduan setup |
| Akun `root` bawaan Laragon tanpa kata sandi | Tinggi | Tinggi | Setel kata sandi root di Fase 0. Aplikasi memakai akun terpisah berhak terbatas |
| Data diubah lewat alat SQL manual, melewati buku besar | Sedang | Tinggi | Aturan operasional (DESAIN bagian 11), `periksa-invarian` berkala |
| Fitur lanjutan menunda MVP | Sedang | Sedang | Skema lengkap sejak awal, fitur dibangun bertahap sesuai `todo.md` |

## 14. Roadmap

| Fase | Isi |
|---|---|
| 0 | Persiapan: repo, lingkungan, keputusan terbuka, `AGENTS.md` terbaca agent |
| 1 | Fondasi backend: proyek Laravel, migration, model, seeder, autentikasi admin, walking skeleton frontend ke backend |
| 2 | Inti stok: Service peminjaman, klaim, verifikasi, dan lainnya, buku besar, pemeriksa invarian, tes konkurensi |
| 3 | API: Controller, Form Request, Resource semua endpoint |
| 4 | Frontend dan kiosk |
| 5 | Panel admin |
| 6 | Laporan PDF, ekspor, audit |
| 7 | Pengujian penuh, keamanan, rilis, UAT |

**Setelah v1.0:** master data karyawan, lokasi simpan, tipe barang habis pakai dengan stok minimum, antrean persetujuan perpanjangan, nomor seri atau asset tag per unit, barcode/QR, stock opname, kalender ketersediaan, pelepasan otomatis pemesanan, pengingat jatuh tempo, foto kondisi, kebijakan keterlambatan, beberapa level peran admin.

## 15. Keputusan terbuka

Agent tidak boleh memilih sendiri keputusan di tabel ini. Nilai bawaan dipakai sementara dan dapat diganti sebelum fase yang disebut.

| ID | Pertanyaan | Bawaan sementara | Dibutuhkan sebelum |
|---|---|---|---|
| K-1 | Frontend memakai JavaScript atau TypeScript? | JavaScript (`<script setup>`), tanpa TypeScript | Fase 1 (walking skeleton) |
| K-2 | Pustaka PDF di server | `barryvdh/laravel-dompdf`, kompatibilitasnya dengan Laravel 13 dan PHP 8.4 diverifikasi dulu | Fase 6 |
| K-3 | Cara menjalankan aplikasi: PHP dari mana, MySQL dari mana (Laragon, Herd, `php artisan serve`) | Dev: `php artisan serve` + `npm run dev` + MySQL Laragon. Produksi ditentukan di K-4 | Fase 0 |
| K-4 | Penyajian produksi dan layanan otomatis | Laravel menyajikan hasil build frontend lewat satu port. MySQL sebagai layanan Windows | Fase 7 |
| K-5 | Daftar awal kategori dan divisi untuk seeder | Hanya kategori "Tanpa Kategori". Divisi dari daftar yang Anda berikan | Fase 1 |
| K-6 | Kunci pembatas lookup kode kiosk | Per IP, gagal beruntun. Konsekuensi: satu orang yang salah lima kali mengunci kiosk selama 5 menit | Fase 3 |
| K-7 | `sesi_idle_menit` dapat diubah dari UI | Middleware yang membandingkan aktivitas terakhir dengan nilai di `settings`. Alternatif: nilai tetap dari `.env` dan hapus dari UI | Fase 1 |
| K-8 | Framework tes | Pest | Fase 1 |

## 16. Riwayat revisi

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 22 Okt 2026 | Dokumen pertama proyek baru. Menggabungkan PRD Laravel (draf Tahap A–E) dengan PRD v5.4 dan SYSTEM-DESIGN v1.4 proyek sebelumnya. Memulihkan aksi cepat "Setujui sesuai laporan", hasil klaim di kartu kiosk, SOP, dan BR-34. Menetapkan empat laporan PDF. Menambahkan FR-S-03 (perintah pemeriksa). Menghapus migrasi data lama |
