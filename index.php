<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server Monitor</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="header">
    <div>
        <h1 id="hostname">Server Monitor</h1>
        <div class="sub" id="subinfo">Memuat...</div>
    </div>
    <div class="range-picker">
        <button data-range="1h" class="active">1 Jam</button>
        <button data-range="6h">6 Jam</button>
        <button data-range="24h">24 Jam</button>
        <button data-range="7d">7 Hari</button>
    </div>
</div>

<div class="stat-grid" id="statGrid">
    <div class="stat-card">
        <div class="label">CPU</div>
        <div class="value" id="cpuVal">-</div>
        <div class="progress-bar"><div class="fill" id="cpuBar" style="background:var(--accent)"></div></div>
    </div>
    <div class="stat-card">
        <div class="label">Memory</div>
        <div class="value" id="memVal">-</div>
        <div class="progress-bar"><div class="fill" id="memBar" style="background:var(--purple)"></div></div>
    </div>
    <div class="stat-card">
        <div class="label">Disk (/)</div>
        <div class="value" id="diskVal">-</div>
        <div class="progress-bar"><div class="fill" id="diskBar" style="background:var(--yellow)"></div></div>
    </div>
    <div class="stat-card">
        <div class="label">Load Average</div>
        <div class="value" id="loadVal" style="font-size:18px">-</div>
    </div>
    <div class="stat-card">
        <div class="label">Suhu CPU</div>
        <div class="value" id="tempVal">-</div>
    </div>
    <div class="stat-card">
        <div class="label">Uptime</div>
        <div class="value" id="uptimeVal" style="font-size:16px">-</div>
    </div>
</div>

<div class="info-grid" style="margin-bottom:24px" id="awsGrid">
    <div class="info-card" style="grid-column: span 2">
        <h3>🖥️ AWS Instance Info (EC2 Metadata)</h3>
        <table id="awsTable"><tbody><tr><td>Memuat data AWS...</td></tr></tbody></table>
    </div>
    <div class="info-card">
        <h3>👤 Akun Pengguna Aktif</h3>
        <table id="activeUsersTable"><thead><tr><th>User</th><th>TTY</th><th>Dari</th></tr></thead><tbody></tbody></table>
    </div>
</div>

<div class="info-grid" style="margin-bottom:24px">
    <div class="info-card">
        <h3>📜 Histori Login Terakhir</h3>
        <table id="lastLoginTable"><tbody></tbody></table>
    </div>
    <div class="info-card">
        <h3>👥 Semua User Sistem</h3>
        <table id="systemUsersTable"><thead><tr><th>Username</th><th>UID</th><th>Home</th></tr></thead><tbody></tbody></table>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h3>CPU Usage (%)</h3>
        <canvas id="chartCpu"></canvas>
    </div>
    <div class="chart-card">
        <h3>Memory Usage (%)</h3>
        <canvas id="chartMem"></canvas>
    </div>
    <div class="chart-card">
        <h3>Network Traffic (KB, kumulatif)</h3>
        <canvas id="chartNet"></canvas>
    </div>
    <div class="chart-card">
        <h3>Load Average (1 menit)</h3>
        <canvas id="chartLoad"></canvas>
    </div>
</div>

<div class="info-grid">
    <div class="info-card">
        <h3>Disk & Partisi</h3>
        <table id="dfTable"><thead><tr><th>Mount</th><th>Size</th><th>Used</th><th>Use%</th></tr></thead><tbody></tbody></table>
    </div>
    <div class="info-card">
        <h3>Kesehatan Disk (SMART)</h3>
        <table id="smartTable"><thead><tr><th>Device</th><th>Status</th></tr></thead><tbody></tbody></table>
    </div>
    <div class="info-card">
        <h3>Info Sistem</h3>
        <table>
            <tr><td>Kernel</td><td id="kernelVal">-</td></tr>
            <tr><td>Jumlah Proses</td><td id="procVal">-</td></tr>
        </table>
    </div>
</div>

<script src="charts.js"></script>
<script>
let currentRange = '1h';

function pctColor(p) {
    if (p < 60) return 'green';
    if (p < 85) return 'yellow';
    return 'red';
}

async function loadLatest() {
    const res = await fetch('api.php?action=latest');
    const data = await res.json();
    const m = data.metric;

    document.getElementById('hostname').textContent = data.hostname || 'Server Monitor';
    document.getElementById('subinfo').textContent =
        `${data.process_count} proses berjalan · diperbarui ${new Date().toLocaleTimeString('id-ID')}`;
    document.getElementById('kernelVal').textContent = data.kernel;
    document.getElementById('procVal').textContent = data.process_count;
    document.getElementById('uptimeVal').textContent = data.uptime;

    if (!m) return;

    const cpuCls = pctColor(m.cpu_percent);
    document.getElementById('cpuVal').textContent = m.cpu_percent.toFixed(1) + '%';
    document.getElementById('cpuVal').className = 'value ' + cpuCls;
    document.getElementById('cpuBar').style.width = m.cpu_percent + '%';

    const memCls = pctColor(m.mem_percent);
    document.getElementById('memVal').textContent = m.mem_percent.toFixed(1) + '%';
    document.getElementById('memVal').className = 'value ' + memCls;
    document.getElementById('memBar').style.width = m.mem_percent + '%';
    document.getElementById('memBar').title = `${m.mem_used_mb}MB / ${m.mem_total_mb}MB`;

    const diskCls = pctColor(m.disk_percent);
    document.getElementById('diskVal').textContent = m.disk_percent.toFixed(1) + '%';
    document.getElementById('diskVal').className = 'value ' + diskCls;
    document.getElementById('diskBar').style.width = m.disk_percent + '%';

    document.getElementById('loadVal').textContent = `${m.load1.toFixed(2)} / ${m.load5.toFixed(2)} / ${m.load15.toFixed(2)}`;
    document.getElementById('tempVal').textContent = m.temp_c ? m.temp_c.toFixed(1) + '°C' : 'N/A';
}

