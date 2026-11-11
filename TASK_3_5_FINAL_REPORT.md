# Task 3.5: API Kiosk Perpanjangan & Pembatalan

## Summary
Implementasi endpoint kiosk perpanjangan (`POST /api/kiosk/extensions`) dan pembatalan (`POST /api/kiosk/loans/cancel`) sesuai spesifikasi DESAIN BR-11, BR-12, BR-24, BR-26, dan FR-K-07, FR-K-08, FR-K-12.

## Implemented Files

### 1. `backend/app/Http/Requests/Kiosk/SimpanPerpanjanganRequest.php`
Form request validasi endpoint perpanjangan:
- `kode_pinjam` (wajib, string)
- `tambahan_hari` (wajib, integer, min 1)
- `alasan` (opsional, string, max 500 karakter)

### 2. `backend/app/Http/Requests/Kiosk/SimpanPembatalanRequest.php`
Form request validasi endpoint pembatalan:
- `kode_pinjam` (wajib, string)

### 3. `backend/app/Http/Controllers/Api/Kiosk/ExtensionKioskController.php`
Controller `POST /api/kiosk/extensions`:
- Integrasi `PerpanjanganService::olehPeminjam()`
- Pembatas kode: `periksaTerkunci` (429) sebelum query
- Pemeriksaan keberadaan loan via `exists()` (baca-saja) sebelum Service
- Jika tidak ditemukan: `catatGagal` + respons 404 `{kode: 'kode_tidak_ditemukan', pesan: 'Kode peminjaman tidak ditemukan.'}`
- Jika sukses: `catatSukses`, baca `batas_kembali_sebelumnya` dari `loan_extensions` terbaru (baca-saja), kembalikan struktur lengkap
- Galat `GalatBisnis` dari Service (409) dan `ValidationException` (422) diteruskan apa adanya, TIDAK memicu `catatGagal`
- Tidak ada kunci idempotensi — perpanjangan ganda dibatasi kuota, pembatalan kedua mendapat 409
- Respons tanpa telepon, stok, dan id internal

### 4. `backend/app/Http/Controllers/Api/Kiosk/CancelKioskController.php`
Controller `POST /api/kiosk/loans/cancel`:
- Integrasi `PembatalanService::olehPeminjam()`
- Pembatas kode: `periksaTerkunci` (429) sebelum query
- Pemeriksaan keberadaan loan via `exists()` (baca-saja) sebelum Service
- Jika tidak ditemukan: `catatGagal` + respons 404 `{kode: 'kode_tidak_ditemukan', pesan: 'Kode peminjaman tidak ditemukan.'}`
- Jika sukses: `catatSukses`, kembalikan `{kode_pinjam, status: 'dibatalkan', dibatalkan_pada}`
- Pembatalan peminjam sebelum `tanggal_pinjam`: sukses, stok kembali, status Dibatalkan
- Pembatalan pada/ sesudah `tanggal_pinjam`: ditolak 409
- Pembatalan kedua: 409
- Kode tidak ditemukan menaikkan penghitung PembatasKodeKiosk (sama seperti lookup)
- Respons tanpa telepon, stok

### 5. `backend/routes/kiosk.php`
Menambahkan rute dalam kelompok `throttle:kiosk-tulis`:
- `POST extensions` → `ExtensionKioskController@simpan`
- `POST loans/cancel` → `CancelKioskController@batalkan`

## API Endpoints

### POST /api/kiosk/extensions
```
Request body: {kode_pinjam, tambahan_hari, alasan?}
Response 200: {data: {kode_pinjam, batas_kembali_sebelumnya, batas_kembali, tambahan_hari, perpanjangan: {terpakai, maks, sisa_kuota}, status}}
Response 404: {kode: 'kode_tidak_ditemukan', pesan: 'Kode peminjaman tidak ditemukan.'}
Response 409: {kode, pesan} dari GalatBisnis (diteruskan apa adanya)
Response 429: {lookup_terkunci, pesan, Retry-After}
```

### POST /api/kiosk/loans/cancel
```
Request body: {kode_pinjam}
Response 200: {data: {kode_pinjam, status: 'dibatalkan', dibatalkan_pada}}
Response 404: {kode: 'kode_tidak_ditemukan', pesan: 'Kode peminjaman tidak ditemukan.'}
Response 409: {kode, pesan} dari GalatBisnis (diteruskan apa adanya)
Response 429: {lookup_terkunci, pesan, Retry-After}
```

## Design Decisions (DESAIN 8.1)
- Ganti entri extensions dan cancel dengan contoh JSON nyata (200, 409/422, 404, 429)
- **Tidak ada idempotensi** — Service tidak menyediakan kunci idempotensi. Perpanjangan ganda cepat dibatasi kuota, pembatalan kedua mendapat 409.

## Verification Results

### Test Suites
- **368 backend tests passed** (3743 assertions) — semua lulus tanpa kerusakan
- **54 kiosk tests passed** (739 assertions) — semua endpoint kiosk berfungsi
- `./vendor/bin/pint --test` — Clean, 0 issues (after formatting fix)

### Test Scenarios Verified
- Perpanjangan sah: batas baru = batas lama + n hari, riwayat tercatat di `loan_extensions`
- Batas baru dihitung dari batas lama (bukan dari hari ini), BR-12
- Ditolak: terlambat, kuota habis, n > maks_hari_per_perpanjangan, barang tidak boleh diperpanjang (pesan menyebut nama barang, T-27)
- Pembatalan pemesanan sebelum tanggal pinjam: stok kembali, status Dibatalkan (T-24, assertInvarian)
- Pembatalan pada/ sesudah tanggal pinjam: ditolak
- Pembatalan kedua: 409
- Kode tidak ditemukan di kedua endpoint identik dengan lookup dan menaikkan penghitung yang sama
- Terkunci 429 walau kode benar
- Respons tanpa telepon dan stok

## CodeGraph Report

### 1. Penyebab Error (Awal)
- Watchdog timeout internal Node/MCP (~60s unresponsive saat lock sinkronisasi berfas)
- Indeks SQLite `codegraph.db` tidak rusak. Tidak ada stale PID locking.

### 2. Apa yang Diubah
- Dibuat 4 berkas baru (2 Request, 2 Controller)
- Memodifikasi `routes/kiosk.php`
- Memperbarui `todo.md` Log A
- **Tidak ada rebuild indeks** — database valid, CLI berjalan normal

### 3. Hasil Kueri Uji
- `codegraph query PembatasKodeKiosk`: Menemukan 10 simbol (class, methods, file, import paths di controller, request, dan service)

### 4. Hasil Pemeriksaan Keamanan Indeks
- Jumlah berkas `.env` terindeks: **0** (aman)
- Jumlah berkas `.env.testing` terindeks: **0** (aman)
- Jumlah berkas `vendor/` terindeks: **0** (aman)
- Jumlah berkas `node_modules/` terindeks: **0** (aman)
- Total file terindeks: **212** berkas (`backend/app`, `frontend/src`, `docs`)

### 5. Cadangan Dibuat
- **Tidak** (database valid, tidak perlu rebuild)

### 6. Status Akhir
- **Berfungsi**. Indeks utuh, query dan status CLI berjalan normal. Semua 368 backend tes dan 54 kiosk tes lulus.

## Git Status
- Branch: `feat/api-fase3-sisa` (dari origin/main)
- Modified: `backend/routes/kiosk.php`, `todo.md`
- Untracked (new): 4 berkas request/controller, 2 berkas request form
- `git status` tidak menampilkan `.codegraph/`