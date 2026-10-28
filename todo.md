# todo.md: Sistem Peminjaman & Inventaris Barang Tim IT

Task per fase untuk agent AI. Aturan kerja ada di `AGENTS.md`. Maksud dan kriteria lulus ada di `docs/PRD.md` (T-xx, K-x). Aturan bisnis, skema, Service, API, UI ada di `docs/DESAIN.md` (BR-xx).

Format: `- [ ] **ID** (P) Judul. Rujukan. Verifikasi.` P = M (Must), S (Should), C (Could), urutan pembangunan, bukan pemangkasan. Kerjakan berurutan. Gerbang di akhir tiap fase harus lolos sebelum fase berikutnya. Keputusan **K-x** belum diputuskan: pakai bawaan di PRD bagian 15 dan catat di Log.

---

## Fase 0: Persiapan (tanpa kode aplikasi)

Task 0.2 sampai 0.5 dikerjakan di mesin masing-masing anggota. Hanya A yang mencentang di todo.md.

- [x] **0.1** (M) [A] Buat repo baru dan struktur `backend/`, `frontend/`, `docs/`. Taruh `AGENTS.md`, `todo.md`, `docs/PRD.md`, `docs/DESAIN.md`. `.gitignore` memuat `.env`, `vendor`, `node_modules`, `*.sql`. Commit awal.
- [x] **0.2** (M) [A] Verifikasi lingkungan: PHP 8.4, Composer, Node + npm, MySQL ≥ 8.0.16 (`SELECT VERSION();`). Putuskan K-3 (satu cara menjalankan). Verifikasi: semua perintah versi berjalan.
- [x] **0.3** (M) [A] Setel kata sandi `root` MySQL. Buat database `inventaris_it_dev` dan `inventaris_it_test`. Buat akun `app_inventaris`, `migrator`, `backup` sesuai DESAIN 11.2 (versi dev). Verifikasi: login tiap akun, `app_inventaris` tidak bisa `DROP`.
- [x] **0.4** (M) [A] Isi `my.ini` sesuai DESAIN 11.3 (`bind-address`, `utf8mb4`, zona waktu `+00:00`). Verifikasi `@@sql_mode`, `@@character_set_server`, `@@time_zone`.
- [x] **0.5** (M) [A] Uji `AGENTS.md` terbaca: minta Antigravity dan agent VS Code meringkas "aturan wajib" dari berkas itu. Bila Antigravity tidak memuatnya, buat `.agents/rules/proyek.md` yang merujuk `AGENTS.md`. Bila agent VS Code tidak memuatnya, periksa pengaturan `chat.useAgentsMdFile`.
- [x] **0.6** (M) [A] Dari pemilik proyek: daftar awal divisi dan kategori (K-5), pilihan K-1 dan K-8 dan K-9. Catat di Log.
- [ ] **0.7** (S) [A] GitHub Actions pada setiap PR ke `main`: PHP 8.4 dan MySQL 8.4 sebagai service, database `_test`, `php artisan test`, `./vendor/bin/pint --test`, Node LTS dengan `npm ci` dan `npm run build`. Dikerjakan setelah 1.1 dan 1.19a ada, bukan syarat Gerbang 0. Jadikan required status check hanya setelah workflow pernah berjalan sukses.

**Gerbang 0:** lingkungan siap, kedua agent membaca `AGENTS.md`, K-1/K-3/K-5/K-8 tercatat.

## Fase 1: Fondasi backend

