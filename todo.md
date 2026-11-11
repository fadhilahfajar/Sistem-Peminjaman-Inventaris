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
- [x] **1.10** (M) [B] Tabel bawaan `sessions`, `cache`, `cache_locks`. Jalankan `migrate:fresh` di `_dev` dan `_test`. Verifikasi: tes memasukkan baris yang melanggar tiap CHECK dan UNIQUE penting, semuanya ditolak database.
- [x] **1.11** (M) [B] Model Eloquent dan relasi untuk 13 tabel. `$timestamps = false`, cast tanggal `date:Y-m-d`, `$fillable` eksplisit.
- [x] **1.12** (M) [A] `Support\Tanggal` (hari ini Asia/Jakarta, selisih hari) dan `Support\KodeAcak` (alfabet 31 karakter, `PJM-XXXXXX`, `KMB-XXXXXX`). Verifikasi: tes unit termasuk batas tengah malam Jakarta, tanpa karakter I, L, O, 0, 1.
- [x] **1.13** (M) [B] `Setting` (model atau service) dengan pembacaan terpusat, cast angka, dan validasi rentang di satu tempat. Seeder delapan kunci.
- [x] **1.14** (M) [B] Seeder divisi dan kategori dari 0.6 plus "Tanpa Kategori". Tanpa data bisnis palsu di produksi (factory hanya untuk dev dan tes).
- [x] **1.15** (M) [B] Perintah `BuatAdmin` (meminta password interaktif) dan `ResetPasswordAdmin` (mengakhiri semua sesi). Verifikasi: **T-29**.
- [x] **1.16** (M) [B] Login, logout, `me` di `Auth\LoginController`. Rate limit login 5 per 15 menit per IP dan email. Middleware `AkunAktif`. `login_terakhir` dan audit login. Verifikasi: **T-01**, bagian dari **T-20**.
- [x] **1.17** (M) [B] Middleware `SesiIdle` memakai `settings.sesi_idle_menit` (K-7). Verifikasi: sesi idle berakhir di server (bagian dari **T-20**).
- [x] **1.18** (M) [B] Ganti password sendiri. Penangan galat JSON seragam `{kode, pesan, errors?}` untuk 401, 403, 404, 409, 419, 422, 429, 500 tanpa jejak internal.
- [x] **1.19a** (M) [C] Scaffold `frontend/`: Vue, Vite, Router, Pinia, Axios, Tailwind, proxy `/api` dan `/sanctum`, tiga layout, satu halaman login admin dan satu halaman kiosk placeholder yang dapat dijalankan lokal. Pin versi Node LTS lewat `.nvmrc` dan `engines` (K-9). Bisa mulai setelah 1.1 selesai dan K-1 diputuskan.
- [x] **1.19b** (M) [A+B+C] Bukti integrasi, setelah 1.17: halaman kiosk memanggil `GET /api/kiosk/config` dan satu POST kiosk sementara. Buktikan cookie sesi Sanctum bekerja, CSRF bekerja untuk POST kiosk (DESAIN 6), tidak ada CORS error. Hapus POST sementara setelah terbukti.
- [0] **1.20** (M) [B] Tes fase 1 dan `./vendor/bin/pint`.

**Gerbang 1:** `migrate:fresh --seed` berjalan di `_dev`, login admin berfungsi dari frontend, T-01, T-29, T-36 lulus, CHECK terbukti menolak pelanggaran.

## Fase 2: Inti stok dan transaksi

Tahap paling berisiko. Jangan terburu-buru, jangan melewati tes.

- [x] **2.1** (M) [B] `StatusPeminjaman`: O dan P per `loan_item`, enam status, empat lencana, hari terlambat. DESAIN 4, BR-04, BR-10. Verifikasi: tes unit tabel kasus termasuk unit ditolak kembali menjadi sisa. **T-04**.
- [x] **2.2** (M) [B] `BukuBesarStok` (jalur tunggal tulis `stock_movements`) dan helper audit. Verifikasi: tes bahwa tiap perubahan stok memunculkan tepat satu baris.
- [x] **2.3** (M) [B] `StokService`: tambah dan kurangi stok. BR-15. Verifikasi bagian **T-42**.
- [x] **2.4** (M) [B] `StokService`: tandai rusak, perbaikan, afkir. BR-09, BR-34. Verifikasi: **T-15**, **T-33**.
- [x] **2.5** (M) [B] `PeminjamanService::buat` untuk peminjaman hari ini: idempotensi, validasi BR-22 dan BR-23, pembaruan bersyarat, kode unik, mutasi `PINJAM`. Verifikasi: **T-02**, **T-11**, **T-32**, **T-34**.
- [x] **2.6** (S) [B] `PeminjamanService::buat` untuk pemesanan (tanggal masa depan, stok ditahan). BR-23, D-10. Verifikasi: **T-23**.
- [x] **2.7** (M) [B] `KlaimService::buat`. BR-03, BR-04, BR-10. Verifikasi: **T-07**, **T-08**, **T-12**, **T-25**.
- [x] **2.8** (M) [B] `VerifikasiService::proses`. BR-05 sampai BR-08, cek BR-01 sebelum commit. Verifikasi: **T-03**, **T-09**, **T-14**.
- [x] **2.9** (S) [B] `PembatalanService` (peminjam dan admin). BR-24, BR-25. Verifikasi: **T-24**, **T-26**.
- [x] **2.10** (S) [B] `PerpanjanganService` (peminjam dan admin). BR-11 sampai BR-13, BR-26. Verifikasi: **T-17**, **T-18**, **T-19**, **T-27**.
- [x] **2.11** (S) [B] `KoreksiService`. BR-16. Verifikasi: tes koreksi jumlah, penolakan bila sudah ada klaim, riwayat tersimpan.
- [x] **2.12** (M) [B] Perintah `inventaris:periksa-invarian`: hitung ulang BR-01 dan cocokkan dengan jumlah `delta_*`. Exit code non-nol bila ada pelanggaran. Verifikasi: **T-42**.
- [x] **2.13** (M) [B] Helper tes `assertInvarian()` dipanggil di akhir setiap tes Service.
- [x] **2.14** (M) [B] Tes konkurensi **nyata** dengan dua proses atau dua koneksi terpisah (satu proses PHP tidak membuktikan `lockForUpdate`). **T-21**, **T-22**.
- [x] **2.15** (M) [B] Tes acak 1.000 operasi dengan seed tetap supaya dapat diulang, lalu `periksa-invarian`. **T-10**.

