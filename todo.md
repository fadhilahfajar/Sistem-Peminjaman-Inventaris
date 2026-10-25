# todo.md: Sistem Peminjaman & Inventaris Barang Tim IT

Task per fase untuk agent AI. Aturan kerja ada di `AGENTS.md`. Maksud dan kriteria lulus ada di `docs/PRD.md` (T-xx, K-x). Aturan bisnis, skema, Service, API, UI ada di `docs/DESAIN.md` (BR-xx).

Format: `- [ ] **ID** (P) Judul. Rujukan. Verifikasi.` P = M (Must), S (Should), C (Could), urutan pembangunan, bukan pemangkasan. Kerjakan berurutan. Gerbang di akhir tiap fase harus lolos sebelum fase berikutnya. Keputusan **K-x** belum diputuskan: pakai bawaan di PRD bagian 15 dan catat di Log.

---

## Fase 0: Persiapan (tanpa kode aplikasi)

- [x] **0.1** (M) Buat repo baru dan struktur `backend/`, `frontend/`, `docs/`. Taruh `AGENTS.md`, `todo.md`, `docs/PRD.md`, `docs/DESAIN.md`. `.gitignore` memuat `.env`, `vendor`, `node_modules`, `*.sql`. Commit awal.
- [x] **0.2** (M) Verifikasi lingkungan: PHP 8.4, Composer, Node + npm, MySQL ≥ 8.0.16 (`SELECT VERSION();`). Putuskan K-3 (satu cara menjalankan). Verifikasi: semua perintah versi berjalan.
- [x] **0.3** (M) Setel kata sandi `root` MySQL. Buat database `inventaris_it_dev` dan `inventaris_it_test`. Buat akun `app_inventaris`, `migrator`, `backup` sesuai DESAIN 11.2 (versi dev). Verifikasi: login tiap akun, `app_inventaris` tidak bisa `DROP`.
- [ ] **0.4** (M) Isi `my.ini` sesuai DESAIN 11.3 (`bind-address`, `utf8mb4`, zona waktu `+00:00`). Verifikasi `@@sql_mode`, `@@character_set_server`, `@@time_zone`.
- [ ] **0.5** (M) Uji `AGENTS.md` terbaca: minta Antigravity dan agent VS Code meringkas "aturan wajib" dari berkas itu. Bila Antigravity tidak memuatnya, buat `.agents/rules/proyek.md` yang merujuk `AGENTS.md`. Bila agent VS Code tidak memuatnya, periksa pengaturan `chat.useAgentsMdFile`.
- [ ] **0.6** (M) Dari pemilik proyek: daftar awal divisi dan kategori (K-5), pilihan K-1 dan K-8. Catat di Log.

**Gerbang 0:** lingkungan siap, kedua agent membaca `AGENTS.md`, K-1/K-3/K-5/K-8 tercatat.

## Fase 1: Fondasi backend