- [x] **1.1** (M) [A] Buat proyek Laravel 13 di `backend/` tanpa starter kit. Verifikasi perintah instalasi ke dokumentasi resmi. Hapus `jobs` dan `password_reset_tokens` dari migration bawaan.
- [x] **1.2** (M) [A] `.env.example` dan konfigurasi sesuai DESAIN 11.3 dan 6: `APP_TIMEZONE=UTC`, `APP_LOCALE=id`, koneksi DB `timezone +00:00`, sesi `database`, cookie `SameSite=strict`. Verifikasi: `php artisan about`.
- [x] **1.3** (M) [A] Pasang Sanctum mode SPA (`statefulApi()`), CORS dengan kredensial, `SANCTUM_STATEFUL_DOMAINS`. Verifikasi di dokumentasi Laravel 13.
- [x] **1.4** (M) [A] Perintah `inventaris:periksa-lingkungan` (versi MySQL, `sql_mode`, charset). Rujukan FR-S-03. Verifikasi: **T-36**.
- [x] **1.5** (M) [B] Migration `users`, `divisions`, `categories`, `settings` beserta CHECK. DESAIN 5.
- [x] **1.6** (M) [B] Migration `items`: `nama_aktif` (generated, unique), CHECK stok, `kategori_id` FK.
- [x] **1.7** (M) [B] Migration `loans` dan `loan_items`.
- [x] **1.8** (M) [B] Migration `return_claims` dan `return_claim_lines` (CHECK empat hasil, BR-05).
- [x] **1.9** (M) [B] Migration `loan_extensions`, `loan_corrections`, `stock_movements` (enum 11 jenis), `audit_logs`.
- [ ] **1.10** (M) [B] Tabel bawaan `sessions`, `cache`, `cache_locks`. Jalankan `migrate:fresh` di `_dev` dan `_test`. Verifikasi: tes memasukkan baris yang melanggar tiap CHECK dan UNIQUE penting, semuanya ditolak database.
- [ ] **1.11** (M) [B] Model Eloquent dan relasi untuk 13 tabel. `$timestamps = false`, cast tanggal `date:Y-m-d`, `$fillable` eksplisit.
- [x] **1.12** (M) [A] `Support\Tanggal` (hari ini Asia/Jakarta, selisih hari) dan `Support\KodeAcak` (alfabet 31 karakter, `PJM-XXXXXX`, `KMB-XXXXXX`). Verifikasi: tes unit termasuk batas tengah malam Jakarta, tanpa karakter I, L, O, 0, 1.
- [ ] **1.13** (M) [B] `Setting` (model atau service) dengan pembacaan terpusat, cast angka, dan validasi rentang di satu tempat. Seeder delapan kunci.
- [ ] **1.14** (M) [B] Seeder divisi dan kategori dari 0.6 plus "Tanpa Kategori". Tanpa data bisnis palsu di produksi (factory hanya untuk dev dan tes).
- [ ] **1.15** (M) [B] Perintah `BuatAdmin` (meminta password interaktif) dan `ResetPasswordAdmin` (mengakhiri semua sesi). Verifikasi: **T-29**.
- [ ] **1.16** (M) [B] Login, logout, `me` di `Auth\LoginController`. Rate limit login 5 per 15 menit per IP dan email. Middleware `AkunAktif`. `login_terakhir` dan audit login. Verifikasi: **T-01**, bagian dari **T-20**.
- [ ] **1.17** (M) [B] Middleware `SesiIdle` memakai `settings.sesi_idle_menit` (K-7). Verifikasi: sesi idle berakhir di server (bagian dari **T-20**).
- [ ] **1.18** (M) [B] Ganti password sendiri. Penangan galat JSON seragam `{kode, pesan, errors?}` untuk 401, 403, 404, 409, 419, 422, 429, 500 tanpa jejak internal.
- [ ] **1.19a** (M) [C] Scaffold `frontend/`: Vue, Vite, Router, Pinia, Axios, Tailwind, proxy `/api` dan `/sanctum`, tiga layout, satu halaman login admin dan satu halaman kiosk placeholder yang dapat dijalankan lokal. Pin versi Node LTS lewat `.nvmrc` dan `engines` (K-9). Bisa mulai setelah 1.1 selesai dan K-1 diputuskan.
- [ ] **1.19b** (M) [A+B+C] Bukti integrasi, setelah 1.17: halaman kiosk memanggil `GET /api/kiosk/config` dan satu POST kiosk sementara. Buktikan cookie sesi Sanctum bekerja, CSRF bekerja untuk POST kiosk (DESAIN 6), tidak ada CORS error. Hapus POST sementara setelah terbukti.
- [ ] **1.20** (M) [B] Tes fase 1 dan `./vendor/bin/pint`.

