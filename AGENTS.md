# AGENTS.md

Aturan kerja untuk agent AI di repo ini. Baca seluruhnya di awal setiap sesi. Berkas ini sengaja pendek. Rincian ada di `docs/`.

## Proyek

Sistem Peminjaman & Inventaris Barang Tim IT, dibangun dari nol. Dua mode: **kiosk** (peminjam, tanpa akun) dan **panel admin** (login).

- Backend: Laravel 13, API JSON murni, PHP 8.4, folder `backend/`
- Frontend: Vue + Vite + Tailwind CSS, folder `frontend/`. Bahasa (JS atau TS) mengikuti K-1 di PRD bagian 15
- Database: MySQL 8.4 InnoDB, hanya diakses backend
- Login admin: Laravel Sanctum mode SPA (sesi cookie). Peminjam tidak punya akun

## Dokumen (baca sesuai kebutuhan, jangan ditebak)

| Butuh apa | Baca |
|---|---|
| Maksud fitur, kriteria lulus (T-xx), keputusan terbuka (K-x) | `docs/PRD.md` |
| Aturan bisnis BR-01..34, skema, status, Service, API, UI | `docs/DESAIN.md` |
| Task yang harus dikerjakan | `todo.md` |
| Cara tim bekerja: pemilik task, alur Git dan PR, review | `PANDUAN-KOLABORASI-TIM.md` |

Bila dokumen bertentangan satu sama lain atau dengan permintaan, **berhenti dan tanyakan**. Jangan memilih sendiri. Keputusan bertanda **K-x** belum diputuskan: pakai nilai bawaan sementara di PRD dan sebutkan itu di log.

## Aturan wajib

1. Satu task per giliran, urut dalam daftar task milik pengguna (PANDUAN-KOLABORASI-TIM.md bagian 2). Jangan mengerjakan task milik anggota lain dan jangan melompat fase di luar yang diizinkan PANDUAN-KOLABORASI-TIM.md bagian 3 dan 4. Sebelum menulis kode, baca bagian PRD dan DESAIN yang dirujuk task.
2. **Aturan bisnis hanya di `backend/app/Services`.** Controller tipis. Frontend tidak menghitung stok atau status dan tidak memutuskan boleh/tidaknya aksi.
3. **Status peminjaman tidak disimpan di kolom.** Selalu diturunkan oleh `StatusPeminjaman`.
4. **Stok hanya berubah lewat Service**, dan setiap perubahan `items.total/tersedia/rusak` menulis tepat satu baris `stock_movements` di transaksi yang sama, lewat `BukuBesarStok`. Dilarang meng-update angka stok langsung dari Controller, seeder bisnis, atau tes tanpa jalur ini.
5. Operasi multi-baris dibungkus `DB::transaction()` dengan percobaan ulang untuk deadlock. Urutan kunci: `loans`/klaim dulu, lalu `items` menurut `id` menaik.
6. Tabel append-only (`loan_extensions`, `loan_corrections`, `stock_movements`, `audit_logs`): tidak ada `UPDATE` atau `DELETE` dari aplikasi.
7. Semua FK `RESTRICT`. Data yang pernah dipakai transaksi tidak dihapus fisik (barang soft-delete, divisi, kategori, dan akun dinonaktifkan).
8. Validasi lewat Form Request. Galat JSON seragam `{kode, pesan, errors?}`. Kode status: 422 validasi, 409 konflik, 429 batas laju, 401, 403, 419.
9. Tanggal kalender `Y-m-d` zona Asia/Jakarta lewat `Support\Tanggal`. Waktu kejadian UTC. Jangan menghitung selisih hari dari Carbon bertipe berbeda zona.
10. JSON memakai `snake_case`. Nama kolom, model, dan Service domain memakai Bahasa Indonesia sesuai `DESAIN.md`. Teks antarmuka dan pesan galat berbahasa Indonesia.
11. Kode pinjam dan klaim tidak boleh masuk URL, log, atau penyimpanan browser.
12. Tidak ada kredensial, `.env`, atau dump SQL di Git. Tidak ada password bawaan di kode, seeder, README, atau bundle.
13. **Dilarang menjalankan `migrate:fresh`, `db:wipe`, atau perintah merusak pada database selain `inventaris_it_dev` dan `inventaris_it_test`.** Tes hanya boleh berjalan bila nama database berakhiran `_test`.
14. Jangan memasang paket atau mengubah versi stack tanpa disebut task. Verifikasi perintah dan API Laravel 13 ke dokumentasi resmi, jangan dari ingatan.
15. Jangan membuat berkas dokumentasi baru kecuali diminta.

## Selesai berarti

- [ ] Kode berjalan dan tes terkait lulus. `php artisan test` hijau
- [ ] Ada tes untuk perilaku baru (Feature atau Unit)
- [ ] Tidak ada logika bisnis di Controller atau komponen Vue
- [ ] Tidak ada `dd()`, `console.log`, atau kode uji di bundle produksi
- [ ] Format rapi (`./vendor/bin/pint`)
- [ ] Task dicentang di `todo.md` dan satu-dua kalimat ditulis di sub-bagian Log milik pengguna (Log A, Log B, atau Log C) (tanggal, ID task, hasil, catatan bila memakai nilai bawaan K-x)

## Perintah

Sebagian perintah baru ada setelah task terkait selesai.

```
# backend (jalankan dari backend/)
php artisan serve                       # dev, port 8000
php artisan test                        # semua tes
./vendor/bin/pint                       # format
php artisan inventaris:periksa-invarian     # ada setelah todo 2.12
php artisan inventaris:periksa-lingkungan   # ada setelah todo 1.4
# migration di lingkungan nyata memakai akun migrator (lihat DESAIN 11.2)

# frontend (jalankan dari frontend/)
npm run dev                             # dev, port 5173, proxy /api dan /sanctum ke backend
npm run build
```

## Git

Alur lengkap ada di PANDUAN-KOLABORASI-TIM.md bagian 5. Ringkasnya: kerja di branch `f<fase>/<id-task>-<slug>`, commit kecil berawalan `feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`. Agent tidak push dan tidak merge ke `main`. `main` hanya berubah lewat Pull Request yang disetujui anggota lain, dan push dilakukan pengguna.

## Bila ragu

Tanyakan, jangan menebak. Terutama untuk: aturan bisnis yang tidak tertulis, keputusan K-x, apa pun yang menyentuh stok atau data produksi.