- [ ] **1.1** (M) Buat proyek Laravel 13 di `backend/` tanpa starter kit. Verifikasi perintah instalasi ke dokumentasi resmi. Hapus `jobs` dan `password_reset_tokens` dari migration bawaan.
- [ ] **1.2** (M) `.env.example` dan konfigurasi sesuai DESAIN 11.3 dan 6: `APP_TIMEZONE=UTC`, `APP_LOCALE=id`, koneksi DB `timezone +00:00`, sesi `database`, cookie `SameSite=strict`. Verifikasi: `php artisan about`.
- [ ] **1.3** (M) Pasang Sanctum mode SPA (`statefulApi()`), CORS dengan kredensial, `SANCTUM_STATEFUL_DOMAINS`. Verifikasi di dokumentasi Laravel 13.
- [ ] **1.4** (M) Perintah `inventaris:periksa-lingkungan` (versi MySQL, `sql_mode`, charset). Rujukan FR-S-03. Verifikasi: **T-36**.
- [ ] **1.5** (M) Migration `users`, `divisions`, `categories`, `settings` beserta CHECK. DESAIN 5.
- [ ] **1.6** (M) Migration `items`: `nama_aktif` (generated, unique), CHECK stok, `kategori_id` FK.
- [ ] **1.7** (M) Migration `loans` dan `loan_items`.
- [ ] **1.8** (M) Migration `return_claims` dan `return_claim_lines` (CHECK empat hasil, BR-05).
- [ ] **1.9** (M) Migration `loan_extensions`, `loan_corrections`, `stock_movements` (enum 11 jenis), `audit_logs`.
- [ ] **1.10** (M) Tabel bawaan `sessions`, `cache`, `cache_locks`. Jalankan `migrate:fresh` di `_dev` dan `_test`. Verifikasi: tes memasukkan baris yang melanggar tiap CHECK dan UNIQUE penting, semuanya ditolak database.
- [ ] **1.11** (M) Model Eloquent dan relasi untuk 13 tabel. `$timestamps = false`, cast tanggal `date:Y-m-d`, `$fillable` eksplisit.
- [ ] **1.12** (M) `Support\Tanggal` (hari ini Asia/Jakarta, selisih hari) dan `Support\KodeAcak` (alfabet 31 karakter, `PJM-XXXXXX`, `KMB-XXXXXX`). Verifikasi: tes unit termasuk batas tengah malam Jakarta, tanpa karakter I, L, O, 0, 1.
- [ ] **1.13** (M) `Setting` (model atau service) dengan pembacaan terpusat, cast angka, dan validasi rentang di satu tempat. Seeder delapan kunci.
- [ ] **1.14** (M) Seeder divisi dan kategori dari 0.6 plus "Tanpa Kategori". Tanpa data bisnis palsu di produksi (factory hanya untuk dev dan tes).
- [ ] **1.15** (M) Perintah `BuatAdmin` (meminta password interaktif) dan `ResetPasswordAdmin` (mengakhiri semua sesi). Verifikasi: **T-29**.
- [ ] **1.16** (M) Login, logout, `me` di `Auth\LoginController`. Rate limit login 5 per 15 menit per IP dan email. Middleware `AkunAktif`. `login_terakhir` dan audit login. Verifikasi: **T-01**, bagian dari **T-20**.
- [ ] **1.17** (M) Middleware `SesiIdle` memakai `settings.sesi_idle_menit` (K-7). Verifikasi: sesi idle berakhir di server (bagian dari **T-20**).
- [ ] **1.18** (M) Ganti password sendiri. Penangan galat JSON seragam `{kode, pesan, errors?}` untuk 401, 403, 404, 409, 419, 422, 429, 500 tanpa jejak internal.
- [ ] **1.19** (M) **Walking skeleton:** scaffold `frontend/` (Vue, Vite, Router, Pinia, Axios, Tailwind, proxy `/api` dan `/sanctum`) dengan satu halaman login admin dan satu halaman kiosk yang memanggil `GET /api/kiosk/config` dan satu POST kiosk sementara. Buktikan: cookie sesi Sanctum bekerja, CSRF bekerja untuk POST kiosk (DESAIN 6), tidak ada CORS error. Hapus POST sementara setelah terbukti.
- [ ] **1.20** (M) Tes fase 1 dan `./vendor/bin/pint`.

**Gerbang 1:** `migrate:fresh --seed` berjalan di `_dev`, login admin berfungsi dari frontend, T-01, T-29, T-36 lulus, CHECK terbukti menolak pelanggaran.

## Fase 2: Inti stok dan transaksi

Tahap paling berisiko. Jangan terburu-buru, jangan melewati tes.