**Gerbang 1:** `migrate:fresh --seed` berjalan di `_dev`, login admin berfungsi dari frontend, T-01, T-29, T-36 lulus, CHECK terbukti menolak pelanggaran.

## Fase 2: Inti stok dan transaksi

Tahap paling berisiko. Jangan terburu-buru, jangan melewati tes.

- [ ] **2.1** (M) [B] `StatusPeminjaman`: O dan P per `loan_item`, enam status, empat lencana, hari terlambat. DESAIN 4, BR-04, BR-10. Verifikasi: tes unit tabel kasus termasuk unit ditolak kembali menjadi sisa. **T-04**.
- [ ] **2.2** (M) [B] `BukuBesarStok` (jalur tunggal tulis `stock_movements`) dan helper audit. Verifikasi: tes bahwa tiap perubahan stok memunculkan tepat satu baris.
- [ ] **2.3** (M) [B] `StokService`: tambah dan kurangi stok. BR-15. Verifikasi bagian **T-42**.
- [ ] **2.4** (M) [B] `StokService`: tandai rusak, perbaikan, afkir. BR-09, BR-34. Verifikasi: **T-15**, **T-33**.
- [ ] **2.5** (M) [B] `PeminjamanService::buat` untuk peminjaman hari ini: idempotensi, validasi BR-22 dan BR-23, pembaruan bersyarat, kode unik, mutasi `PINJAM`. Verifikasi: **T-02**, **T-11**, **T-32**, **T-34**.
- [ ] **2.6** (S) [B] `PeminjamanService::buat` untuk pemesanan (tanggal masa depan, stok ditahan). BR-23, D-10. Verifikasi: **T-23**.
- [ ] **2.7** (M) [B] `KlaimService::buat`. BR-03, BR-04, BR-10. Verifikasi: **T-07**, **T-08**, **T-12**, **T-25**.
- [ ] **2.8** (M) [B] `VerifikasiService::proses`. BR-05 sampai BR-08, cek BR-01 sebelum commit. Verifikasi: **T-03**, **T-09**, **T-14**.
- [ ] **2.9** (S) [B] `PembatalanService` (peminjam dan admin). BR-24, BR-25. Verifikasi: **T-24**, **T-26**.
- [ ] **2.10** (S) [B] `PerpanjanganService` (peminjam dan admin). BR-11 sampai BR-13, BR-26. Verifikasi: **T-17**, **T-18**, **T-19**, **T-27**.
- [ ] **2.11** (S) [B] `KoreksiService`. BR-16. Verifikasi: tes koreksi jumlah, penolakan bila sudah ada klaim, riwayat tersimpan.
- [ ] **2.12** (M) [B] Perintah `inventaris:periksa-invarian`: hitung ulang BR-01 dan cocokkan dengan jumlah `delta_*`. Exit code non-nol bila ada pelanggaran. Verifikasi: **T-42**.
- [ ] **2.13** (M) [B] Helper tes `assertInvarian()` dipanggil di akhir setiap tes Service.
- [ ] **2.14** (M) [B] Tes konkurensi **nyata** dengan dua proses atau dua koneksi terpisah (satu proses PHP tidak membuktikan `lockForUpdate`). **T-21**, **T-22**.
- [ ] **2.15** (M) [B] Tes acak 1.000 operasi dengan seed tetap supaya dapat diulang, lalu `periksa-invarian`. **T-10**.

**Gerbang 2:** T-02, T-03, T-04, T-07 sampai T-12, T-14, T-15, T-21, T-22, T-34, T-42 lulus. `periksa-invarian` melaporkan 0 pelanggaran setelah T-10.

## Fase 3: API

