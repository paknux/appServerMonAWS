<?php
// collector.php
// Dipanggil dari index.php via fetch() tiap beberapa detik untuk merekam kondisi server ke SQLite.
// Pakai file lock supaya kalau ada request yang lambat/nyangkut, tidak ada 2 proses jalan bersamaan.
require __DIR__ . '/config.php';

$lockFile = __DIR__ . '/data/.collector.lock';
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0775, true);
}
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    http_response_code(429);
    echo "SKIP masih ada proses collector yang berjalan\n";
    exit;
}

// Batasi frekuensi tulis ke DB minimal 3 detik antar insert, biar dashboard yang
// dibuka banyak tab / auto-refresh cepat tidak membanjiri SQLite dengan insert.
$lastRunFile = __DIR__ . '/data/.collector.lastrun';
$minIntervalSec = 3;
if (file_exists($lastRunFile) && (microtime(true) - (float)file_get_contents($lastRunFile)) < $minIntervalSec) {
    echo "SKIP throttle (interval minimum {$minIntervalSec}s)\n";
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit;
}
file_put_contents($lastRunFile, microtime(true));

function get_cpu_percent() {
    // Baca /proc/stat dua kali dengan jeda singkat untuk hitung persentase pemakaian
    $read = function () {
        $lines = file('/proc/stat');
        foreach ($lines as $line) {
            if (strpos($line, 'cpu ') === 0) {
                $parts = preg_split('/\s+/', trim($line));
                array_shift($parts);
                return array_map('intval', $parts);
            }
        }
        return [];
    };
    $a = $read();
    usleep(300000); // 0.3 detik
    $b = $read();

    $idleA = $a[3] + $a[4];
    $idleB = $b[3] + $b[4];
    $totalA = array_sum($a);
    $totalB = array_sum($b);

    $totalDiff = $totalB - $totalA;
    $idleDiff = $idleB - $idleA;

    if ($totalDiff <= 0) return 0;
    return round((1 - $idleDiff / $totalDiff) * 100, 1);
}

function get_mem() {
    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $ma);
    $totalMb = round(($mt[1] ?? 0) / 1024);
    $availMb = round(($ma[1] ?? 0) / 1024);
    $usedMb = $totalMb - $availMb;
    $percent = $totalMb > 0 ? round($usedMb / $totalMb * 100, 1) : 0;
    return [$percent, $usedMb, $totalMb];
}

function get_disk() {
    $out = shell_exec("df -B1 / | tail -1");
    $parts = preg_split('/\s+/', trim($out ?? ''));
    // Filesystem Size Used Avail Use% Mounted
    $total = isset($parts[1]) ? (float)$parts[1] : 0;
    $used = isset($parts[2]) ? (float)$parts[2] : 0;
    $percent = $total > 0 ? round($used / $total * 100, 1) : 0;
    return [$percent, round($used / 1073741824, 2), round($total / 1073741824, 2)];
}

function get_load() {
    $load = sys_getloadavg();
    return [$load[0] ?? 0, $load[1] ?? 0, $load[2] ?? 0];
}

function get_net($iface) {
    // Ambil byte rx/tx kumulatif dari /proc/net/dev, delta dihitung di frontend/API
    $lines = file('/proc/net/dev');
    foreach ($lines as $line) {
        if (strpos($line, $iface . ':') !== false) {
            $data = preg_split('/\s+/', trim(substr($line, strpos($line, ':') + 1)));
            $rxBytes = (float)$data[0];
            $txBytes = (float)$data[8];
            return [round($rxBytes / 1024, 2), round($txBytes / 1024, 2)];
        }
    }
    return [0, 0];
}

function get_temp() {
    $out = shell_exec("sensors 2>/dev/null");
    if ($out && preg_match('/(?:Package id 0|Tctl|temp1):\s*\+?([\d.]+)/', $out, $m)) {
        return (float)$m[1];
    }
    return null;
}

$iface = NET_IFACE === 'auto' ? detect_iface() : NET_IFACE;

$cpu = get_cpu_percent();
[$memPercent, $memUsed, $memTotal] = get_mem();
[$diskPercent, $diskUsed, $diskTotal] = get_disk();
[$load1, $load5, $load15] = get_load();
[$rxKb, $txKb] = get_net($iface);
$temp = get_temp();

$db = get_db();
$stmt = $db->prepare("INSERT INTO metrics
    (ts, cpu_percent, mem_percent, mem_used_mb, mem_total_mb, disk_percent, disk_used_gb, disk_total_gb, load1, load5, load15, net_rx_kb, net_tx_kb, temp_c)
    VALUES (:ts, :cpu, :memp, :memu, :memt, :diskp, :disku, :diskt, :l1, :l5, :l15, :rx, :tx, :temp)");

$stmt->bindValue(':ts', time(), SQLITE3_INTEGER);
$stmt->bindValue(':cpu', $cpu, SQLITE3_FLOAT);
$stmt->bindValue(':memp', $memPercent, SQLITE3_FLOAT);
$stmt->bindValue(':memu', $memUsed, SQLITE3_INTEGER);
$stmt->bindValue(':memt', $memTotal, SQLITE3_INTEGER);
$stmt->bindValue(':diskp', $diskPercent, SQLITE3_FLOAT);
$stmt->bindValue(':disku', $diskUsed, SQLITE3_FLOAT);
$stmt->bindValue(':diskt', $diskTotal, SQLITE3_FLOAT);
$stmt->bindValue(':l1', $load1, SQLITE3_FLOAT);
$stmt->bindValue(':l5', $load5, SQLITE3_FLOAT);
$stmt->bindValue(':l15', $load15, SQLITE3_FLOAT);
$stmt->bindValue(':rx', $rxKb, SQLITE3_FLOAT);
$stmt->bindValue(':tx', $txKb, SQLITE3_FLOAT);
$stmt->bindValue(':temp', $temp, SQLITE3_FLOAT);
$stmt->execute();

// Hapus data lebih dari 7 hari biar db tidak membengkak
$db->exec("DELETE FROM metrics WHERE ts < " . (time() - 7 * 86400));

echo "OK " . date('Y-m-d H:i:s') . " cpu={$cpu}% mem={$memPercent}% disk={$diskPercent}%\n";

flock($lockHandle, LOCK_UN);
fclose($lockHandle);