- [ ] **2.1** (M) `StatusPeminjaman`: O dan P per `loan_item`, enam status, empat lencana, hari terlambat. DESAIN 4, BR-04, BR-10. Verifikasi: tes unit tabel kasus termasuk unit ditolak kembali menjadi sisa. **T-04**.
- [ ] **2.2** (M) `BukuBesarStok` (jalur tunggal tulis `stock_movements`) dan helper audit. Verifikasi: tes bahwa tiap perubahan stok memunculkan tepat satu baris.
- [ ] **2.3** (M) `StokService`: tambah dan kurangi stok. BR-15. Verifikasi bagian **T-42**.
- [ ] **2.4** (M) `StokService`: tandai rusak, perbaikan, afkir. BR-09, BR-34. Verifikasi: **T-15**, **T-33**.
- [ ] **2.5** (M) `PeminjamanService::buat` untuk peminjaman hari ini: idempotensi, validasi BR-22 dan BR-23, pembaruan bersyarat, kode unik, mutasi `PINJAM`. Verifikasi: **T-02**, **T-11**, **T-32**, **T-34**.
- [ ] **2.6** (S) `PeminjamanService::buat` untuk pemesanan (tanggal masa depan, stok ditahan). BR-23, D-10. Verifikasi: **T-23**.
- [ ] **2.7** (M) `KlaimService::buat`. BR-03, BR-04, BR-10. Verifikasi: **T-07**, **T-08**, **T-12**, **T-25**.
- [ ] **2.8** (M) `VerifikasiService::proses`. BR-05 sampai BR-08, cek BR-01 sebelum commit. Verifikasi: **T-03**, **T-09**, **T-14**.
- [ ] **2.9** (S) `PembatalanService` (peminjam dan admin). BR-24, BR-25. Verifikasi: **T-24**, **T-26**.
- [ ] **2.10** (S) `PerpanjanganService` (peminjam dan admin). BR-11 sampai BR-13, BR-26. Verifikasi: **T-17**, **T-18**, **T-19**, **T-27**.
- [ ] **2.11** (S) `KoreksiService`. BR-16. Verifikasi: tes koreksi jumlah, penolakan bila sudah ada klaim, riwayat tersimpan.
- [ ] **2.12** (M) Perintah `inventaris:periksa-invarian`: hitung ulang BR-01 dan cocokkan dengan jumlah `delta_*`. Exit code non-nol bila ada pelanggaran. Verifikasi: **T-42**.
- [ ] **2.13** (M) Helper tes `assertInvarian()` dipanggil di akhir setiap tes Service.
- [ ] **2.14** (M) Tes konkurensi **nyata** dengan dua proses atau dua koneksi terpisah (satu proses PHP tidak membuktikan `lockForUpdate`). **T-21**, **T-22**.
- [ ] **2.15** (M) Tes acak 1.000 operasi dengan seed tetap supaya dapat diulang, lalu `periksa-invarian`. **T-10**.

**Gerbang 2:** T-02, T-03, T-04, T-07 sampai T-12, T-14, T-15, T-21, T-22, T-34, T-42 lulus. `periksa-invarian` melaporkan 0 pelanggaran setelah T-10.

## Fase 3: API

- [ ] **3.1** (M) Konvensi: struktur route, Form Request per aksi, Resource per entitas, kunci idempotensi di request.
- [ ] **3.2** (M) Kiosk baca: `config`, `items`, `categories`, `divisions`. Hanya nama dan `tersedia` di katalog. Rate limit.
- [ ] **3.3** (M) Kiosk: `POST loans` (peminjaman dan pemesanan). Verifikasi: **T-02**, **T-11**, **T-23**.
- [ ] **3.4** (M) Kiosk: `POST loans/lookup` dengan pembatas laju (K-6). Telepon tidak pernah di respons. Verifikasi: **T-06**.
- [ ] **3.5** (M) Kiosk: klaim, perpanjangan, pembatalan. Verifikasi: **T-07**, **T-17**, **T-24**, **T-25**.
- [ ] **3.6** (M) Admin: items CRUD (soft-delete, BR-14, BR-33), stock-adjustments, mark-damaged, repairs, repair-complete, write-off. **T-05**, **T-15**, **T-33**.
- [ ] **3.7** (M) Admin: categories dan divisions (nonaktifkan, BR-33). **T-28**, **T-30**.
- [ ] **3.8** (M) Admin: loans (filter lengkap, pagination), detail dan lini masa, koreksi, extensions, cancel. **T-13**, **T-19**, **T-26**.
- [ ] **3.9** (M) Admin: return-claims index, show, verify. **T-03**, **T-14**, **T-22**.
- [ ] **3.10** (M) Admin: summary, settings (validasi rentang), audit-logs, users.
- [ ] **3.11** (M) Catat bentuk JSON sebenarnya tiap endpoint di `docs/DESAIN.md` bagian 8.
- [ ] **3.12** (M) Tes Feature tiap grup endpoint: sukses, 422, 401, 409, 429 yang relevan. **T-31**, **T-35**.