- [ ] **3.1** (M) [A] Konvensi: struktur route, Form Request per aksi, Resource per entitas, kunci idempotensi di request.
- [ ] **3.2** (M) [A] Kiosk baca: `config`, `items`, `categories`, `divisions`. Hanya nama dan `tersedia` di katalog. Rate limit.
- [ ] **3.3** (M) [A] Kiosk: `POST loans` (peminjaman dan pemesanan). Verifikasi: **T-02**, **T-11**, **T-23**.
- [ ] **3.4** (M) [A] Kiosk: `POST loans/lookup` dengan pembatas laju (K-6). Telepon tidak pernah di respons. Verifikasi: **T-06**.
- [ ] **3.5** (M) [A] Kiosk: klaim, perpanjangan, pembatalan. Verifikasi: **T-07**, **T-17**, **T-24**, **T-25**.
- [ ] **3.6** (M) [A] Admin: items CRUD (soft-delete, BR-14, BR-33), stock-adjustments, mark-damaged, repairs, repair-complete, write-off. **T-05**, **T-15**, **T-33**.
- [ ] **3.7** (M) [A] Admin: categories dan divisions (nonaktifkan, BR-33). **T-28**, **T-30**.
- [ ] **3.8** (M) [A] Admin: loans (filter lengkap, pagination), detail dan lini masa, koreksi, extensions, cancel. **T-13**, **T-19**, **T-26**.
- [ ] **3.9** (M) [A] Admin: return-claims index, show, verify. **T-03**, **T-14**, **T-22**.
- [ ] **3.10** (M) [A] Admin: summary, settings (validasi rentang), audit-logs, users.
- [ ] **3.11** (M) [A] Catat bentuk JSON sebenarnya tiap endpoint di `docs/DESAIN.md` bagian 8 dalam PR endpoint itu, bukan di akhir fase.
- [ ] **3.12** (M) [A] Tes Feature tiap grup endpoint: sukses, 422, 401, 409, 429 yang relevan. **T-31**, **T-35**.

**Gerbang 3:** semua endpoint di DESAIN 8 ada dan bertes. Semua rute `/api/admin/*` menolak 401 tanpa sesi (T-01).

## Fase 4: Frontend dan kiosk

- [ ] **4.1** (M) [C] Rapikan scaffold dari 1.19: router, store Pinia (tanpa persist untuk kode), tiga layout, token desain Tailwind (DESAIN 10.5), font sistem.
- [ ] **4.2** (M) [C] Klien API per endpoint, interceptor galat (401/419 ke login, 409 muat ulang, 429 pesan tunggu), CSRF otomatis.
- [ ] **4.3** (M) [C] Komponen dasar: `TombolBesar`, `FormField`, `InputJumlah`, `InputKode`, `ModalKonfirmasi`, `NotifikasiToast`, `StatusKosong`, `Memuat`, `PesanGalat`, `BadgeStatus`, `BadgeLencana`, `StrukCetak`.
- [ ] **4.4** (M) [C] Kiosk: Beranda dan Katalog (pencarian, filter kategori, stok 0 tidak bisa dipilih). FR-K-01.
- [ ] **4.5** (M) [C] Kiosk: Form pinjam (keranjang, validasi klien, kunci idempotensi UUID, tombol nonaktif saat kirim, pemesanan). FR-K-02.
- [ ] **4.6** (M) [C] Kiosk: Struk dan kode, tata letak cetak. FR-K-03.
- [ ] **4.7** (M) [C] Kiosk: Cek peminjaman dan Kartu (batas kembali, sisa hari, riwayat klaim dengan hasil dan catatan admin). FR-K-04, FR-K-09.
- [ ] **4.8** (M) [C] Kiosk: Klaim dan bukti klaim. FR-K-05, FR-K-06.
- [ ] **4.9** (S) [C] Kiosk: Perpanjangan (dengan bukti) dan Batalkan pemesanan. FR-K-07, FR-K-08, FR-K-12.
- [ ] **4.10** (M) [C] Idle reset memakai `kiosk_idle_detik` dari config, hapus semua isian dan kode. FR-K-10.
- [ ] **4.11** (M) [C] Uji manual: UI-01, UI-02, UI-03, UI-05, UI-06, **T-35**, **T-39**, **T-40**. Ukur waktu form untuk G4.

