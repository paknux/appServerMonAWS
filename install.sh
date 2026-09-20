#!/bin/bash
# install.sh - Server Monitoring Dashboard (Debian 13 / Trixie)
# 100% apt, TANPA composer, TANPA npm/CDN untuk chart (canvas custom).

set -e

echo ">>> Catatan: collector.php sekarang dipicu dari browser (index.php), bukan cron."
echo ">>> Artinya data hanya terekam selama ada dashboard yang terbuka di browser."

echo ">>> Aktifkan sysstat (agar mpstat merekam data)..."
sudo sed -i 's/ENABLED="false"/ENABLED="true"/' /etc/default/sysstat || true
sudo systemctl enable --now sysstat

echo ">>> Setup vnstat untuk interface default..."
IFACE=$(ip route | awk '/default/ {print $5; exit}')
sudo vnstat -u -i "$IFACE" || true
sudo systemctl enable --now vnstat

echo ">>> Deteksi sensor suhu (jawab 'YES' semua saat ditanya interaktif)..."
sudo sensors-detect --auto || true
sudo systemctl enable --now apache2

echo ">>> Beri izin sudo tanpa password untuk perintah smartctl & sensors ke www-data..."
echo "www-data ALL=(root) NOPASSWD: /usr/sbin/smartctl, /usr/bin/sensors" | sudo tee /etc/sudoers.d/monitor-dashboard

echo ">>> Buat folder aplikasi & database sqlite..."
sudo mkdir -p /var/www/html/data
sudo chown -R www-data:www-data /var/www/html/data

echo ">>> Collector TIDAK pakai cron — dipanggil otomatis dari index.php tiap 5 detik saat dashboard dibuka."

echo ">>> Selesai!"
echo ">>> Akses via http://IP-SERVER/"
echo ">>> Catatan: interface jaringan terdeteksi = $IFACE (cek/ubah di collector.php jika salah)"