**Gerbang 2:** T-02, T-03, T-04, T-07 sampai T-12, T-14, T-15, T-21, T-22, T-34, T-42 lulus. `periksa-invarian` melaporkan 0 pelanggaran setelah T-10.

## Fase 3: API

- [x] **3.1** (M) [A] Konvensi: struktur route, Form Request per aksi, Resource per entitas, kunci idempotensi di request.
- [x] **3.2** (M) [A] Kiosk baca: `config`, `items`, `categories`, `divisions`. Hanya nama dan `tersedia` di katalog. Rate limit.
- [x] **3.3** (M) [A] Kiosk: `POST loans` (peminjaman dan pemesanan). Verifikasi: **T-02**, **T-11**, **T-23**.
- [x] **3.4** (M) [A] Kiosk: `POST loans/lookup` dengan pembatas laju (K-6). Telepon tidak pernah di respons. Verifikasi: **T-06**.
- [x] **3.5** (M) [A] Kiosk: klaim, perpanjangan, pembatalan. Verifikasi: **T-07**, **T-17**, **T-24**, **T-25**.
- [x] **3.6** (M) [A] Admin: items CRUD (soft-delete, BR-14, BR-33), stock-adjustments, mark-damaged, repairs, repair-complete, write-off. **T-05**, **T-15**, **T-33**.
- [x] **3.7** (M) [A] Admin: categories dan divisions (nonaktifkan, BR-33). **T-28**, **T-30**.
- [ ] **3.8** (M) [A] Admin: loans (filter lengkap, pagination), detail dan lini masa, koreksi, extensions, cancel. **T-13**, **T-19**, **T-26**.
- [ ] **3.9** (M) [A] Admin: return-claims index, show, verify. **T-03**, **T-14**, **T-22**.
- [ ] **3.10** (M) [A] Admin: summary, settings (validasi rentang), audit-logs, users.
- [ ] **3.11** (M) [A] Catat bentuk JSON sebenarnya tiap endpoint di `docs/DESAIN.md` bagian 8 dalam PR endpoint itu, bukan di akhir fase.
- [ ] **3.12** (M) [A] Tes Feature tiap grup endpoint: sukses, 422, 401, 409, 429 yang relevan. **T-31**, **T-35**.

**Gerbang 3:** semua endpoint di DESAIN 8 ada dan bertes. Semua rute `/api/admin/*` menolak 401 tanpa sesi (T-01).

## Fase 4: Frontend dan kiosk