**Gerbang 4:** alur kiosk lengkap berjalan di tablet, kode tidak ada di URL atau riwayat.

## Fase 5: Panel admin

- [ ] **5.1** (M) [C] Login admin, router guard dari `/api/auth/me`, pesan sesi berakhir. UI-08.
- [ ] **5.2** (M) [C] Layout admin: sidebar (menu geser di layar kecil), navbar, lencana antrean dan stok rusak.
- [ ] **5.3** (M) [C] `TabelData` (urutan, pagination, mode kartu di layar sempit) dan `FilterBar`.
- [ ] **5.4** (M) [C] Dasbor dengan polling 30 detik. FR-A-15, FR-A-19.
- [ ] **5.5** (M) [C] Barang: daftar, form tambah dan ubah, hapus dengan konfirmasi, penyesuaian stok.
- [ ] **5.6** (M) [C] Daftar peminjaman dengan semua filter, detail dan lini masa, aksi koreksi, perpanjang, batalkan.
- [ ] **5.7** (M) [C] Antrean verifikasi dan layar verifikasi: empat kolom hasil, selisih, Simpan aktif hanya bila nol, aksi cepat "Setujui sesuai laporan", catatan wajib. FR-A-04.
- [ ] **5.8** (M) [C] Stok rusak: tandai rusak, selesai diperbaiki, afkir.
- [ ] **5.9** (M) [C] Kategori dan Divisi.
- [ ] **5.10** (S) [C] Pengaturan, Akun admin, Jejak audit.
- [ ] **5.11** (M) [C] Halaman galat 403, 404, 419.
- [ ] **5.12** (M) [C] Uji manual: UI-04, UI-05, UI-07, **T-13**, **T-16**, **T-40**. Badge status identik di kiosk dan admin.

**Gerbang 5:** seluruh alur admin berjalan. T-13, T-16 lulus.

## Fase 6: Laporan, ekspor, audit

- [ ] **6.1** (M) [A] Pilih pustaka PDF (K-2). Verifikasi kompatibilitas dengan Laravel 13 dan PHP 8.4 sebelum memasang.
- [ ] **6.2** (M) [A] `LaporanService` dan `LaporanController`: rekap (filter kategori dan tipe), belum dikembalikan plus pemesanan belum mulai, stok per kategori, riwayat pengembalian dengan catatan. Judul, periode, waktu cetak Asia/Jakarta. **T-41**.
- [ ] **6.3** (M) [C] Halaman Laporan: pilih jenis dan filter, unduh.
- [ ] **6.4** (C) [A] Ekspor CSV/Excel untuk daftar peminjaman dan stok. FR-A-14.
- [ ] **6.5** (S) [A] Tinjau cakupan audit: semua aksi penting menulis `audit_logs` tanpa password atau token.

**Gerbang 6:** T-41 lulus, isi PDF cocok dengan data.

## Fase 7: Pengujian penuh, keamanan, rilis

- [ ] **7.1** (M) [A] Jalankan seluruh T-01..T-42 dan UI-01..UI-08. Catat hasil di `docs/HASIL-UJI.md`.
- [ ] **7.2** (M) [A] Uji kinerja: seed 5.000 transaksi, daftar < 2 detik, peminjaman < 1 detik.
- [ ] **7.3** (M) [A] Tinjau keamanan: mass assignment, escaping, cookie sesi, `APP_DEBUG=false`, `git grep` kredensial, akun DB berhak minimal. **T-20**, **T-37**.
- [ ] **7.4** (M) [A] Build produksi dan penyajian satu port, layanan menyala otomatis (K-4). FR-S-05. Uji restart PC sungguhan.
- [ ] **7.5** (M) [A] Akun DB produksi dan hak (DESAIN 11.2), `.env` produksi, port 3306 tertutup di firewall. **T-37**.
- [ ] **7.6** (M) [A] Backup terjadwal (14 salinan, satu di perangkat lain) dan uji pulih. FR-S-04, **T-38**.
- [ ] **7.7** (M) [C] README (cara pasang dari repo bersih: `composer install`, `.env`, `migrate --seed`, `BuatAdmin`, frontend) dan SOP singkat. G6.
- [ ] **7.8** (M) [A] Buat akun admin cadangan. Tetapkan penanggung jawab utama dan pengganti server.
- [ ] **7.9** (M) [C] UAT dengan pengguna nyata dan sign-off.

