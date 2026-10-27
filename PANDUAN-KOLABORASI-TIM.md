# Panduan Kolaborasi dan Pembagian Jobdesk

**Proyek:** Sistem Peminjaman & Inventaris Barang Tim IT  
**Status dokumen:** Berlaku. Selaras dengan `AGENTS.md` dan `todo.md` sejak PR sinkronisasi 23 Oktober 2026.  
**Dokumen acuan utama:** `docs/PRD.md`, `docs/DESAIN.md`, `AGENTS.md`, dan `todo.md`.

## 1. Tujuan dan prinsip kerja

Dokumen ini mengatur pembagian tanggung jawab tiga anggota agar pekerjaan dapat berlangsung paralel tanpa melewati ketergantungan teknis, gerbang pengujian, atau aturan bisnis proyek.

Prinsip yang tidak boleh dilanggar:

1. `PRD.md` menjadi acuan kebutuhan produk dan kriteria penerimaan.
2. `DESAIN.md` menjadi acuan aturan bisnis `BR-xx`, skema database, Service, API, dan UI.
3. `AGENTS.md` memuat aturan kerja wajib agent AI.
4. `todo.md` mengatur task, urutan, pemilik, verifikasi, dan gerbang tiap fase.
5. Pekerjaan paralel hanya boleh dilakukan jika dependensinya sudah tersedia. Paralelisme tidak menghapus kewajiban lulus gerbang fase.
6. Aturan bisnis hanya berada di backend Service. Frontend hanya menampilkan data dan mengirim tindakan pengguna.
7. Perubahan stok hanya melalui Service dan `BukuBesarStok`, dalam transaksi database yang sama dengan catatan `stock_movements`.
8. Tidak boleh mengarang keputusan bisnis atau nilai bawaan `K-x`. Gunakan nilai bawaan sementara dalam PRD dan catat penggunaannya di Log.
9. Jangan push atau merge ke `main` tanpa mengikuti proses Pull Request (PR).
10. Jangan pernah memasukkan `.env`, kata sandi, token, dump database, atau berkas SQL lokal yang berisi kredensial ke Git.

> **Prioritas:** bila berkas ini dan `AGENTS.md` berbeda soal pembagian task dan alur Git, berkas ini yang berlaku. Soal aturan kode, stok, dan database, `AGENTS.md` yang berlaku.

## 2. Pembagian tim

Nama anggota B dan C diisi setelah tim menyepakati penempatan berdasarkan kemampuan dan ketersediaan waktu.

| Peran | Pemilik | Fase/task utama | Perkiraan jumlah task | Karakter tanggung jawab |
|---|---|---|---:|---|
| **A — PM, DevOps, API, integrasi** | Pemilik repository | Fase 0; 1.1–1.4; 1.12; Fase 3; Fase 6 kecuali 6.3; Fase 7 kecuali 7.7 dan 7.9 | ±33 | Menjaga keputusan proyek, lingkungan, kontrak API, integrasi, review, CI, dan rilis |
| **B — Backend inti** | idrsabdlh-coder | 1.5–1.11; 1.13–1.18; 1.20; Fase 2 | ±30 | Menangani skema, autentikasi dan Service inti; paling berisiko pada stok, transaksi, dan konkurensi |
| **C — Frontend** | a710230117-faris | 1.19a; Fase 4; Fase 5; 6.3; 7.7; 7.9 | ±27 | Membangun kiosk dan panel admin berdasarkan kontrak API yang telah tersedia |

Perkiraan jumlah task hanyalah estimasi berdasarkan pembagian saat ini, bukan ukuran beban kerja yang pasti. Penempatan akhir B dan C harus mempertimbangkan pengalaman Laravel, transaksi database, testing, Vue, dan ketersediaan waktu. Fase 2 dimiliki B dan wajib ditinjau A sebelum merge karena risiko integritas data dan konkurensinya paling tinggi.

## 3. Gelombang kerja dan ketergantungan