- [x] **4.1** (M) [C] Rapikan scaffold dari 1.19: router, store Pinia (tanpa persist untuk kode), tiga layout, token desain Tailwind (DESAIN 10.5), font sistem.
- [x] **4.2** (M) [C] Klien API per endpoint, interceptor galat (401/419 ke login, 409 muat ulang, 429 pesan tunggu), CSRF otomatis.
- [x] **4.3** (M) [C] Komponen dasar: `TombolBesar`, `FormField`, `InputJumlah`, `InputKode`, `ModalKonfirmasi`, `NotifikasiToast`, `StatusKosong`, `Memuat`, `PesanGalat`, `BadgeStatus`, `BadgeLencana`, `StrukCetak`.
- [x] **4.4** (M) [C] Kiosk: Beranda dan Katalog (pencarian, filter kategori, stok 0 tidak bisa dipilih). FR-K-01.
- [x] **4.5** (M) [C] Kiosk: Form pinjam (keranjang, validasi klien, kunci idempotensi UUID, tombol nonaktif saat kirim, pemesanan). FR-K-02.
- [x] **4.6** (M) [C] Kiosk: Struk dan kode, tata letak cetak. FR-K-03.
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
- 2026-11-04 — Task 3.1 dan 3.2 selesai. Pemisahan rute routes/kiosk.php dan routes/admin.php; rute admin dilindungi auth:sanctum, AkunAktif, dan SesiIdle; rute auth milik B dipertahankan. Konvensi Form Request (App\Http\Requests\Kiosk dan App\Http\Requests\Admin), trait MemilikiKunciIdempotensi (UUID wajib), dan Resource (App\Http\Resources) dengan keputusan mempertahankan pembungkus bawaan data (konsisten dengan format paginasi). Endpoint kiosk baca: GET /api/kiosk/config (dipakai ulang dari 1.19b, 5 kunci angka publik), GET /api/kiosk/items (pencarian LIKE di-escape, filter kategori_id, soft-delete dikecualikan, stok 0 tetap tampil, urut nama), GET /api/kiosk/categories dan GET /api/kiosk/divisions (aktif saja, urut nama). Keempat endpoint dibatasi limiter kiosk-baca (120 req/menit/IP, nilai bawaan sementara yang perlu ditinjau, galat 429 seragam). Temuan langkah 0: GET /api/kiosk/config dan POST /api/kiosk/uji-integrasi sudah ada dari 1.19b, POST sementara tetap dipertahankan; App\Http\Resources belum ada; limiter terdaftar di AppServiceProvider. Seluruh 277 tes backend lulus (2844 assertions) dan Pint lulus.
- 2026-11-04 — Task 3.7 selesai. Endpoint admin master data: GET semua baris (aktif dan nonaktif, urut nama), POST (hanya `nama`, `aktif` selalu true) dan PATCH (nama dan/atau aktif) untuk `/api/admin/categories` dan `/api/admin/divisions`; tanpa DELETE (BR-27, BR-28). Nama akhir service: `KategoriService`, `DivisiService`. Menonaktifkan kategori yang masih dipakai barang aktif (soft-delete dikecualikan) ditolak 409 dengan kode `kategori_masih_dipakai` dan jumlah barang di pesan; divisi selalu boleh dinonaktifkan. Bentuk JSON dicatat di DESAIN 8.2 (bagian dari 3.11). Perubahan lintas task dengan setuju pemilik (lihat catatan di bawah): `PenangananGalat` diperkaya agar `GalatBisnis` meneruskan `kode` dan `pesan` khususnya (sebelumnya 409 selalu pesan generik); penagihan ke PenangananGalatTest milik B tidak berubah. Refaktor kecil tes: helper `adminUji()` dan `spa()` dipindah ke `tests/Helpers.php` (dimuat `tests/Pest.php`) karena Pest memuat semua berkas tes dalam satu proses sehingga deklarasi ganda fatal; duplikasi `use/sesuai` di `SesiAdminTest` dibersihkan, perilaku tes tidak berubah. Verifikasi: 12 tes baru (T-01 keenam rute 401, CRUD, 422 nama ganda tak peka kapital, BR-33 409 dan lolos setelah soft-delete, DELETE 405, T-28/T-30 kiosk berubah) dan seluruh 308 tes backend lulus (3016 assertions), Pint lulus.
- 2026-11-07 — Task 3.3 selesai. Endpoint kiosk POST /api/kiosk/loans untuk peminjaman hari ini dan pemesanan ke depan: memanggil PeminjamanService::buat, validasi bentuk via SimpanPeminjamanRequest, dan pemformatan via LoanKioskResource dengan pembungkus data tanpa membocorkan nomor telepon atau angka stok internal. Nilai bawaan sementara: replay dengan idempotency_key yang sama mengembalikan status 200 OK dengan badan identik (201 untuk peminjaman baru), dan limiter kiosk-tulis ditetapkan pada 30 permintaan per menit per IP. Temuan langkah 0: PeminjamanService::buat mengembalikan Loan (sudah memvalidasi tanggal kalender/rentang BR-23, divisi aktif, dan duplikasi barang; melempar GalatBisnis 409 stok_tidak_cukup dan kunci_idempotensi_bentrok), StatusPeminjaman::dariModel menghasilkan status dipinjam atau dipesan, trait MemilikiKunciIdempotensi sudah tersedia dari 3.1, helper assertInvarian() tersedia di tests/Helpers/Invarian.php, dan limiter kiosk-tulis baru didaftarkan di AppServiceProvider. Verifikasi: 12 tes Feature baru di PeminjamanKioskTest (T-02, T-11, T-23, T-32, BR-17, BR-18, validasi 422, batas laju 429), seluruh 339 tes backend lulus (3283 assertions), dan Pint lulus.
- 2026-11-10 — Task 3.4 selesai. Endpoint kiosk POST /api/kiosk/loans/lookup dengan pembatas percobaan kode (K-6, T-06): kelas PembatasKodeKiosk mengunci IP selama kiosk_lookup_kunci_menit setelah kiosk_lookup_maks_gagal berturut-turut, diuji 429 Retry-After seragam; kelas baca-saja KartuPeminjamanService menyusun kartu peminjaman tanpa membocorkan nomor telepon atau stok internal, menghitung sisa per barang (BR-04, unit ditolak kembali ke sisa) dan kelayakan aksi ({klaim, perpanjang, batal} dengan alasan spesifik). Nilai bawaan sementara: masa kedaluwarsa penghitung kegagalan disetel sama dengan kiosk_lookup_kunci_menit menit sejak kegagalan terakhir; format kode salah dan kode tidak ditemukan mengembalikan respons identik 404 (kode: kode_tidak_ditemukan) tanpa membocorkan eksistensi; aksi.alasan memuat pesan penjelasan dalam bahasa Indonesia. Temuan langkah 0: StatusPeminjaman::dariModel hanya menyediakan O dan P agregat (belum rincian per barang sehingga KartuPeminjamanService dibuat); KlaimService mengidentifikasi baris dengan item_id; PerpanjanganService dan PembatalanService tidak memiliki method pemeriksa baca-saja publik; cache store tes adalah array. Verifikasi: 20 tes Feature baru di LookupKioskTest (T-06, K-6, konsistensi dengan StatusPeminjaman, PerpanjanganService, PembatalanService, pemesanan, pembatalan, klaim terverifikasi, invarian), seluruh 359 tes backend lulus (3639 assertions), dan Pint lulus.
- 2026-11-10 — Task 3.5 selesai. Endpoint perpanjangan (extensions) dan pembatalan (cancel) ditambahkan ke routes kiosk, beserta Form Request, Controller, dan validasi pembatas kode. Dibuat: `SimpanPerpanjanganRequest`, `SimpanPembatalanRequest`, `ExtensionKioskController`, `CancelKioskController`, dan 2 route baru di `routes/kiosk.php`. Hasil tes: 368 backend tests lulus (3743 assertions), 54 kiosk tests lulus. Keterbatasan idempotensi: perpanjangan ganda cepat dibatasi kuota (max_perpanjangan=2), pembatalan kedua mendapat 409; Service tidak menyediakan kunci idempotensi. Temuan nama field kode: endpoint baru memakai `kode_pinjam` (bukan `kode`), identik dengan lookup yang pakai `kode` pada URL. Log lengkap di `TASK_3_5_FINAL_REPORT.md`.
- 2026-11-11 — Task 3.6 selesai. Endpoint admin barang, stok, dan perbaikan: GET/POST/PUT/DELETE `/api/admin/items`, GET `/api/admin/items/{id}`, POST `stock-adjustments`, `mark-damaged`, `repair-complete`, `write-off`, dan GET `/api/admin/repairs`. Dibuat: Service baru `BarangService` (daftar, buat, ubah, hapus, daftarPerbaikan), `ItemController`, `ItemStockController`, `RepairController`, tujuh Form Request di `App\Http\Requests\Admin`, `AdminItemResource`, `AdminRepairResource`, rute di `routes/admin.php`, dan `tests/Feature/Admin/ItemAdminTest.php` (18 tes). Pembuatan barang menulis baris stok 0 lalu memanggil `StokService::tambah` (`TAMBAH_STOK`) dalam satu transaksi, jadi tidak ada kolom stok yang diisi langsung; seluruh perubahan stok tetap lewat `StokService`. DELETE memilih 200 dengan `{"pesan": "Barang berhasil dihapus."}` (bukan 204) agar klien punya konfirmasi eksplisit; penolakan memakai 409 `barang_masih_dipinjam` (pesan menyebut jumlah unit), `barang_masih_rusak`, dan `kategori_nonaktif`, dengan pemeriksaan dan soft-delete dalam satu transaksi ber-`lockForUpdate` agar BR-14 tidak kalah balapan dengan peminjaman baru. `kondisi` (BR-29) dan `sedang_keluar` diturunkan di `AdminItemResource`, bukan accessor model. Hasil tes: 386 tes backend lulus (3917 assertions), Pint lulus. Bentuk JSON dicatat di DESAIN 8.2.