**Gerbang 7:** semua T dan UI lulus, UAT disetujui, backup teruji.

---

## Log pekerjaan

(Agent mengisi: tanggal, ID task, hasil satu-dua kalimat, dan nilai bawaan K-x yang dipakai.)

### Log A (Fadhilah)

- 2026-10-22: 0.1 selesai. Struktur folder awal backend/, frontend/, docs/ beserta .gitignore dan dokumen acuan telah disiapkan di repo Git.
- 2026-10-23: 0.2 selesai. Verifikasi lingkungan dan keputusan K-3:
  - Keputusan K-3: opsi A (PHP dari Laravel Herd menjadi PHP default, folder php84 di PATH sistem, di atas XAMPP).
  - Hasil pemeriksaan:
    - PHP 8.4.16 (NTS), Herd, <folder-pengguna>\.config\herd\bin\php84\php.exe
    - Composer 2.9.5, berjalan di atas PHP 8.4.16
    - Node v25.7.0 (bukan LTS), npm 11.10.1
    - Git 2.52.0.windows.1
    - MySQL server 8.4.3 dari Laragon di D:\Laragon, port 3306
    - MySQL sql_mode memuat STRICT_TRANS_TABLES, character_set_server utf8mb4, time_zone masih SYSTEM
    - Ekstensi PHP 8.4 yang diperlukan sudah terverifikasi pada laporan Task 0.2 sebelumnya
  - Catatan untuk task berikutnya:
    - Akun root MySQL masih tanpa kata sandi, diperbaiki di Task 0.3
    - time_zone MySQL harus diubah ke +00:00 di Task 0.4
    - Node v25 bukan LTS. Bila Vite atau Tailwind bermasalah di Fase 1, pertimbangkan pindah ke Node LTS