### Gelombang 0 — Persiapan

**Mulai:** sekarang.

- **A:** menyelesaikan keputusan/persiapan Fase 0 (termasuk task 0.2–0.6 sesuai status aktual), menyiapkan repository GitHub, aturan `main`, serta membagikan dokumen keputusan kepada tim.
- **B dan C:** menyiapkan lingkungan lokal masing-masing dan membaca `AGENTS.md`, `todo.md`, `docs/PRD.md`, dan `docs/DESAIN.md`.
- Task lingkungan 0.2–0.5 bersifat **per mesin**. B dan C memverifikasi lingkungan sendiri lalu melaporkan hasil kepada A; mereka tidak mencentang task A di `todo.md` seolah-olah pemeriksaan dilakukan pada mesin yang sama.
- Task 0.6, yang mencakup pilihan K-1, K-8, serta divisi/kategori awal K-5, diputuskan oleh A setelah berdiskusi dengan tim. K-3 dicatat sesuai hasil task 0.2.
- Jangan membagikan kata sandi MySQL melalui chat, issue, PR, atau dokumen yang dikomit. Pengisian kata sandi dan eksekusi skrip lokal dilakukan pemilik mesin.

### Gelombang 1 — Fondasi paralel

**Mulai bila:** K-1 diputuskan dan task 1.1 selesai.

- **A:** task 1.1–1.4, kemudian 1.12.
- **B:** task 1.5–1.11, lalu 1.13–1.18 dan 1.20, dengan tetap mengikuti dependensi migration/model dan hasil review yang diperlukan.
- **C:** membuat scaffold frontend dan komponen dasar pada task 1.19a agar pekerjaan UI dasar dapat dimulai tanpa menunggu seluruh integrasi autentikasi selesai.

Task 1.19 diusulkan dibagi menjadi:

- **1.19a — Scaffold frontend (C):** Vue, Vite, Router, Pinia, Axios, Tailwind, proxy `/api` dan `/sanctum`, struktur layout, serta halaman placeholder yang dapat dijalankan secara lokal.
- **1.19b — Bukti integrasi (A + B + C):** membuktikan cookie sesi Sanctum, CSRF, POST kiosk, dan tidak ada kesalahan CORS setelah bagian backend yang diperlukan tersedia. Hapus endpoint POST sementara setelah pembuktian.

Pembagian tersebut harus direfleksikan di `todo.md` sebelum nomor task baru digunakan secara resmi.

### Gerbang 1 — Fondasi terintegrasi

Gerbang ini baru lulus setelah bagian backend dan frontend yang diperlukan terpasang serta bukti autentikasi/cookie/CSRF berhasil. Selesainya scaffold frontend saja tidak berarti Gerbang 1 lulus.

### Gelombang 2 — Awal API dan UI kiosk

**Mulai bila:** Gerbang 1 lulus.

- **B:** mengerjakan Fase 2 secara berurutan, termasuk pengujian Service, invariant stok, konkurensi, dan idempotensi.
- **A:** mengerjakan bagian Fase 3 yang tidak memerlukan Service yang belum selesai, terutama 3.1, 3.2, 3.7, dan bagian awal 3.10.
- **C:** mengerjakan 4.1–4.4 serta kerangka UI yang dapat dikembangkan berdasarkan endpoint yang sudah tersedia.

Tidak boleh menganggap endpoint placeholder sebagai kontrak final. Endpoint dan UI yang membutuhkan Service tertentu menunggu serah-terima Service terkait.

### Gelombang 3 — Pipeline serah-terima per Service

Pekerjaan API dan UI berikutnya dapat dimulai per Service yang telah diuji, direview, dan di-merge. Rincian serah-terima ada di bagian 4.

### Gelombang 4 — Laporan dan penyempurnaan

**Mulai bila:** Gerbang 2 dan pekerjaan API yang dibutuhkan telah lulus.

