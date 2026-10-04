# CUANTAH

Aplikasi pengelolaan setoran minyak jelantah: rumah tangga dan UMKM menyetor
jelantah bekas, mitra pengumpul menimbang dan membayarnya, lalu volumenya
disalurkan ke pengolah.

Dibangun dengan Laravel 13 dan Tailwind CSS 4.

## Peran

| Peran | Yang bisa dilakukan |
|---|---|
| **Penyetor** | Mengajukan setoran (dijemput atau diantar sendiri), melihat estimasi nilai dan ongkir sebelum mengirim, menampilkan barcode untuk drop-off, membatalkan selagi masih menunggu, mengonfirmasi penerimaan pembayaran, dan menyanggah takaran dalam tiga hari setelah transaksi selesai. |
| **Karyawan** | Mengambil pickup yang tersedia, memindai barcode drop-off, menandai sudah dijemput, lalu memverifikasi volume aktual beserta cara dan status pembayarannya. |
| **Admin** | Semua yang bisa dilakukan karyawan, ditambah penugasan pickup, pengelolaan mitra dan ongkir per jarak, pengelolaan pengguna, penetapan harga jelantah, pencatatan penyaluran keluar, laporan, dan penanganan sanggahan. |

Admin dan karyawan **terikat pada mitra**: keduanya hanya melihat data mitra
yang terhubung dengan akunnya. Pengikatan itu diuji di
`tests/Feature/PartnerScopingTest.php` dan `tests/Feature/AccessBoundaryTest.php`.

## Menjalankan secara lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
composer run dev
```

`composer run dev` menjalankan server PHP, Vite, dan pekerja antrean sekaligus.
Aplikasi terbuka di <http://127.0.0.1:8000>.

### Akun contoh

Seeder membuat tiga akun untuk mencoba ketiga peran:

| Peran | Email |
|---|---|
| Admin | `admin@cuantah.test` |
| Karyawan | `karyawan@cuantah.test` |
| Penyetor | `user@cuantah.test` |

Kata sandi ketiganya sama, diambil dari `SEED_PASSWORD` di `.env`. Isi lebih
dulu sebelum menjalankan seeder, misalnya:

```
SEED_PASSWORD=pwakundem0
```

Bila dibiarkan kosong, seeder membuat kata sandi acak dan menampilkannya sekali
di layar; catat saat itu juga, sebab nilainya tidak disimpan di mana pun. Kata
sandinya harus memuat huruf dan angka serta sedikitnya delapan karakter, sama
seperti aturan yang berlaku di dalam aplikasi.

Kata sandi ketiga akun dapat diganti belakangan tanpa menyentuh data lain:

```bash
php artisan cuantah:rotate-seed-passwords --force --password=pwakundem0
```

Selain ketiganya, seeder juga mengisi mitra, riwayat harga, serta transaksi dan
penyaluran contoh, sehingga setiap halaman langsung berisi tanpa perlu
memasukkan data lebih dulu.

## Pengujian

```bash
php artisan test
```

Feature test memakai SQLite in-memory, dan akan dilewati bila ekstensi
`pdo_sqlite` tidak terpasang.

## Aset

Tampilan bergantung pada hasil build Vite. **Setiap penyebaran wajib
menjalankan build**, sebab kelas Tailwind hanya ikut ter-compile bila benar-benar
dipakai:

```bash
npm run build
```

Tanpa langkah ini, perubahan tampilan tidak muncul dan tata letaknya bisa patah
meski kodenya sudah benar.

Tidak ada pustaka yang ditarik dari CDN saat halaman dibuka. DaisyUI, Leaflet,
dan Chart.js ikut dibundel, lalu dipecah per halaman supaya beranda publik tidak
ikut mengunduh pembaca barcode yang hanya dipakai satu halaman karyawan.

## Penyebaran

`.cpanel.yml` menyalin berkas ke `public_html/cuantah`, menjalankan migrasi,
lalu meng-cache konfigurasi. Direktori `public/build` **tidak boleh** masuk
`.gitignore`, sebab server tidak menjalankan Node.

Jangan mengaktifkan `trustProxies` selama aplikasi dilayani langsung oleh web
server. Pembatas percobaan login membaca `$request->ip()`, dan mempercayai proxy
sembarangan membuat header `X-Forwarded-For` yang dipalsukan bisa melewatinya.
