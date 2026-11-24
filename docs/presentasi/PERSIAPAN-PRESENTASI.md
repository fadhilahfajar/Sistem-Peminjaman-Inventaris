# Panduan Presentasi Sistem Peminjaman Inventaris

Dokumen ini membantu menyiapkan presentasi dan demo Sistem Peminjaman Inventaris. Sesuaikan durasi dengan waktu yang diberikan. Tampilkan hanya fitur yang sudah berhasil diverifikasi pada branch dan lingkungan yang dipakai untuk demo.

## Tujuan presentasi

Jelaskan bagaimana aplikasi membantu tim IT mencatat peminjaman, pengembalian, dan kondisi barang inventaris. Tekankan dua prinsip:

- Perubahan stok divalidasi dan diproses backend dalam transaksi.
- Barang yang dikembalikan baru menambah stok tersedia setelah petugas memeriksa kondisi fisiknya.

## Ringkasan proyek

Aplikasi web memiliki dua mode:

- **Kiosk peminjam:** digunakan tanpa akun untuk mencari barang, mengajukan peminjaman atau pemesanan, melihat status, dan mengajukan pengembalian.
- **Panel admin:** digunakan petugas IT yang login untuk mengelola inventaris, memproses pengembalian, mengatur data sistem, dan melihat laporan.

Teknologi proyek:

- Frontend: JavaScript, Vue 3, Vite, Vue Router, Pinia, Axios, dan Tailwind CSS.
- Backend: Laravel 13 dengan API JSON.
- Database: MySQL 8.4.
- Runtime frontend: Node.js 24.

Frontend menampilkan data dan mengirim aksi pengguna. Validasi dan aturan bisnis berada di backend, termasuk keputusan perubahan stok.

## Persiapan sebelum presentasi

### Aplikasi dan perangkat

- Pastikan laptop/PC, browser, proyektor, jaringan lokal, serta kabel atau adaptor berfungsi.
- Jalankan backend dan frontend sesuai konfigurasi lokal proyek.
- Pastikan halaman kiosk dapat dibuka dan API memberikan respons.
- Jika panel admin akan didemokan, pastikan akun demo tersedia dan login sudah diuji. Jangan menaruh kata sandi di dokumen atau menampilkannya ke audiens.
- Gunakan data demo yang tidak sensitif. Jangan memakai data atau kredensial produksi.
- Tutup terminal, tab browser, dan berkas yang dapat menampilkan `.env`, kata sandi, token, atau data pribadi.
- Siapkan browser pada ukuran layar yang akan dipakai. Jika memungkinkan, uji pula tampilan ponsel/tablet yang relevan.

### Rencana cadangan

- Simpan beberapa tangkapan layar atau rekaman alur demo yang sudah diverifikasi.
- Siapkan penjelasan arsitektur dan alur sistem yang tetap dapat disampaikan tanpa koneksi.
- Jika satu fitur gagal, jangan mengulang aksi tulis berkali-kali. Jelaskan kendalanya, lanjutkan dengan tangkapan layar, atau pindah ke bagian yang stabil.
- Jangan menjalankan perintah reset database untuk memperbaiki demo. Perintah destruktif hanya diizinkan pada database `inventaris_it_dev` dan `inventaris_it_test`; tes hanya pada database yang berakhiran `_test`.

## Susunan presentasi 8–10 menit

### 1. Pembukaan — sekitar 1 menit

Contoh narasi:

> Sistem Peminjaman Inventaris dibuat untuk membantu tim IT mencatat barang yang dipinjam, dipesan, dan dikembalikan. Aplikasi memisahkan pengalaman peminjam di kiosk dari pekerjaan petugas di panel admin.

Jelaskan masalah yang ingin dibantu: pencatatan manual menyulitkan pemantauan ketersediaan barang, status peminjaman, dan pemeriksaan pengembalian.

### 2. Kiosk peminjam — sekitar 3 menit

Gunakan data demo dan lakukan alur yang sudah diuji:

1. Tampilkan beranda kiosk.
2. Buka katalog; tunjukkan pencarian, filter kategori, dan informasi ketersediaan.
3. Pilih barang dan tunjukkan keranjang serta formulir peminjaman.
4. Jika lingkungan demo siap dan pengiriman transaksi memang diinginkan, kirim satu permohonan demo.
5. Tunjukkan bukti peminjaman dan kode yang ditampilkan kepada peminjam.
6. Bila tersedia data transaksi demo yang sesuai, tunjukkan pencarian status dan proses klaim pengembalian.

Jangan menyebut stok akan berubah hanya karena tombol ditekan. Jelaskan bahwa permohonan divalidasi server; pengembalian menunggu pemeriksaan petugas sebelum stok diperbarui.