**Gerbang 3:** semua endpoint di DESAIN 8 ada dan bertes. Semua rute `/api/admin/*` menolak 401 tanpa sesi (T-01).

## Fase 4: Frontend dan kiosk

- [ ] **4.1** (M) Rapikan scaffold dari 1.19: router, store Pinia (tanpa persist untuk kode), tiga layout, token desain Tailwind (DESAIN 10.5), font sistem.
- [ ] **4.2** (M) Klien API per endpoint, interceptor galat (401/419 ke login, 409 muat ulang, 429 pesan tunggu), CSRF otomatis.
- [ ] **4.3** (M) Komponen dasar: `TombolBesar`, `FormField`, `InputJumlah`, `InputKode`, `ModalKonfirmasi`, `NotifikasiToast`, `StatusKosong`, `Memuat`, `PesanGalat`, `BadgeStatus`, `BadgeLencana`, `StrukCetak`.
- [ ] **4.4** (M) Kiosk: Beranda dan Katalog (pencarian, filter kategori, stok 0 tidak bisa dipilih). FR-K-01.
- [ ] **4.5** (M) Kiosk: Form pinjam (keranjang, validasi klien, kunci idempotensi UUID, tombol nonaktif saat kirim, pemesanan). FR-K-02.
- [ ] **4.6** (M) Kiosk: Struk dan kode, tata letak cetak. FR-K-03.
- [ ] **4.7** (M) Kiosk: Cek peminjaman dan Kartu (batas kembali, sisa hari, riwayat klaim dengan hasil dan catatan admin). FR-K-04, FR-K-09.
- [ ] **4.8** (M) Kiosk: Klaim dan bukti klaim. FR-K-05, FR-K-06.
- [ ] **4.9** (S) Kiosk: Perpanjangan (dengan bukti) dan Batalkan pemesanan. FR-K-07, FR-K-08, FR-K-12.
- [ ] **4.10** (M) Idle reset memakai `kiosk_idle_detik` dari config, hapus semua isian dan kode. FR-K-10.
- [ ] **4.11** (M) Uji manual: UI-01, UI-02, UI-03, UI-05, UI-06, **T-35**, **T-39**, **T-40**. Ukur waktu form untuk G4.

**Gerbang 4:** alur kiosk lengkap berjalan di tablet, kode tidak ada di URL atau riwayat.

## Fase 5: Panel admin

- [ ] **5.1** (M) Login admin, router guard dari `/api/auth/me`, pesan sesi berakhir. UI-08.
- [ ] **5.2** (M) Layout admin: sidebar (menu geser di layar kecil), navbar, lencana antrean dan stok rusak.
- [ ] **5.3** (M) `TabelData` (urutan, pagination, mode kartu di layar sempit) dan `FilterBar`.
- [ ] **5.4** (M) Dasbor dengan polling 30 detik. FR-A-15, FR-A-19.
- [ ] **5.5** (M) Barang: daftar, form tambah dan ubah, hapus dengan konfirmasi, penyesuaian stok.
- [ ] **5.6** (M) Daftar peminjaman dengan semua filter, detail dan lini masa, aksi koreksi, perpanjang, batalkan.
- [ ] **5.7** (M) Antrean verifikasi dan layar verifikasi: empat kolom hasil, selisih, Simpan aktif hanya bila nol, aksi cepat "Setujui sesuai laporan", catatan wajib. FR-A-04.
- [ ] **5.8** (M) Stok rusak: tandai rusak, selesai diperbaiki, afkir.
- [ ] **5.9** (M) Kategori dan Divisi.
- [ ] **5.10** (S) Pengaturan, Akun admin, Jejak audit.
- [ ] **5.11** (M) Halaman galat 403, 404, 419.
- [ ] **5.12** (M) Uji manual: UI-04, UI-05, UI-07, **T-13**, **T-16**, **T-40**. Badge status identik di kiosk dan admin.

