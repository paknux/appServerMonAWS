<?php
// api.php
require __DIR__ . '/config.php';
require __DIR__ . '/aws_meta.php';
header('Content-Type: application/json');

$db = get_db();
$action = $_GET['action'] ?? 'latest';

if ($action === 'latest') {
    $res = $db->query("SELECT * FROM metrics ORDER BY ts DESC LIMIT 1");
    $row = $res->fetchArray(SQLITE3_ASSOC) ?: null;

    // Uptime
    $uptimeRaw = (float)explode(' ', trim(file_get_contents('/proc/uptime')))[0];
    $days = floor($uptimeRaw / 86400);
    $hours = floor(($uptimeRaw % 86400) / 3600);
    $mins = floor(($uptimeRaw % 3600) / 60);
    $uptimeStr = "{$days}h {$hours}j {$mins}m";

    // Hostname & kernel
    $hostname = trim(shell_exec('hostname') ?? '');
    $kernel = trim(shell_exec('uname -r') ?? '');

    // Jumlah proses
    $procCount = (int)trim(shell_exec("ps -e --no-headers | wc -l") ?? 0);

    echo json_encode([
        'metric' => $row,
        'uptime' => $uptimeStr,
        'hostname' => $hostname,
        'kernel' => $kernel,
        'process_count' => $procCount,
    ]);
    exit;
}

if ($action === 'history') {
    $range = $_GET['range'] ?? '1h';
    $seconds = match ($range) {
        '1h' => 3600,
        '6h' => 21600,
        '24h' => 86400,
        '7d' => 604800,
        default => 3600,
    };
    $since = time() - $seconds;
    $res = $db->query("SELECT * FROM metrics WHERE ts >= $since ORDER BY ts ASC");
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $r;
    }
    echo json_encode($rows);
    exit;
}

if ($action === 'disks') {
    // Info SMART tiap disk fisik (butuh smartmontools + sudoers rule dari install.sh)
    $disks = [];
    $lsblk = shell_exec("lsblk -d -n -o NAME,TYPE 2>/dev/null");
    foreach (explode("\n", trim($lsblk ?? '')) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 2 || $parts[1] !== 'disk') continue;
        $dev = '/dev/' . $parts[0];
        $smart = shell_exec("sudo smartctl -H $dev 2>/dev/null");
        $healthy = $smart && stripos($smart, 'PASSED') !== false;
        $disks[] = ['device' => $dev, 'healthy' => $healthy];
    }
    echo json_encode($disks);
    exit;
}

if ($action === 'df') {
    // Semua mount point (bukan cuma root)
    $out = shell_exec("df -h --output=target,size,used,avail,pcent -x tmpfs -x devtmpfs -x squashfs 2>/dev/null");
    $lines = explode("\n", trim($out ?? ''));
    array_shift($lines); // header
    $mounts = [];
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 5) continue;
        $mounts[] = [
            'target' => $parts[0],
            'size' => $parts[1],
            'used' => $parts[2],
            'avail' => $parts[3],
            'percent' => $parts[4],
        ];
    }
    echo json_encode($mounts);
    exit;
}

if ($action === 'users') {
    // User yang sedang login (who) & histori login terakhir (last)
    $who = shell_exec("who 2>/dev/null");
    $whoLines = array_filter(explode("\n", trim($who ?? '')));
    $activeUsers = [];
    foreach ($whoLines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        $activeUsers[] = [
            'user' => $parts[0] ?? '',
            'tty' => $parts[1] ?? '',
            'from' => $parts[3] ?? '-',
            'since' => trim(($parts[2] ?? '') . ' ' . ($parts[3] ?? '')),
        ];
    }

    $last = shell_exec("last -n 10 -F 2>/dev/null | grep -v '^$' | grep -v 'wtmp begins'");
    $lastLines = array_filter(explode("\n", trim($last ?? '')));

    // Daftar semua user sistem dengan login shell interaktif (bukan service account)
    $passwd = file('/etc/passwd');
    $systemUsers = [];
    foreach ($passwd as $line) {
        $f = explode(':', trim($line));
        if (count($f) < 7) continue;
        $shell = $f[6];
        if (in_array(basename($shell), ['bash', 'sh', 'zsh'])) {
            $systemUsers[] = ['username' => $f[0], 'uid' => $f[2], 'home' => $f[5], 'shell' => $shell];
        }
    }

    // Sudoer / admin group
    $sudoers = trim(shell_exec("getent group sudo | cut -d: -f4") ?? '');

    echo json_encode([
        'active_sessions' => $activeUsers,
        'last_logins' => array_slice($lastLines, 0, 10),
        'system_users' => $systemUsers,
        'sudo_members' => $sudoers ? explode(',', $sudoers) : [],
    ]);
    exit;
}

if ($action === 'aws') {
    echo json_encode(get_aws_info());
    exit;
}

echo json_encode(['error' => 'unknown action']);