### 3. Panel admin — sekitar 2–3 menit

Login menggunakan akun demo yang sudah disiapkan. Pilih beberapa layar yang stabil, misalnya:

- Dasbor dan daftar peminjaman.
- Verifikasi klaim pengembalian.
- Pengelolaan barang atau stok rusak.
- Pengaturan, akun admin, jejak audit, atau laporan jika fitur tersebut sudah tersedia dan sudah diuji di build demo.

Hindari mengubah stok atau data penting yang bukan data demo. Jika hanya ingin menjelaskan layar, jangan simpan perubahan.

### 4. Arsitektur dan aturan penting — sekitar 1–2 menit

Jelaskan bahwa aplikasi menggunakan arsitektur tiga bagian. Browser menjalankan frontend **Vue dengan JavaScript**; frontend berkomunikasi dengan backend **Laravel melalui API JSON**; backend menjadi satu-satunya lapisan yang mengakses **MySQL**.

```text
Pengguna
   ↓
Frontend Vue/JavaScript
   ↓ permintaan/respons JSON melalui API
Backend Laravel
   ├─ validasi permintaan
   ├─ Service: aturan bisnis dan transaksi
   └─ akses data melalui model
   ↓
MySQL
```

Tanggung jawab setiap bagian:

- **Frontend Vue/JavaScript** menampilkan halaman kiosk dan admin, mengumpulkan input, lalu mengirim aksi pengguna. Frontend tidak menghitung stok atau menentukan sendiri apakah aksi bisnis diperbolehkan.
- **Backend Laravel API** memvalidasi input, memeriksa aturan bisnis, mengatur autentikasi/otorisasi, dan menjalankan operasi transaksi. Aturan bisnis berada di Service; controller menerima permintaan dan meneruskan proses.
- **MySQL** menyimpan data aplikasi dan transaksi. Database hanya diakses oleh backend, bukan langsung oleh browser.

Saat demo, gambarkan satu aksi peminjaman seperti ini:

1. Pengguna mengisi formulir pada frontend.
2. Frontend mengirim permintaan JSON ke endpoint Laravel.
3. Backend memvalidasi data dan meminta Service menerapkan aturan peminjaman.
4. Service menyimpan transaksi ke MySQL; bila stok berubah, perubahan barang dan catatan buku besar stok dilakukan dalam transaksi yang sama.
5. Backend mengirim respons JSON, lalu frontend menampilkan hasilnya.

Tekankan bahwa **aturan bisnis dan seluruh perubahan stok ditangani backend**. Browser tidak menjadi sumber kebenaran untuk stok atau status peminjaman; backend memvalidasi ulang setiap aksi.

### 5. Penutup — sekitar 1 menit

- Rangkum manfaat yang sudah dapat ditunjukkan.
- Sebutkan batasan atau fitur yang belum selesai secara jujur.
- Jelaskan langkah berikutnya berdasarkan status task terbaru di `todo.md`.
- Beri waktu untuk pertanyaan.

## Checklist singkat tepat sebelum demo

- [ ] Branch dan versi aplikasi yang dipresentasikan sudah dipastikan.
- [ ] Backend, frontend, dan database berjalan.
- [ ] Halaman yang akan ditampilkan sudah dicoba dari awal sampai akhir.
- [ ] Akun dan data demo siap; tidak ada kredensial atau data pribadi yang terlihat.
- [ ] Browser berada pada halaman awal yang benar dan zoom nyaman dibaca.
- [ ] Tangkapan layar/rekaman cadangan tersedia.
- [ ] Tidak ada perubahan data atau reset database yang tidak direncanakan.
- [ ] Daftar fitur selesai dan belum selesai sudah diperiksa di `todo.md`.

## Pertanyaan yang mungkin muncul

**Siapa yang menggunakan aplikasi?**  
Peminjam menggunakan kiosk tanpa akun; petugas IT menggunakan panel admin dengan login.

**Bagaimana aplikasi mencegah stok tidak konsisten?**  
Backend menjalankan validasi dan aturan bisnis dalam transaksi. Perubahan stok dicatat melalui jalur buku besar, bukan dihitung atau ditulis langsung oleh frontend.

**Apa yang terjadi ketika barang dikembalikan?**  
Peminjam mengajukan klaim. Petugas memeriksa barang dan memverifikasi hasilnya; stok diperbarui berdasarkan hasil verifikasi.

**Apa teknologi yang digunakan?**  
Vue dan JavaScript untuk frontend, Laravel untuk API backend, serta MySQL untuk penyimpanan data.

**Apakah semua fitur sudah siap dipakai?**  
Jawab berdasarkan versi yang benar-benar diuji untuk presentasi. Bedakan fitur yang sudah didemokan dari pekerjaan yang masih tercatat belum selesai.