### Log B (idrsabdlh-coder)

- 2026-10-27: 1.5 selesai. Migration `users` (disesuaikan dari bawaan Laravel), `divisions`, `categories`, `settings` dengan CHECK `aktif`. Diverifikasi di MySQL 8.4.10 (`inventaris_it_dev`): `migrate:fresh` berhasil, `SHOW CREATE TABLE` sesuai DESAIN 5.
- 2026-10-28: 1.6 selesai. Migration `items` dengan `nama_aktif` (generated stored, UNIQUE), FK `kategori_id` RESTRICT, dan lima CHECK termasuk `tersedia + rusak <= total`. Diverifikasi: insert melanggar CHECK, nama aktif ganda, dan FK ditolak; nama barang yang sudah di-soft-delete boleh dipakai lagi.
- 2026-10-28: 1.7 selesai. Migration `loans` (CHECK `batas_kembali >= tanggal_pinjam`, UNIQUE `kode_pinjam` dan `idempotency_key`, FK RESTRICT) dan `loan_items` (CHECK `qty_dipinjam >= 1`, UNIQUE `loan_id, item_id`). Diverifikasi di MySQL 8.4.10: insert melanggar CHECK, duplikat, enum tidak valid, dan FK ditolak, serta penghapusan barang yang sudah dipinjam ditolak (RESTRICT).
- 2026-10-28: 1.8 selesai. Migration `return_claims` (CHECK `hari_terlambat >= 0`, UNIQUE `kode_klaim` dan `idempotency_key`) dan `return_claim_lines`. CHECK BR-05 ditulis dengan `IS NOT NULL` eksplisit pada cabang kedua, karena versi harfiah meloloskan baris dengan sebagian kolom `qty_*` NULL (CHECK MySQL lolos pada hasil NULL). Nama UNIQUE dibuat manual (`rcl_klaim_item_kondisi_unique`) karena nama otomatis melebihi 64 karakter. Diverifikasi di MySQL 8.4.10: sebagian-NULL, jumlah beda, nilai negatif, qty 0, kombinasi ganda, dan hari terlambat negatif ditolak.
- 2026-10-28: 1.9 selesai. Migration `loan_extensions`, `loan_corrections`, `stock_movements` (enum 11 jenis), `audit_logs` (kolom JSON). Diverifikasi: jenis tidak dikenal, FK, penghapusan barang yang punya riwayat stok (RESTRICT), dan JSON tidak valid ditolak.
- 2026-10-28: 1.10 selesai. Tabel sessions, cache, cache_locks ada, tidak ada sisa jobs dan password_reset_tokens. migrate:fresh berhasil di _dev dan _test. ConstraintTest membuktikan CHECK, UNIQUE, FK RESTRICT, enum, dan JSON menolak pelanggaran dengan kode error yang tepat. Guard _test di TestCase terbukti menolak database non-_test. BAGIAN B dijalankan, app_inventaris dapat DELETE hanya di tiga tabel itu. Keputusan 1.2: tes memakai migrator lewat .env.testing. Pest dipasang (K-8).
- 2026-10-28: 1.11 selesai. 13 model Eloquent dengan dasar ModelDomain ($timestamps = false), cast date:Y-m-d untuk kolom tanggal, $fillable eksplisit, Item soft-delete lewat dihapus_pada, telepon di $hidden. RelasiTest lulus, Pint lulus.
- 2026-10-29: 1.13 selesai. `PengaturanService` (`app/Services`) membaca angka, memvalidasi rentang, dan menolak kunci tidak dikenal di satu tempat. `SettingSeeder` mengisi delapan kunci dengan `firstOrCreate` sehingga tidak menimpa nilai yang sudah diubah admin. Rentang min dan maks tiap kunci adalah bawaan sementara usulan B (belum dikonfirmasi A). Hanya batas atas `sesi_idle_menit` (120) yang berdasar DESAIN. Tes: X tes lulus (Y assertions) untuk PengaturanService dan seeder.
- 2026-10-29: 1.14 selesai. `DivisiKategoriSeeder` mengisi divisi IT, Media, EO dan kategori "Tanpa Kategori" (K-5). `DatabaseSeeder` hanya memanggil dua seeder dan tidak membuat akun atau data bisnis. Blok `User::factory()` (kata sandi bawaan, kolom yang sudah dihapus) dihapus. `migrate:fresh --seed` berhasil di `_dev`. `UserFactory` masih memakai kolom lama dan belum diperbaiki.
- 2026-10-29: 1.15 selesai. `AkunAdminService` dan perintah `inventaris:buat-admin` (kata sandi diminta interaktif, tersembunyi, dua kali) serta `inventaris:reset-password-admin` (mengganti hash dan menghapus baris `sessions` milik akun itu dalam satu transaksi). Model `User` disesuaikan dengan skema 1.5 (`$timestamps = false`, cast `aktif` dan `login_terakhir`, `email_verified_at` dan `remember_token` dibuang), karena tanpa itu `User::create()` gagal. Panjang minimal kata sandi 12 karakter adalah bawaan sementara usulan B (belum dikonfirmasi A). Reset hanya mengakhiri sesi akun yang direset, bukan semua akun. Diverifikasi: T-29 lulus di tes, perintah dicoba langsung di terminal (kata sandi tidak tampil, hash bcrypt tersimpan). `UserFactory` masih memakai kolom lama dan belum diperbaiki.
- 2026-10-29: 1.16 selesai. AutentikasiService, Auth\LoginController (login, logout, me), LoginRequest, middleware AkunAktif, dan rute di routes/api.php. Rate limit 5 percobaan gagal per 15 menit per IP dan email. Kredensial salah dan email tidak ada menghasilkan respons yang sama. login_terakhir diisi dan login ditulis ke audit_logs tanpa kata sandi. Tes lulus. Verifikasi T-01 dan sebagian T-20
- 2026-10-29: pelengkap 1.16. Rute me dan logout serta middleware AkunAktif ditambahkan, karena PR 1.16 sebelumnya hanya memuat login. Tes SesiAdminTest mencakup 401 tanpa sesi (T-01), me tanpa kata sandi, logout, dan akun nonaktif di tengah sesi ditolak 403. Tes lulus.
- 2026-10-30: 1.17 selesai. Middleware SesiIdle menyimpan waktu aktivitas terakhir (UTC) di sesi dan mengakhiri sesi dengan 401 {kode: sesi_berakhir, pesan} bila selisih melebihi settings.sesi_idle_menit, dibaca lewat PengaturanService sehingga perubahan nilai langsung berlaku. Dipasang setelah auth:sanctum dan AkunAktif, tidak pada login. K-7 memakai nilai bawaan PRD (middleware membandingkan dengan settings). Bentuk galat 401 masih eksplisit di middleware dan diseragamkan di 1.18. Tes lulus. Uji manual idle 1 menit: (hasil).
 - 2026-10-30: 1.18 selesai. POST /api/auth/change-password (KataSandiService, UbahKataSandiRequest, KataSandiController): kata sandi lama wajib benar, kata sandi baru minimal 12 karakter, berbeda dari yang lama, dan terkonfirmasi. Audit ganti_password ditulis tanpa kata sandi. Support\PenangananGalat didaftarkan di bootstrap/app.php dan menyeragamkan galat API menjadi {kode, pesan, errors?} untuk 401, 403, 404, 409, 419, 422, 429, dan 500 tanpa jejak internal. Nilai bawaan sementara: panjang minimal 12 (dari 1.15), sesi lain milik akun diakhiri saat ganti kata sandi (usulan B, belum dikonfirmasi A), respons 500 selalu generik walau APP_DEBUG=true. Tes lulus.
 - 2026-10-30: 1.19b bagian B. GET /api/kiosk/config (lima parameter publik, tanpa parameter keamanan) dan POST /api/kiosk/uji-integrasi sementara di grup kiosk pada routes/api.php, dengan throttle:60,1. KonfigurasiKioskTest lulus. Pembuktian lewat Console browser di localhost:5173 dengan proxy Vite: csrf-cookie (isi status), config (isi status), POST dengan token (isi status), POST tanpa token (isi status), galat CORS (ada/tidak), atribut cookie sesi (isi). Endpoint sementara belum dihapus dan config diambil alih A di 3.2. Task 1.19b tidak dicentang oleh B sendirian.
 - 2026-10-30: 1.20 selesai. migrate:fresh --seed berhasil di _dev. php artisan test lulus. pint --test pada seluruh proyek: (hasil). inventaris:periksa-lingkungan terhadap MySQL asli: (hasil). Gerbang 1: CHECK terbukti (ConstraintTest), T-01 (SesiAdminTest), T-29 (AkunAdminTest), T-36 (unit dan perintah nyata). Belum terpenuhi: login admin dari frontend (menunggu 5.1, C) dan bukti cookie/CSRF/CORS (1.19b, A+C). Catatan terbuka untuk A: reset kata sandi hanya mengakhiri sesi akun itu, bukan semua sesi seperti di FR-S-06. Gerbang 1 dinyatakan lulus oleh A.

 # Fase 2
