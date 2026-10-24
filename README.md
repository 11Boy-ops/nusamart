# NusaMart — Website E-commerce (Tahap 1 & 2)

Website e-commerce responsif menggunakan **HTML, CSS, JavaScript, PHP, dan MySQL**. Paket ini mencakup landing page, katalog, detail produk, pencarian, login/registrasi, keranjang berbasis database, checkout, dan halaman konfirmasi pesanan. Pembayaran transfer masih berupa simulasi dan belum terhubung ke payment gateway.

## Fitur yang sudah tersedia

- Landing page responsif untuk desktop, tablet, dan ponsel.
- Daftar kategori dan produk unggulan dari database.
- Pencarian produk berdasarkan nama, deskripsi singkat, atau kategori.
- Registrasi pelanggan baru.
- Login dan logout dengan session PHP.
- Kata sandi disimpan menggunakan `password_hash()` dan diverifikasi dengan `password_verify()`.
- Perlindungan CSRF untuk formulir login, registrasi, dan logout.
- Validasi input, prepared statements PDO, state akun aktif, dan escaping output HTML.
- Akun demo yang dapat dipakai untuk mencoba login.
- Katalog dengan filter kategori, sort harga/nama/terbaru, dan pagination.
- Halaman detail produk dengan status stok dan pilihan jumlah.
- Pencarian berdasarkan nama, kategori, dan deskripsi produk.
- Keranjang per akun: tambah, ubah kuantitas, hapus, dan hitung subtotal.
- Checkout dengan alamat, pilihan transfer simulasi atau COD, ongkir, dan validasi stok.
- Pembuatan pesanan dalam transaksi database, snapshot harga/nama produk, pengurangan stok atomik, dan halaman konfirmasi pesanan.

## Cara menjalankan di Windows menggunakan XAMPP

> **Catatan:** file `database.sql` menyiapkan ulang tabel NusaMart dan memasukkan data contoh. Impor ulang akan menghapus data NusaMart yang sudah ada di tabel-tabel tersebut.

1. Ekstrak folder `nusamart` ke `C:\xampp\htdocs\nusamart`.
2. Buka XAMPP Control Panel, lalu jalankan **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih tab **Import**, klik **Choose File**, pilih file `database.sql` dari folder **sql** dalam folder `nusamart`, lalu klik **Import/Go**.
5. Buka `http://localhost/nusamart/` di browser.
6. Untuk memastikan autentikasi bekerja, pilih **Masuk** atau **Daftar**.

## Akun demo

- Email: `demo@nusamart.id`
- Kata sandi: `Belanja123!`

Akun ini hanya untuk pengujian lokal/proyek tugas. Ubah atau hapus sebelum website dipublikasikan secara umum.

## Konfigurasi koneksi database

File: `config/db.php`

Nilai bawaan sesuai instalasi XAMPP pada umumnya:

- Host: `127.0.0.1`
- Database: `nusamart_db`
- Username: `root`
- Password: kosong

Apabila konfigurasi MySQL berbeda, ubah nilai default pada `config/db.php` atau gunakan environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.

## Struktur folder

```text
nusamart/
├── index.php                  # Landing page + pencarian produk beranda
├── katalog.php                # Katalog, pencarian, filter, urutan, pagination
├── detail.php                 # Detail produk dan tambah ke keranjang
├── keranjang.php              # Keranjang database pengguna
├── checkout.php               # Alamat, ongkir, metode dan pembuatan pesanan
├── pesanan-sukses.php         # Ringkasan pesanan milik pengguna
├── login.php                  # Login pelanggan
├── register.php               # Registrasi pelanggan
├── logout.php                 # Logout via POST + CSRF
├── database.sql               # Skema dan data awal untuk phpMyAdmin
├── config/
│   ├── db.php                 # Koneksi PDO ke MySQL
│   └── init.php               # Inisialisasi session dan helper
├── includes/
│   ├── functions.php          # Helper escaping, CSRF, flash, mata uang
│   ├── site-header.php        # Header dan navigasi
│   └── site-footer.php        # Footer
└── assets/
    ├── css/style.css          # Desain responsif
    ├── js/app.js              # Interaksi menu, password, dan validasi UI
    └── img/product-placeholder.svg
```

## Struktur database

| Tabel | Fungsi |
|---|---|
| `users` | Data akun, peran, dan hash kata sandi |
| `categories` | Kategori produk |
| `products` | Data dan harga produk |
| `carts` | Keranjang aktif milik pengguna |
| `cart_items` | Daftar produk di keranjang dan kuantitas |
| `addresses` | Alamat pengiriman pengguna |
| `orders` | Data transaksi dan status pesanan |
| `order_items` | Snapshot nama, harga, dan kuantitas produk yang dipesan |
| `payments` | Catatan metode dan status pembayaran |

Relasi utama:

- Satu kategori memiliki banyak produk (`categories` → `products`).
- Satu pengguna memiliki satu keranjang aktif, dan keranjang berisi banyak item (`users` → `carts` → `cart_items`).
- Satu pengguna dapat menyimpan banyak alamat dan membuat banyak pesanan.
- Satu pesanan memiliki banyak item pesanan dan dapat memiliki catatan pembayaran.

## Catatan tahap pengerjaan

Pembayaran otomatis belum tersedia. Opsi transfer pada proyek ini merupakan simulasi dan status pembayaran dibuat pending; integrasi payment gateway perlu ditambahkan sebelum website digunakan untuk transaksi nyata. Ongkos kirim demo Rp15.000 dan gratis untuk subtotal minimal Rp500.000. Harga/ongkir tetap dihitung kembali di server saat checkout, dan stok divalidasi dalam transaksi database.

Gambar produk menggunakan URL gambar eksternal; koneksi internet diperlukan untuk menampilkan foto dari sumber tersebut. Bila gambar tidak dapat diakses, kartu produk menampilkan ilustrasi placeholder.
