Sebagai seorang devOps Engineer anda diminta untuk deploy app Monitoring Server di instance EC2 AWS. Anda akan melakukan testing pada sebuah server Ubuntu yang anda buat. Untuk spek bebas

Langkah pekerjaan :


1. Install Paket yang dibutuhkan
$  sudo su
#  apt update

#  apt install -y apache2 php php-fpm libapache2-mod-php php-sqlite3 php-cli php-curl php-json php-curl sysstat vnstat lm-sensors smartmontools net-tools procps cron mc


2. Extract dan copykan semua file ke /var/www/html dengan WinSCP


3. Masuk ke /var/www/html
#  cd /var/www/html

4. Ubah install.sh agar dapat diexcute, dan jalankan install.sh. Script ini adalah script utama yang menjadikan beberpa hal saling terhubung.
#  chmod  +x  install.sh
#  ./install.sh

5. testing dashboard App Monitoring sudah berjalan, menggunakan browser coba akses di http://IP-PUBLIC

6. Test loading CPU (stress test)
Dengan tampilan Jalankan stress Test ke CPU untuk melihat perubahan pemakaian CPU di dashboard
#  apt  install  -y  stress
#  stress  --cpu  $(nproc)  --timeout 60


7. Test buat file 3 file baru masing masing 1 GB dan amati perubahan pemakain disk di dashboard server monitoring
#  fallocate  -l  1G  testfile1.bin
#  fallocate  -l  1G  testfile2.bin
#  fallocate  -l  1G  testfile3.bin