async function loadHistory() {
    const res = await fetch('api.php?action=history&range=' + currentRange);
    const rows = await res.json();

    drawLineChart(document.getElementById('chartCpu'), {
        data: rows.map(r => ({ ts: r.ts, value: r.cpu_percent }))
    }, { color: '#58a6ff', unit: '%', maxHint: 100 });

    drawLineChart(document.getElementById('chartMem'), {
        data: rows.map(r => ({ ts: r.ts, value: r.mem_percent }))
    }, { color: '#bc8cff', unit: '%', maxHint: 100 });

    drawLineChart(document.getElementById('chartNet'), {
        data: rows.map(r => ({ ts: r.ts, value: r.net_rx_kb }))
    }, { color: '#3fb950', unit: 'KB' });

    drawLineChart(document.getElementById('chartLoad'), {
        data: rows.map(r => ({ ts: r.ts, value: r.load1 }))
    }, { color: '#d29922', unit: '' });
}

async function loadDf() {
    const res = await fetch('api.php?action=df');
    const rows = await res.json();
    const tbody = document.querySelector('#dfTable tbody');
    tbody.innerHTML = rows.map(r =>
        `<tr><td>${r.target}</td><td>${r.size}</td><td>${r.used}</td><td>${r.percent}</td></tr>`
    ).join('');
}

async function loadSmart() {
    const res = await fetch('api.php?action=disks');
    const rows = await res.json();
    const tbody = document.querySelector('#smartTable tbody');
    tbody.innerHTML = rows.map(r =>
        `<tr><td>${r.device}</td><td><span class="badge ${r.healthy ? 'ok' : 'fail'}">${r.healthy ? 'OK' : 'CEK'}</span></td></tr>`
    ).join('') || '<tr><td colspan="2">Tidak ada data SMART</td></tr>';
}

async function loadAws() {
    const res = await fetch('api.php?action=aws');
    const d = await res.json();
    const tbody = document.querySelector('#awsTable tbody');

    if (!d.available) {
        tbody.innerHTML = `<tr><td colspan="2">⚠️ ${d.reason}</td></tr>`;
        return;
    }

    const rows = [
        ['Instance ID', d.instance_id],
        ['Instance Type', d.instance_type],
        ['AMI ID', d.ami_id],
        ['Hostname (internal)', d.hostname],
        ['Public IPv4', d.public_ipv4 || '(tidak ada / tidak dialokasikan)'],
        ['Private IPv4', d.local_ipv4],
        ['Availability Zone', d.availability_zone],
        ['Region', d.region],
        ['AWS Account ID', d.account_id],
        ['— Identitas IAM —', ''],
        ['IAM Role Name', d.iam_role],
        ['IAM Instance Profile ARN', d.iam_arn],
        ['Access Key ID (sementara)', d.iam_access_key_id],
        ['Kredensial Berlaku Sampai', d.iam_cred_expiration],
        ['Security Groups', d.security_groups],
        ['MAC Address', d.mac],
    ];
    tbody.innerHTML = rows.map(([k, v]) =>
        k.startsWith('—')
        ? `<tr><td colspan="2" style="color:var(--accent);padding-top:14px;font-weight:600">${k}</td></tr>`
        : `<tr><td style="color:var(--text-dim)">${k}</td><td>${v ?? '-'}</td></tr>`
    ).join('');
}

async function loadUsers() {
    const res = await fetch('api.php?action=users');
    const d = await res.json();

    document.querySelector('#activeUsersTable tbody').innerHTML =
        (d.active_sessions || []).map(u => `<tr><td>${u.user}</td><td>${u.tty}</td><td>${u.from}</td></tr>`).join('')
        || '<tr><td colspan="3">Tidak ada sesi aktif terdeteksi</td></tr>';

    document.querySelector('#lastLoginTable tbody').innerHTML =
        (d.last_logins || []).map(l => `<tr><td style="font-family:monospace;font-size:12px">${l}</td></tr>`).join('')
        || '<tr><td>Tidak ada histori</td></tr>';

    document.querySelector('#systemUsersTable tbody').innerHTML =
        (d.system_users || []).map(u => `<tr><td>${u.username}</td><td>${u.uid}</td><td>${u.home}</td></tr>`).join('');
}

document.querySelectorAll('.range-picker button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.range-picker button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentRange = btn.dataset.range;
        loadHistory();
    });
});

async function runCollector() {
    try {
        await fetch('collector.php', { cache: 'no-store' });
    } catch (e) {
        // diamkan saja, biar refresh berikutnya coba lagi
        console.warn('collector.php gagal dipanggil:', e);
    }
}

function refreshAll() {
    loadLatest();
    loadHistory();
}

// Jalankan collector duluan sebelum load pertama biar ada data segar,
// baru lanjut ambil data dashboard.
runCollector().then(refreshAll);
loadDf();
loadSmart();
loadAws();
loadUsers();

// Collector jalan tiap 5 detik dari dashboard (menggantikan cron)
setInterval(runCollector, 5000);

setInterval(refreshAll, 1000); // refresh tampilan tiap 15 detik
setInterval(() => { loadDf(); loadSmart(); }, 60000);
setInterval(() => { loadAws(); loadUsers(); }, 60000); // data ini jarang berubah
</script>
</body>
</html>