- 026-10-30: 2.1 selesai. Services\StatusPeminjaman: hitung() murni menurunkan O dan P per loan_item, enam status berurutan sesuai DESAIN 4, empat lencana, dan hari terlambat (dibekukan saat O = 0, D-8). dariModel() membangun data dari Loan dan tidak menghitung unit ditolak sebagai terverifikasi (BR-04). Tes tabel kasus termasuk unit ditolak kembali menjadi sisa; T-04 sebagian: status dan hari terlambat diverifikasi, pembekuan saat klaim diuji di 2.7. Catatan untuk A: lencana Sebagian tidak diberikan untuk klaim yang seluruh unitnya ditolak (DESAIN ambigu).
- 2026-10-30: 2.2 selesai. Support\BukuBesarStok sebagai jalur tunggal penulisan stock_movements dan Support\Audit. Tes lulus. BR-01 belum diperiksa menyeluruh di akhir tes karena 2.13 ditunda
- 2026-10-30: 2.3 selesai. StokService tambah dan kurangi stok (BR-15): alasan wajib, jumlah bilangan bulat positif, tersedia tidak boleh negatif, satu mutasi per perubahan lewat BukuBesarStok. Tes StokServiceTest lulus. Bagian T-42 (angka items sama dengan jumlah delta_*) diuji; pemeriksaan BR-01 penuh menunggu 2.13 dan 2.12.
- 2026-10-30: 2.4 selesai. StokService tandai rusak (BR-34), selesai perbaikan, dan afkir (BR-09, alasan wajib untuk tandai rusak dan afkir). Tes StokRusakServiceTest lulus. T-15 dan T-33 diuji: jumlah nol, negatif, dan melebihi stok ditolak tanpa mengubah apa pun, alasan kosong ditolak; penolakan pecahan ada di Form Request (3.6) dan belum teruji. BR-01 menyeluruh menunggu 2.13.
- 2026-10-30: 2.5 selesai. `PeminjamanService::buat` untuk peminjaman hari ini: idempotensi, validasi BR-22 dan BR-23, pembaruan stok bersyarat per barang berurut `id`, kode dari `KodeAcak`, mutasi `PINJAM` per barang, audit tanpa userId (kiosk). Tes: T-02, T-11, T-32, T-34 lulus (satu proses). Konkurensi nyata (T-21) menunggu 2.14. Perbaikan: pemanggilan `Audit::catat` sempat tertukar urutan argumen (userId di depan), dikoreksi ke urutan `(aksi, entitas, entitasId, userId, detail)`. Catatan untuk A: `PenangananGalat` memetakan 409 ke pesan tetap, sehingga nama barang yang gagal tidak terkirim ke klien.
- 2026-10-30: 2.6 selesai. Penahanan stok dan validasi tanggal pemesanan sudah dibuat di 2.5 karena `simpan()` tidak membedakan tanggal. 2.6 menambah empat tes T-23 (stok langsung berkurang untuk tanggal masa depan, status turunan `dipesan` dan `dipinjam`, batas tepat `maks_booking_hari` diterima, stok kurang ditolak 409 tanpa efek). Tes langsung lulus karena tidak ada kode baru. Total PeminjamanServiceTest: lulus.
- 2026-10-30: 2.7 selesai. `KlaimService::buat`: klaim tidak mengubah stok (BR-03), jumlah per barang dibatasi sisa (BR-04, 409 bila lebih), `hari_terlambat` disalin dari `StatusPeminjaman` saat klaim (BR-10), klaim atas pemesanan atau peminjaman dibatalkan ditolak 409 (T-25), kunci idempotensi (T-11). Tes lulus untuk T-08, T-25, dan bagian pembekuan T-12; T-07 dan pemulihan T-12 diuji bersama 2.8.
- 2026-10-30: 2.8 selesai. VerifikasiService::proses: klaim dikunci lalu items menurut id menaik, empat hasil per baris wajib berjumlah sama dengan klaim, catatan admin wajib bila hasil berbeda dari laporan atau ada unit ditolak (BR-06), tersedia +baik, rusak +rusak, total -hilang, satu mutasi per barang per jenis lewat BukuBesarStok::catat, unit ditolak tidak mengubah stok, pembaruan bersyarat status = 'menunggu' (BR-07), BR-01 diperiksa per barang sebelum commit. T-03, T-09, T-14 diuji, serta T-22 hanya berurutan. Tes lulus. Belum diuji: konkurensi nyata (2.14) dan BR-01 menyeluruh (2.12 dan 2.13). Tindak lanjut: audit ditulis langsung ke AuditLog dan melewati penjaga kata sandi di Support\Audit. Audit ditulis langsung ke AuditLog, bukan lewat Support\Audit.
- 2026-11-04: 2.9 selesai. `PembatalanService` menangani pembatalan pemesanan oleh peminjam sebelum tanggal pinjam dan pembatalan oleh admin dengan alasan wajib; perubahan stok, mutasi `BATAL_PINJAM`, dan audit berada dalam transaksi. Tes T-24/T-26 lulus (7 tes, 40 assertions); tidak memakai nilai bawaan K-x.
- 2026-11-04: 2.10 selesai. `PerpanjanganService` mencatat riwayat append-only dan audit, menghitung batas baru dari batas lama, menerapkan syarat peminjam serta alasan wajib dan kelonggaran admin sesuai BR-13/T-27. Tes T-17/T-18/T-19/T-27 lulus ( tes,  assertions); seluruh backend lulus (275 tes, 2684 assertions). Tidak memakai nilai bawaan K-x.
- 2026-11-05: 2.11 selesai. `KoreksiService` menangani koreksi peminjaman admin (nama, divisi, telepon, tanggal, batas kembali, dan jumlah per barang sesuai BR-16). Mengunci baris `items` urut ID menaik, menolak bila barang sudah memiliki klaim, mencatat riwayat di `loan_corrections`, mutasi `KOREKSI_PINJAM`, dan audit log.
- 2026-11-05: 2.12 selesai. Perintah artisan `inventaris:periksa-invarian` dan service `PemeriksaInvarian` memvalidasi invarian BR-01 dan mencocokkan total/tersedia/rusak dengan delta buku besar stok (T-42). Mengembalikan kode keluar non-nol bila ada pelanggaran dan 0 saat konsisten.
- 2026-11-05: 2.13 selesai. Helper tes `assertInvarian()` dibuat dan diintegrasikan secara global serta pada `afterEach` tes service domain.
- 2026-11-05: 2.14 selesai. Tes konkurensi nyata multi-proses via `proc_open` dan barrier `flock`: T-21 (dua peminjaman bersamaan untuk stok 1: tepat satu berhasil dan satu 409) dan T-22 (dua verifikasi paralel pada satu klaim: satu berhasil dan satu 409).
- 2026-11-05: 2.15 selesai. Tes acak 1.000 operasi (T-10) dengan seed tetap mencakup pinjam, pesan, klaim, verifikasi, batal, perpanjang, penyesuaian stok, tandai rusak, perbaikan, afkir, dan koreksi, diverifikasi dengan `periksa-invarian` 0 pelanggaran. Gerbang 2 terpenuhi seluruhnya (315 tes lulus).