- Catatan Task 0.2: lingkungan MySQL diganti ke phpBro pada 24 Oktober 2026, lihat entri 0.3.
- 2026-10-26: 0.3 selesai. PHP tetap Herd 8.4.16 (K-3 opsi A); MySQL 8.4.10 berjalan di port 3306 dalam `<folder-pengguna>\AppData\Roaming\phpBro`, menggantikan Laragon. Database dev dan test dibuat dengan charset `utf8mb4` dan collation `utf8mb4_unicode_ci`; akun DB dibuat sesuai DESAIN 11.2 versi dev memakai `scripts/db/setup-dev.sql.example`, lalu salinan lokalnya dihapus. Akun root kini memiliki kata sandi. Verifikasi lulus: root tanpa kata sandi ditolak, tiap akun dapat login melalui TCP `127.0.0.1`, `app_inventaris` ditolak `DROP`, dan migrator dapat `CREATE` serta `DROP TABLE` di database dev.
- Task 0.4 selesai: time_zone MySQL telah diatur ke +00:00; konfigurasi phpBro tidak menggunakan my.ini Laragon.
- Task 1.10: setelah migration, jalankan BAGIAN B scripts/db/setup-dev.sql.example (GRANT DELETE pada sessions, cache, cache_locks) sebagai root.
- Task 1.2 dan 1.20: tes perlu hak DDL pada inventaris_it_test. Saat ini hanya akun migrator yang punya, app_inventaris hanya DML. Putuskan saat 1.2 apakah tes memakai migrator lewat .env.testing atau phpunit.xml.
- 2026-10-26: 0.4 selesai. Hasil verifikasi pemilik: `bind_address` `127.0.0.1`, `character_set_server` `utf8mb4`, dan `sql_mode` memuat `STRICT_TRANS_TABLES`. `time_zone` `+00:00` dan `collation_server` `utf8mb4_unicode_ci` ditetapkan dengan `SET PERSIST` dan tersimpan di `mysqld-auto.cnf` dalam datadir; setelah restart dari dashboard phpBro, nilainya bertahan, `sql_mode` tetap strict, dan kedua nilai persisten muncul di `performance_schema.persisted_variables`. Penyimpangan: Task 0.4 dan DESAIN 11.3 menyebut `my.ini` untuk Laragon, tetapi konfigurasi phpBro ini diterapkan melalui `SET PERSIST`, bukan `my.ini`.
- Dokumen: DESAIN 11.3 (my.ini, Laragon) dan teks Task 0.4 usang. Ganti dengan SET PERSIST untuk phpBro pada PR sinkronisasi dokumen berikutnya.
- phpBro menyimpan datadir per versi mayor MySQL. Pindah versi mayor menghapus pengaturan persisten, ulangi SET PERSIST.
- Berkas scripts/db/setup-dev.sql.example: komentar 'ganti semua placeholder <...>' ambigu. Perjelas bahwa tanda < dan > ikut dihapus, atau sarankan pembangkit kata sandi otomatis, pada PR dokumen berikutnya.
- Task 1.10: setelah migration, jalankan BAGIAN B `scripts/db/setup-dev.sql.example` (GRANT DELETE pada `sessions`, `cache`, `cache_locks`) sebagai root.
- Task 1.2 dan 1.20: tes perlu hak DDL pada `inventaris_it_test`. Saat ini hanya akun `migrator` yang punya, `app_inventaris` hanya DML. Putuskan saat 1.2 apakah tes memakai `migrator` lewat `.env.testing` atau `phpunit.xml`.
- 2026-10-26: 0.5 selesai. Uji mandiri: Antigravity dan agent VS Code lulus; pada chat baru tanpa membaca berkas, keduanya menjawab benar empat pertanyaan tentang AGENTS.md. Tidak perlu membuat `.agents/rules/proyek.md`.
- 2026-10-26: 0.6 selesai. Tim menetapkan K-1 JavaScript, K-5 divisi awal IT, Media, EO dan kategori awal hanya "Tanpa Kategori" (kategori lain ditambahkan admin lewat aplikasi), serta K-8 Pest. Task 0.6 menyebut K-1, K-5, K-8; K-9 juga diputuskan bersamaan: Node 24 (LTS), dipin lewat `.nvmrc` dan `engines` (>=24 <25) pada Task 1.19a karena Node 25 EOL sejak 1 Juni 2026.
- 0.7 (GitHub Actions) ditunda. Pemilik A. Dikerjakan setelah 1.1 (backend) dan 1.19a (frontend) selesai. Versi Node di workflow harus sama dengan K-9 dan .nvmrc. Jadikan required status check hanya setelah workflow pernah berjalan sukses. Bukan syarat Gerbang 0.
- Task 1.14: seeder divisi memakai IT, Media, EO. Kategori awal hanya 'Tanpa Kategori'.
- Task 1.19a: pin Node 24 lewat .nvmrc dan engines (K-9). Anggota yang memakai versi Node lain perlu pindah ke 24 sebelum 1.19a.
- Kriteria Gerbang 0 terpenuhi (0.1 sampai 0.6 selesai). Pernyataan lolos oleh A setelah PR ini ter-merge.
- 2026-10-26 — Task 1.1: Skeleton resmi Laravel 13.38.0 berhasil dipasang di `backend/` tanpa starter kit. Migration jobs (termasuk `job_batches` dan `failed_jobs`) serta definisi `password_reset_tokens` dihapus; migration users, sessions, dan cache dipertahankan. Verifikasi: PHP 8.4.16, Composer 2.9.5, tes bawaan lulus (2 tes/2 assertions), dan Pint lulus. `.env` tidak terlacak Git dan tidak ada SQLite permanen. Tidak ada koneksi atau migrasi MySQL yang dijalankan.
- 2026-10-27 — Task 1.2 selesai. Konfigurasi MySQL, zona waktu UTC/id, dan cookie sesi database strict/httpOnly disesuaikan; template testing memakai `inventaris_it_test` dan akun migrator tanpa kredensial. Verifikasi: `php artisan about`, 3 tes/18 assertions, dan Pint lulus. Tes tidak menghubungi atau mengubah MySQL; berkas environment lokal tetap diabaikan Git.
- 2026-10-27 — Task 1.3 selesai. Sanctum 4.3.3 dipasang untuk SPA berbasis sesi, `statefulApi()` dan CORS kredensial dibatasi ke dua origin frontend dev; tes lulus (6 tes/31 assertions) dan Pint lulus. Migration `personal_access_tokens` yang diterbitkan instalasi dihapus. Tambahkan `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173` ke `.env` lokal.
- 2026-10-27 — Task 1.4 selesai. Perintah pemeriksa lingkungan dan kelas pemeriksaan murni dibuat; 18 tes/54 assertions dan Pint lulus menggunakan data tiruan tanpa koneksi database. Verifikasi terhadap MySQL asli dilakukan manual oleh pemilik. Kueri memakai @@global.sql_mode.
- 2026-10-28 — Task 1.12 selesai. Utilitas Tanggal (hariIni zona Asia/Jakarta, selisih hari kalender bertanda) dan KodeAcak (alfabet 31 karakter tanpa karakter ambigu I, L, O, 0, 1, generator PJM-XXXXXX dan KMB-XXXXXX berbasis random_int) dibuat. Verifikasi: 32 tes unit baru lulus (mencakup batas tengah malam Jakarta, selisih hari kalender, dan format kode), seluruh 50 tes backend lulus (1790 assertions), dan Pint lulus tanpa masalah format.
  