**Gerbang 5:** seluruh alur admin berjalan. T-13, T-16 lulus.

## Fase 6: Laporan, ekspor, audit

- [ ] **6.1** (M) Pilih pustaka PDF (K-2). Verifikasi kompatibilitas dengan Laravel 13 dan PHP 8.4 sebelum memasang.
- [ ] **6.2** (M) `LaporanService` dan `LaporanController`: rekap (filter kategori dan tipe), belum dikembalikan plus pemesanan belum mulai, stok per kategori, riwayat pengembalian dengan catatan. Judul, periode, waktu cetak Asia/Jakarta. **T-41**.
- [ ] **6.3** (M) Halaman Laporan: pilih jenis dan filter, unduh.
- [ ] **6.4** (C) Ekspor CSV/Excel untuk daftar peminjaman dan stok. FR-A-14.
- [ ] **6.5** (S) Tinjau cakupan audit: semua aksi penting menulis `audit_logs` tanpa password atau token.

**Gerbang 6:** T-41 lulus, isi PDF cocok dengan data.

## Fase 7: Pengujian penuh, keamanan, rilis

- [ ] **7.1** (M) Jalankan seluruh T-01..T-42 dan UI-01..UI-08. Catat hasil di `docs/HASIL-UJI.md`.
- [ ] **7.2** (M) Uji kinerja: seed 5.000 transaksi, daftar < 2 detik, peminjaman < 1 detik.
- [ ] **7.3** (M) Tinjau keamanan: mass assignment, escaping, cookie sesi, `APP_DEBUG=false`, `git grep` kredensial, akun DB berhak minimal. **T-20**, **T-37**.
- [ ] **7.4** (M) Build produksi dan penyajian satu port, layanan menyala otomatis (K-4). FR-S-05. Uji restart PC sungguhan.
- [ ] **7.5** (M) Akun DB produksi dan hak (DESAIN 11.2), `.env` produksi, port 3306 tertutup di firewall. **T-37**.
- [ ] **7.6** (M) Backup terjadwal (14 salinan, satu di perangkat lain) dan uji pulih. FR-S-04, **T-38**.
- [ ] **7.7** (M) README (cara pasang dari repo bersih: `composer install`, `.env`, `migrate --seed`, `BuatAdmin`, frontend) dan SOP singkat. G6.
- [ ] **7.8** (M) Buat akun admin cadangan. Tetapkan penanggung jawab utama dan pengganti server.
- [ ] **7.9** (M) UAT dengan pengguna nyata dan sign-off.

**Gerbang 7:** semua T dan UI lulus, UAT disetujui, backup teruji.

---

## Log pekerjaan

(Agent mengisi: tanggal, ID task, hasil satu-dua kalimat, dan nilai bawaan K-x yang dipakai.)

- 2026-10-22: 0.1 selesai. Struktur folder awal backend/, frontend/, docs/ beserta .gitignore dan dokumen acuan telah disiapkan di repo Git.
- 2026-10-23: 0.2 selesai. Verifikasi lingkungan dan keputusan K-3:
  - Keputusan K-3: opsi A (PHP dari Laravel Herd menjadi PHP default, folder php84 di PATH sistem, di atas XAMPP).
  - Hasil pemeriksaan:
    - PHP 8.4.16 (NTS), Herd, C:\Users\ASUS\.config\herd\bin\php84\php.exe
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
- Task 0.4: time_zone MySQL masih SYSTEM, ubah ke +00:00. Berkas konfigurasi MySQL milik phpBro, bukan my.ini Laragon, jadi lokasinya harus dicari dulu (datadir dan defaults-file dari proses mysqld).
- Task 1.10: setelah migration, jalankan BAGIAN B scripts/db/setup-dev.sql.example (GRANT DELETE pada sessions, cache, cache_locks) sebagai root.
- Task 1.2 dan 1.20: tes perlu hak DDL pada inventaris_it_test. Saat ini hanya akun migrator yang punya, app_inventaris hanya DML. Putuskan saat 1.2 apakah tes memakai migrator lewat .env.testing atau phpunit.xml.
