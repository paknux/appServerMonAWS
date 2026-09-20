<?php
// config.php
define('DB_PATH', __DIR__ . '/data/monitor.sqlite');
define('NET_IFACE', 'auto'); // 'auto' = deteksi otomatis interface default

function get_db() {
    $isNew = !file_exists(DB_PATH);
    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(3000);
    if ($isNew) {
        $db->exec("CREATE TABLE metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ts INTEGER NOT NULL,
            cpu_percent REAL,
            mem_percent REAL,
            mem_used_mb INTEGER,
            mem_total_mb INTEGER,
            disk_percent REAL,
            disk_used_gb REAL,
            disk_total_gb REAL,
            load1 REAL,
            load5 REAL,
            load15 REAL,
            net_rx_kb REAL,
            net_tx_kb REAL,
            temp_c REAL
        )");
        $db->exec("CREATE INDEX idx_ts ON metrics(ts)");
    }
    return $db;
}

function detect_iface() {
    $out = shell_exec("ip route 2>/dev/null | awk '/default/ {print \$5; exit}'");
    $iface = trim($out ?? '');
    return $iface ?: 'eth0';
}