- **A:** Fase 6, termasuk `LaporanService`, controller, integrasi endpoint laporan, dan pengujian isi PDF terhadap data.
- **C:** task 6.3 serta penyempurnaan halaman laporan dan pengalaman pengguna.
- **B:** membantu perbaikan bug domain dan integritas data berdasarkan temuan integrasi.

### Gelombang 5 — Pengujian akhir dan rilis

**Mulai bila:** Gerbang 5 dan Gerbang 6 lulus.

Semua anggota ikut serta dalam Fase 7, termasuk tes otomatis, tes UI, pemeriksaan keamanan, uji backup-pulih, uji restart mesin, UAT, dan dokumentasi operasional. Kepemilikan task tetap mengikuti daftar yang disepakati, tetapi pengujian penerimaan bukan tanggung jawab satu orang saja.

## 4. Pipeline serah-terima antaranggota

| Hasil B yang telah diuji, direview, dan di-merge | Bagian API yang dapat disambungkan A | Bagian UI yang dapat dibangun C |
|---|---|---|
| **1.16 — Login** | 3.10 (users, settings) | 1.19b dan 5.1 |
| **2.5 — Peminjaman** | 3.3 | 4.5 dan 4.6 |
| **2.1 + 2.7 — Status dan klaim** | 3.4 dan bagian klaim dari 3.5 | 4.7 dan 4.8 |
| **2.8 — Verifikasi** | 3.9 | 5.7 |
| **2.4 — Penanganan stok rusak** | 3.6 | 5.5 dan 5.8 |
| **2.9, 2.10, 2.11 — Pembatalan, perpanjangan, koreksi** | Bagian terkait 3.5 dan 3.8 | 4.9 dan 5.6 |

Ketentuan serah-terima:

1. Serah-terima baru berlaku setelah tes terkait lulus, review dilakukan, dan PR di-merge.
2. A mencatat struktur dan contoh JSON aktual pada `docs/DESAIN.md` bagian 8 **di PR yang memperkenalkan endpoint tersebut**, bukan menunggu akhir Fase 3.
3. C membangun komponen berdasarkan kontrak JSON yang telah disepakati. Perubahan kontrak harus disampaikan melalui PR dan dikoordinasikan dengan C.
4. Bila implementasi Service mengungkap konflik atau kekosongan spesifikasi, hentikan bagian yang terdampak dan rujuk ke dokumen acuan. Jangan diam-diam mengubah aturan bisnis.

## 5. Aturan Git dan GitHub

### 5.1 Repository dan `main`

- Repository GitHub akan bersifat **public**. Semua orang dapat membaca dan menyalin kode; akses collaborator terutama diperlukan untuk kontribusi langsung dan pengelolaan PR.
- `main` adalah cabang stabil dan tidak digunakan untuk pekerjaan harian.
- Semua perubahan ke `main` masuk melalui Pull Request.
- Wajib ada minimal **satu approval dari anggota lain** sebelum merge. Penulis PR tidak boleh menjadi satu-satunya reviewer untuk perubahannya sendiri.
- Aktifkan perlindungan agar `main` tidak bisa dihapus atau di-force-push. Bila pengaturan tersedia, jangan izinkan administrator melewati persyaratan perlindungan.
- Wajib selesaikan percakapan review sebelum merge. Setelah GitHub Actions siap, tambahkan status check CI sebagai syarat merge.
- Undangan collaborator dikirim oleh pemilik repository setelah repository dan kebijakan kerja siap. Masing-masing anggota menerima undangan menggunakan akun GitHub sendiri.

### 5.2 Branch dan PR

- Nama branch: `f<fase>/<id-task>-<slug>`.
- Contoh: `f2/2.5-peminjaman-service`.
- Gunakan satu PR per task atau gabungan task yang kecil dan saling terkait.
- Gunakan **Squash and merge** agar `main` memiliki riwayat yang ringkas.
- Agent AI yang digunakan anggota hanya bekerja pada branch task yang menjadi tanggung jawab anggota tersebut. Jangan menjalankan dua agent pada branch yang sama secara bersamaan tanpa koordinasi.
- Agent tidak push. Anggota memeriksa hasil (`git diff`, `git status`, tes), lalu push dan membuka PR. Tidak ada push atau merge langsung ke `main`.
- Contoh alur umum:

```powershell
# Mulai dari main yang sudah diperbarui
git switch main
git pull --ff-only origin main

# Buat branch khusus task
git switch -c f2/2.5-peminjaman-service

# Setelah perubahan dan tes siap
git status --short
git add <berkas-yang-terkait>
git commit -m "feat: implementasi service peminjaman"
git push -u origin f2/2.5-peminjaman-service
```

Buat PR melalui GitHub, isi ringkasan perubahan, task terkait, bukti tes, dan risiko/perubahan kontrak. Jangan memakai `git add .` tanpa memeriksa status terlebih dahulu.

### 5.3 Kepemilikan berkas dan dependency

- `composer.json` dan `composer.lock` hanya diubah oleh **A atau B**.
- `package.json` dan `package-lock.json` hanya diubah oleh **C**.
- Penambahan atau perubahan paket harus diajukan sebagai PR kecil terpisah dan disertai alasan serta verifikasi kompatibilitas versi stack.
- Migration hanya dibuat oleh **B** selama Fase 1, setelah mengacu pada DESAIN.
- Karena belum ada data produksi, apabila migration yang sudah di-merge perlu diubah, umumkan kepada tim terlebih dahulu dan pastikan seluruh anggota menjalankan `migrate:fresh` **hanya pada database dev milik masing-masing**. Perintah destruktif dilarang pada database lain.
- Pisahkan route berdasarkan kelompok berkas/fitur (auth, kiosk, admin) supaya A, B, dan C tidak mengedit file route yang sama tanpa koordinasi.

### 5.4 Pengelolaan `todo.md` dan Log

- Tambahkan kolom **Pemilik** pada daftar task.
- Pecah bagian Log menjadi tiga subbagian: **A**, **B**, dan **C**. Setiap anggota hanya mencentang task yang menjadi tanggung jawabnya dan mencatat pekerjaan pada bagian Log miliknya.
- Catatan Log memuat tanggal, ID task, hasil satu-dua kalimat, hasil verifikasi, serta nilai bawaan K-x yang digunakan jika ada.
- Hindari mengedit baris task atau Log milik anggota lain tanpa koordinasi. Konflik merge tetap harus diselesaikan dengan mempertahankan hasil semua pihak.
- Perubahan kepemilikan task atau ruang lingkup harus disetujui A dan anggota yang terdampak, kemudian dicatat dalam PR.

## 6. Review silang

| Area | Reviewer utama | Fokus review |
|---|---|---|
| Fase 2 — Service inti | A | Invariant stok, transaksi atomik, deadlock, urutan lock, idempotensi, rollback, audit, dan tes konkurensi nyata |
| Fase 3 — API | B | Validasi server, pemanggilan Service, kode respons, bentuk JSON, otorisasi, dan tidak ada aturan bisnis di controller |
| Fase 4 dan 5 — Frontend | A atau B | Tidak ada perhitungan stok/status atau keputusan bisnis di Vue; penanganan 401/419/409/422/429; responsivitas dan aksesibilitas |
| Fase 6 — Laporan | A dan B | Akurasi data, filter, tanggal/waktu cetak, dan konsistensi dengan data transaksi |
| Fase 7 — Rilis | Semua anggota | Uji penerimaan, keamanan, backup-pulih, restart, instalasi bersih, dan UAT |

Reviewer harus memberikan komentar spesifik dan dapat ditindaklanjuti, bukan hanya menyetujui karena kode berhasil dikompilasi.

## 7. GitHub Actions / CI

Usulkan **task 0.7 (Should)** untuk menambahkan GitHub Actions yang berjalan pada setiap PR ke `main` dan saat perubahan masuk ke `main`.

