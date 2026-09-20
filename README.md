# 📊 Deploy Server Monitoring App di AWS EC2

Panduan ini menjelaskan langkah demi langkah untuk melakukan deploy sebuah aplikasi **Server Monitoring Dashboard** pada instance **EC2 (Ubuntu)** di AWS, sebagai bagian dari task DevOps Engineer.

Aplikasi ini akan menampilkan metrik real-time server seperti penggunaan **CPU**, **Disk**, **Memory**, dan **Network**, yang bisa diakses langsung melalui browser.

---

## 📋 Daftar Isi

- [Prasyarat](#-prasyarat)
- [1. Instalasi Paket yang Dibutuhkan](#1-instalasi-paket-yang-dibutuhkan)
- [2. Upload File Aplikasi ke Server](#2-upload-file-aplikasi-ke-server)
- [3. Masuk ke Direktori Web Root](#3-masuk-ke-direktori-web-root)
- [4. Jalankan Script Instalasi](#4-jalankan-script-instalasi)
- [5. Verifikasi Dashboard](#5-verifikasi-dashboard)
- [6. Stress Test CPU](#6-stress-test-cpu)
- [7. Stress Test Disk](#7-stress-test-disk)
- [Troubleshooting](#-troubleshooting)
- [Lisensi](#-lisensi)

---

## ✅ Prasyarat

Sebelum memulai, pastikan kamu sudah memiliki:

- Instance **EC2 Ubuntu** (versi 22.04 / 24.04 LTS disarankan) yang sudah running.
- Akses **SSH** ke instance tersebut (via key pair `.pem`).
- **Security Group** EC2 yang mengizinkan inbound traffic pada port `22` (SSH) dan `80` (HTTP).
- **Git** terpasang di server untuk melakukan clone repository aplikasi.
- Akses root/sudo di server target.

> ⚠️ **Catatan:** Spesifikasi instance EC2 bebas (misal `t2.micro` untuk free tier sudah cukup untuk testing).

> ⚠️ **Catatan kompatibilitas Ubuntu 26.04 LTS:** Ubuntu 26.04 ("Resolute Raccoon") sudah tersedia dan membawa **PHP 8.5** sebagai default, jauh lebih baru dibanding versi yang biasa digunakan tools monitoring lama. Sebelum deploy di 26.04, perhatikan:
> - Cek isi `install.sh` untuk memastikan tidak ada referensi nama service versi lama (mis. `php7.4-fpm`, `php8.1-fpm`) — pada Ubuntu 26.04 nama service-nya adalah `php8.5-fpm`.
> - Cek apakah aplikasi memakai fungsi PHP yang sudah *deprecated*/dihapus di PHP 8.5 (mis. fungsi `mysql_*` lama).
> - Paket `php-json` sejak PHP 8.0 sudah menyatu ke core PHP, sehingga di 26.04 kemungkinan tidak lagi tersedia sebagai paket terpisah — jika `apt install` menolaknya, cukup hapus `php-json` dari daftar paket yang diinstal.
> - Jika ingin kompatibilitas paling aman dan teruji untuk tools monitoring lawas, **Ubuntu 22.04 atau 24.04 LTS** (PHP 8.1 / 8.3) masih jadi pilihan yang lebih stabil dibanding 26.04.

---

## 1. Instalasi Paket yang Dibutuhkan

Masuk ke server via SSH, lalu naikkan hak akses menjadi root dan update daftar paket:

```bash
sudo su
apt update
```

Install semua paket yang dibutuhkan aplikasi (web server, PHP, dan tools monitoring sistem):

```bash
apt install -y \
  apache2 \
  php php-fpm libapache2-mod-php \
  php-sqlite3 php-cli php-curl php-json \
  sysstat vnstat lm-sensors smartmontools \
  net-tools procps cron mc
```

**Penjelasan paket:**

| Paket | Fungsi |
|---|---|
| `apache2` | Web server untuk menyajikan dashboard aplikasi |
| `php`, `php-fpm`, `libapache2-mod-php` | Runtime PHP untuk menjalankan aplikasi backend |
| `php-sqlite3` | Driver database SQLite yang digunakan aplikasi |
| `php-cli`, `php-curl`, `php-json` | Modul tambahan PHP untuk eksekusi CLI dan komunikasi API |
| `sysstat` | Mengumpulkan statistik penggunaan sistem (CPU, I/O) |
| `vnstat` | Monitoring penggunaan bandwidth/network |
| `lm-sensors` | Membaca sensor suhu hardware |
| `smartmontools` | Monitoring kesehatan disk (S.M.A.R.T.) |
| `net-tools` | Utilitas jaringan (`ifconfig`, `netstat`, dll) |
| `procps` | Utilitas monitoring proses (`ps`, `top`, `free`, dll) |
| `cron` | Menjalankan tugas terjadwal (scheduled tasks) |
| `mc` | Midnight Commander, file manager berbasis terminal (opsional, memudahkan navigasi file) |

---

## 2. Clone File Aplikasi dari Repositori ini ke Server Instance EC2

Ambil source code aplikasi langsung dari repository GitHub menggunakan `git clone`.

Pastikan `git` sudah terpasang:

```bash
apt install -y git
```

Bersihkan lebih dulu folder web root dari file default Apache (`index.html`) agar tidak konflik dengan file aplikasi:

```bash
rm -rf /var/www/html/*
```

Clone repository aplikasi langsung ke direktori web root:

```bash
git clone https://github.com/paknux/appServerMonAWS.git /var/www/html
```

Masuk ke direktori web root untuk memastikan file sudah tersalin dengan benar:

```bash
cd /var/www/html
ls -la
```

> 💡 **Tips:** Menggunakan `git clone` lebih disarankan dibanding upload manual (WinSCP/`scp`) karena lebih cepat, konsisten, dan memudahkan update aplikasi di kemudian hari cukup dengan `git pull` tanpa perlu upload ulang seluruh file.

---

## 3. Masuk ke Direktori Web Root

```bash
cd /var/www/html
```

---

## 4. Jalankan Script Instalasi

Aplikasi ini menyediakan script `install.sh` yang berfungsi menghubungkan seluruh komponen (permission, service, konfigurasi Apache, dll) secara otomatis.

Berikan izin eksekusi terlebih dahulu, lalu jalankan:

```bash
chmod +x install.sh
./install.sh
```

> ⚠️ **Penting:** Jalankan script ini sebagai **root** (atau dengan `sudo`) agar seluruh proses instalasi (permission file, restart service, dll) berjalan tanpa error.

---

## 5. Verifikasi Dashboard

Setelah proses instalasi selesai, buka browser dan akses dashboard menggunakan IP publik dari instance EC2:

```
http://IP-PUBLIC
```

Pastikan dashboard menampilkan data monitoring server secara real-time (CPU, RAM, Disk, Network).

> 🔒 Ganti `IP-PUBLIC` dengan alamat IP publik instance EC2 kamu, dan pastikan Security Group sudah mengizinkan port `80` dari luar.

---

## 6. Stress Test CPU

Untuk menguji apakah dashboard dapat menangkap perubahan beban CPU secara real-time, lakukan stress test menggunakan tool `stress`.

Install tool `stress`:

```bash
apt install -y stress
```

Jalankan stress test pada seluruh core CPU selama 60 detik:

```bash
stress --cpu $(nproc) --timeout 60
```

**Penjelasan parameter:**

- `--cpu $(nproc)` → Menjalankan proses stress sejumlah core CPU yang tersedia (`nproc` otomatis mendeteksi jumlah core).
- `--timeout 60` → Durasi stress test berjalan selama 60 detik, lalu berhenti otomatis.

Selama proses ini berjalan, amati dashboard untuk melihat lonjakan penggunaan CPU secara real-time.

---

## 7. Stress Test Disk

Untuk menguji perubahan penggunaan disk pada dashboard, buat beberapa file besar menggunakan `fallocate`.

Buat 3 file berukuran 1 GB masing-masing:

```bash
fallocate -l 1G testfile1.bin
fallocate -l 1G testfile2.bin
fallocate -l 1G testfile3.bin
```

**Penjelasan parameter:**

- `-l 1G` → Menentukan ukuran file yang dialokasikan, dalam hal ini 1 Gigabyte.
- `fallocate` mengalokasikan ruang disk secara instan tanpa benar-benar menulis data, sehingga proses pembuatan file sangat cepat namun tetap mempengaruhi statistik penggunaan disk.

Amati dashboard untuk melihat perubahan pemakaian **disk space** setelah setiap file dibuat.

**Bersihkan file uji setelah selesai testing** (opsional, agar tidak memenuhi disk secara permanen):

```bash
rm -f testfile1.bin testfile2.bin testfile3.bin
```

---

## 🛠️ Troubleshooting

| Masalah | Kemungkinan Penyebab | Solusi |
|---|---|---|
| Dashboard tidak bisa diakses dari browser | Security Group belum mengizinkan port 80 | Tambahkan inbound rule port `80` (HTTP) di EC2 Security Group |
| `install.sh: Permission denied` | Script belum diberi izin eksekusi | Jalankan `chmod +x install.sh` |
| Data monitoring tidak update | Service `cron` atau `sysstat` belum aktif | Cek status dengan `systemctl status cron sysstat` |
| Error terkait database | Modul `php-sqlite3` belum terpasang dengan benar | Jalankan ulang `apt install -y php-sqlite3` lalu restart Apache: `systemctl restart apache2` |

---

## 📄 Lisensi

Proyek ini dibuat untuk keperluan pembelajaran/testing DevOps Engineering. Silakan sesuaikan lisensi sesuai kebutuhan proyekmu.