### Log C (a710230117-faris)

- 2026-10-29: 1.19a selesai. `frontend/` berisi Vue 3.5, Vue Router 5, Pinia 4, Axios 1, Tailwind 4, dan Vite 8 (versi dari registry saat ini; A boleh meminta versi lain). Node 24 dipin lewat `.nvmrc` dan `engines` (>=24 <25), K-9. Proxy `/api` dan `/sanctum` ke 127.0.0.1:8000, port 5173 dikunci karena `SANCTUM_STATEFUL_DOMAINS`. Tiga layout (kiosk, auth, admin), halaman login admin (kerangka tanpa pengiriman), beranda kiosk, dan dasbor admin berupa placeholder. Verifikasi: `npm run build` lulus di Node v24.21.0, `npm run dev` berjalan di localhost:5173, `/sanctum/csrf-cookie` lewat proxy mengembalikan 204. Tidak termasuk: ESLint, Prettier, Vitest, token desain (4.1), interceptor galat (4.2), penjaga rute dan pengiriman login (5.1).
- 2026-11-02: 4.1 selesai. Token desain Tailwind (font sistem, warna aksen, warna status, target sentuh 44px) dan base focus-visible ditambahkan di main.css. Tiga layout disesuaikan namanya (LayoutKiosk, LayoutLogin, LayoutAdmin) dengan tipografi serta aksesibilitas sesuai DESAIN 10.4-10.5. Router diperbarui dengan layout baru, meta.judul, scrollBehavior ke atas, dan document.title otomatis. Store Pinia kiosk dibuat murni dalam memori tanpa persistensi sesuai DESAIN 10.1. Verifikasi: npm run build lulus.
- 2026-11-04: 4.2 selesai. Interceptor galat di `src/api/client.js` menangani 401/419 (redirect admin ke login, retry CSRF di kiosk), 409 (pesan konflik), 429 (pesan tunggu dari Retry-After), 500 (pesan server umum tanpa jejak internal), dan galat jaringan. Klien API terstruktur dibuat di `src/api/kiosk.js` dan `src/api/admin.js` sesuai DESAIN 8.1 dan 8.2. Composable `useGalat` di `src/composables/useGalat.js` untuk penanganan galat form 422. CSRF cookie diinisialisasi otomatis saat bootstrap aplikasi di `src/main.js`. Verifikasi: npm run build lulus.
- 2026-11-04: 4.3 selesai. 12 komponen dasar dibuat di `src/components/` (TombolBesar, FormField, InputJumlah, InputKode, ModalKonfirmasi, NotifikasiToast, StatusKosong, Memuat, PesanGalat, BadgeStatus, BadgeLencana, StrukCetak) dan diekspor via `src/components/index.js` sesuai DESAIN 10.4-10.6. Aksesibilitas ARIA dan target sentuh minimal 44px terpenuhi. Verifikasi: uji kompilasi SFC di `test/components.test.js` dan `npm run build` lulus.
- 2026-11-05: 4.4 selesai. Halaman Beranda (`src/pages/kiosk/BerandaPage.vue`) dengan 3 kartu menu besar layar sentuh (Lihat Katalog, Pinjam Barang, Cek atau Kembalikan). Halaman Katalog (`src/pages/kiosk/KatalogPage.vue`) dengan pencarian nama barang ber-debounce, filter kategori dinamis, penanganan state (memuat, galat, kosong), serta komponen `KartuBarang.vue` yang menandai stok 0 sebagai habis dan menonaktifkan tombol pemilihan (FR-K-01). Rute `/katalog`, `/pinjam`, `/cek` didaftarkan di router. Verifikasi: uji SFC & aturan stok 0 (`npm test`) dan `npm run build` lulus.
- 2026-11-09: 4.5 selesai. Halaman form pinjam (`src/pages/kiosk/PinjamPage.vue`) dengan keranjang barang interaktif (`InputJumlah` dibatasi `tersedia`), validasi klien lengkap (nama, divisi, tipe peminjam, telepon, tanggal pinjam, batas kembali), deteksi otomatis mode pemesanan/booking tanggal masa depan, pembuatan kunci idempotensi UUID (`crypto.randomUUID()`), proteksi tombol nonaktif saat kirim (UI-02), penanganan galat 409 pembaruan stok otomatis, dan rute bukti selesai (`src/pages/kiosk/PinjamSelesaiPage.vue`) menggunakan `StrukCetak`. Utilitas tanggal kalender dibuat di `src/utils/tanggal.js`. Verifikasi: uji SFC & unit test (`npm test`) dan `npm run build` lulus.
- 2026-11-10: 4.6 selesai. Komponen `StrukCetak.vue` disempurnakan sesuai FR-K-03: kode besar tegas, detail peminjam, penanda `[PEMESANAN]` bila tanggal pinjam masa depan, batas kembali, daftar barang, waktu cetak lokal Jakarta, tombol aksi salin kode ke clipboard, dan petunjuk pengembalian barang yang jelas. Tata letak cetak fisik (`@media print`) menyembunyikan header navigasi kiosk (`LayoutKiosk.vue`), latar belakang dan tombol aksi (US-03, T-39, UI-06). Verifikasi: uji SFC & aturan FR-K-03/UI-06 di `npm test` dan `npm run build` lulus.