Minimal pipeline:

1. Backend memakai PHP 8.4 dan MySQL 8.4.
2. Menyiapkan database pengujian dengan nama berakhiran `_test`.
3. Menjalankan migration/seed yang diperlukan oleh tes dan `php artisan test`.
4. Menjalankan `./vendor/bin/pint --test`.
5. Frontend memakai versi Node LTS yang dipilih tim, menjalankan `npm ci` dan `npm run build`.
6. PR gagal bila salah satu langkah wajib gagal.

Pastikan kredensial CI berasal dari konfigurasi aman GitHub Actions atau service database sementara, bukan dari `.env` atau berkas kredensial yang dikomit. Jangan menjadikan status check wajib sebelum workflow tersedia, namanya stabil, dan sudah pernah berjalan sukses.

Workflow dibuat setelah backend (Task 1.1) dan frontend (Task 1.19a) ada, sehingga Task 0.7 bukan syarat Gerbang 0. Versi Node di CI dan di mesin semua anggota harus sama: gunakan Node LTS yang dipin lewat `.nvmrc` dan `engines` di `package.json` (keputusan K-9).

## 8. Fase 0 dan konfigurasi lokal tiap anggota

- Task lingkungan, akun MySQL, `my.ini`, dan verifikasi runtime adalah pekerjaan **per mesin**. Satu anggota tidak dapat menuntaskan verifikasi lingkungan anggota lainnya.
- B dan C melapor kepada A setelah setup lokal dan pembacaan dokumen selesai. Laporkan versi perangkat lunak dan hasil verifikasi, tetapi **jangan** mengirim kata sandi atau token.
- Task 0.6 (K-1, K-5, K-8) diputuskan A setelah diskusi tim. Semua nilai keputusan dicatat di tempat yang ditentukan oleh PRD/TODO.
- Untuk database lokal, akun aplikasi dan akun migrator tetap dipisahkan sesuai DESAIN. Tes yang memerlukan DDL hanya boleh memakai database `_test` dan akun yang berhak menjalankan migration; keputusan konfigurasi tes harus dicatat saat task terkait dikerjakan.
- `scripts/db/setup-dev.local.sql`, `.env`, dan berkas lokal berisi kredensial tidak boleh dikomit. Berkas template tanpa kredensial boleh dikomit jika tidak memuat nilai rahasia.

## 9. Perubahan pada dokumen acuan

Perubahan berikut diterapkan lewat PR sinkronisasi pada 23 Oktober 2026:

1. `todo.md`: tambahkan kolom pemilik pada task.
2. `todo.md`: pisahkan Log menjadi A, B, dan C.
3. `todo.md`: alihkan task 1.1–1.4 dan 1.12 ke A; tetapkan task 1.5–1.11, 1.13–1.18, 1.20 dan Fase 2 ke B; tetapkan task frontend ke C sesuai tabel peran.
4. `todo.md`: pecah task 1.19 menjadi 1.19a untuk scaffold frontend dan 1.19b untuk pembuktian integrasi cookie/CSRF.
5. `todo.md`: tambahkan task 0.7 (Should) untuk GitHub Actions.
6. `todo.md`: ubah task 3.11 agar bentuk JSON aktual dicatat per endpoint di PR endpoint tersebut.
7. `AGENTS.md`: ubah aturan urutan task menjadi urutan dalam daftar task milik anggota, tanpa menghapus ketentuan untuk membaca PRD/DESAIN dan tidak melewati gerbang.
8. `AGENTS.md`: tambahkan ketentuan bahwa anggota/agent hanya mengerjakan task yang menjadi tanggung jawabnya, kecuali ada pengalihan tugas yang disetujui.
9. `AGENTS.md`: perbarui bagian Git agar mewajibkan branch task, PR, review, dan tidak merge/push langsung ke `main`.
10. Audit semua perubahan terhadap PRD dan DESAIN agar pembagian kerja tidak mengubah kebutuhan atau aturan bisnis secara diam-diam.