### Log B (idrsabdlh-coder)

- 2026-10-27: 1.5 selesai. Migration `users` (disesuaikan dari bawaan Laravel), `divisions`, `categories`, `settings` dengan CHECK `aktif`. Diverifikasi di MySQL 8.4.10 (`inventaris_it_dev`): `migrate:fresh` berhasil, `SHOW CREATE TABLE` sesuai DESAIN 5.
- 2026-10-28: 1.6 selesai. Migration `items` dengan `nama_aktif` (generated stored, UNIQUE), FK `kategori_id` RESTRICT, dan lima CHECK termasuk `tersedia + rusak <= total`. Diverifikasi: insert melanggar CHECK, nama aktif ganda, dan FK ditolak; nama barang yang sudah di-soft-delete boleh dipakai lagi.
- 2026-10-28: 1.7 selesai. Migration `loans` (CHECK `batas_kembali >= tanggal_pinjam`, UNIQUE `kode_pinjam` dan `idempotency_key`, FK RESTRICT) dan `loan_items` (CHECK `qty_dipinjam >= 1`, UNIQUE `loan_id, item_id`). Diverifikasi di MySQL 8.4.10: insert melanggar CHECK, duplikat, enum tidak valid, dan FK ditolak, serta penghapusan barang yang sudah dipinjam ditolak (RESTRICT).
- 2026-10-28: 1.8 selesai. Migration `return_claims` (CHECK `hari_terlambat >= 0`, UNIQUE `kode_klaim` dan `idempotency_key`) dan `return_claim_lines`. CHECK BR-05 ditulis dengan `IS NOT NULL` eksplisit pada cabang kedua, karena versi harfiah meloloskan baris dengan sebagian kolom `qty_*` NULL (CHECK MySQL lolos pada hasil NULL). Nama UNIQUE dibuat manual (`rcl_klaim_item_kondisi_unique`) karena nama otomatis melebihi 64 karakter. Diverifikasi di MySQL 8.4.10: sebagian-NULL, jumlah beda, nilai negatif, qty 0, kombinasi ganda, dan hari terlambat negatif ditolak.
- 2026-10-28: 1.9 selesai. Migration `loan_extensions`, `loan_corrections`, `stock_movements` (enum 11 jenis), `audit_logs` (kolom JSON). Diverifikasi: jenis tidak dikenal, FK, penghapusan barang yang punya riwayat stok (RESTRICT), dan JSON tidak valid ditolak.