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

- Instance **EC2 Ubuntu** (versi 20.04 / 22.04 disarankan) yang sudah running.
- Akses **SSH** ke instance tersebut (via key pair `.pem`).
- **Security Group** EC2 yang mengizinkan inbound traffic pada port `22` (SSH) dan `80` (HTTP).
- Aplikasi **[WinSCP](https://winscp.net/)** (atau `scp`/`rsync`) terpasang di komputer lokal untuk transfer file.
- Akses root/sudo di server target.

> ⚠️ **Catatan:** Spesifikasi instance EC2 bebas (misal `t2.micro` untuk free tier sudah cukup untuk testing).

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

## 2. Upload File Aplikasi ke Server

Gunakan **WinSCP** (atau tools sejenis seperti `scp`/`rsync`) untuk meng-extract dan menyalin seluruh file aplikasi ke direktori web root server:

```
/var/www/html
```

**Alternatif via terminal (menggunakan `scp` dari komputer lokal):**

```bash
scp -i your-key.pem -r ./monitoring-app/* ubuntu@IP-PUBLIC:/tmp/monitoring-app
```

Lalu di sisi server, pindahkan ke `/var/www/html`:

```bash
sudo mv /tmp/monitoring-app/* /var/www/html/
```

> 💡 **Tips:** Pastikan folder `/var/www/html` bersih dari file default Apache (`index.html`) sebelum menyalin file aplikasi, agar tidak konflik.

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