## 10. Definisi selesai untuk setiap task

Sebuah task dianggap selesai hanya bila:

- Implementasi sesuai PRD dan DESAIN yang dirujuk.
- Tes otomatis atau manual yang relevan dijalankan dan hasilnya dicatat.
- Tidak ada logika bisnis di controller atau Vue.
- Tidak ada `dd()`, `console.log`, kode uji yang tertinggal pada bundle produksi, kredensial, atau berkas lokal rahasia.
- Format kode diperiksa sesuai aturan proyek.
- `todo.md` diperbarui pada baris task milik anggota dan Log yang sesuai.
- PR telah direview dan di-merge dengan cara yang ditetapkan sebelum hasil diserahkan ke anggota berikutnya.

## 11. Checklist awal sebelum kolaborasi dimulai

- [ ] Repository lokal telah diperiksa status dan riwayat Git-nya.
- [ ] Tidak ada `.env`, password, token, dump, atau SQL lokal berisi rahasia pada commit yang akan dipublikasikan.
- [ ] Repository GitHub telah dibuat dengan visibility yang disepakati.
- [ ] `main` dan branch persiapan sudah di-push.
- [ ] PR awal sudah direview dan digabung.
- [ ] Perlindungan `main` telah diaktifkan.
- [ ] B dan C sudah diundang dan menerima undangan collaborator.
- [ ] B dan C sudah menyiapkan lingkungan lokal dan melaporkan hasil tanpa mengirim kredensial.
- [ ] Pembaruan aturan pada `AGENTS.md` dan `todo.md` diajukan melalui PR.
- [ ] Task GitHub Actions dibuat dan CI diuji sebelum dijadikan syarat wajib merge.

## 12. Daftar periksa peninjau

- [ ] Aturan bisnis hanya di `app/Services`, Controller tipis, Vue tidak menghitung stok atau status
- [ ] Setiap perubahan stok lewat `BukuBesarStok` dalam transaksi
- [ ] Ada tes untuk perilaku baru, dan tes lulus
- [ ] Tidak ada `.env`, kata sandi, dump SQL, atau data nyata
- [ ] Tidak ada `migrate:fresh` atau perintah merusak di luar `_dev` dan `_test`
- [ ] Bentuk JSON endpoint tercatat di `docs/DESAIN.md` bagian 8
- [ ] Sesuai `docs/DESAIN.md` dan T-xx yang dirujuk task

## 13. Anggota baru atau mesin baru

1. Pasang PHP 8.4, Composer, Node LTS, Git, dan MySQL 8.4 (minimal 8.0.16).
2. Atur email Git ke alamat noreply GitHub, lalu clone repo.
3. Jalankan Task 0.2 sampai 0.5 di mesin sendiri tanpa mencentangnya di `todo.md`. Database dev dan test dibuat dengan `scripts/db/setup-dev.sql.example`: salin ke `setup-dev.local.sql`, isi kata sandi sendiri, jalankan sebagai root, lalu hapus salinannya.
4. Baca `AGENTS.md`, berkas ini, `docs/PRD.md` bagian 1 sampai 8, dan bagian `docs/DESAIN.md` untuk peran Anda: B bagian 2 sampai 7, C bagian 8 dan 10, A semuanya.
5. Uji bahwa agent membaca `AGENTS.md` (Task 0.5).

---

**Catatan keamanan repository public:** menjadikan repository public berarti seluruh isi yang di-push dapat dibaca publik. Menghapus file rahasia dari versi terbaru tidak menghapusnya dari riwayat Git. Jika rahasia pernah ter-commit, rotasi/revoke rahasia tersebut dan tangani riwayat Git sebelum repository dipublikasikan.

- Gunakan alamat email noreply GitHub untuk commit dan aktifkan 'Block command line pushes that expose my email' di pengaturan GitHub.
- Jangan menulis nama pengguna Windows atau path pribadi di Log dan dokumen.
