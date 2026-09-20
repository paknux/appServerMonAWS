// charts.js
// Mini charting engine — tanpa Chart.js/library apapun, murni Canvas API.

function drawLineChart(canvas, series, opts = {}) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const cssWidth = canvas.clientWidth || 400;
    const cssHeight = opts.height || 160;

    canvas.width = cssWidth * dpr;
    canvas.height = cssHeight * dpr;
    canvas.style.height = cssHeight + 'px';
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, cssWidth, cssHeight);

    const padding = { top: 10, right: 10, bottom: 20, left: 36 };
    const w = cssWidth - padding.left - padding.right;
    const h = cssHeight - padding.top - padding.bottom;

    if (!series.data || series.data.length === 0) {
        ctx.fillStyle = '#8b949e';
        ctx.font = '12px sans-serif';
        ctx.fillText('Belum ada data', padding.left, padding.top + h / 2);
        return;
    }

    const values = series.data.map(d => d.value);
    let maxVal = Math.max(...values, series.maxHint || 0);
    let minVal = Math.min(...values, 0);
    if (maxVal === minVal) maxVal = minVal + 1;

    // grid horizontal + label Y
    ctx.strokeStyle = '#21262d';
    ctx.fillStyle = '#8b949e';
    ctx.font = '10px sans-serif';
    ctx.lineWidth = 1;
    const gridLines = 4;
    for (let i = 0; i <= gridLines; i++) {
        const y = padding.top + (h / gridLines) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(padding.left + w, y);
        ctx.stroke();
        const val = maxVal - ((maxVal - minVal) / gridLines) * i;
        ctx.fillText((opts.unit === '%' ? val.toFixed(0) : val.toFixed(1)) + (opts.unit || ''), 2, y + 3);
    }

    // garis data
    const n = series.data.length;
    const stepX = n > 1 ? w / (n - 1) : 0;

    ctx.beginPath();
    series.data.forEach((d, i) => {
        const x = padding.left + i * stepX;
        const y = padding.top + h - ((d.value - minVal) / (maxVal - minVal)) * h;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.strokeStyle = opts.color || '#58a6ff';
    ctx.lineWidth = 2;
    ctx.stroke();

    // area fill gradient tipis
    const gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + h);
    gradient.addColorStop(0, (opts.color || '#58a6ff') + '55');
    gradient.addColorStop(1, (opts.color || '#58a6ff') + '00');
    ctx.lineTo(padding.left + (n - 1) * stepX, padding.top + h);
    ctx.lineTo(padding.left, padding.top + h);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    // label X (waktu awal & akhir)
    ctx.fillStyle = '#8b949e';
    ctx.font = '10px sans-serif';
    const first = new Date(series.data[0].ts * 1000);
    const last = new Date(series.data[n - 1].ts * 1000);
    const fmt = t => t.getHours().toString().padStart(2, '0') + ':' + t.getMinutes().toString().padStart(2, '0');
    ctx.fillText(fmt(first), padding.left, cssHeight - 4);
    ctx.textAlign = 'right';
    ctx.fillText(fmt(last), padding.left + w, cssHeight - 4);
    ctx.textAlign = 'left';
}

function drawGauge(canvas, percent, color) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const size = canvas.clientWidth || 80;
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    canvas.style.height = size + 'px';
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, size, size);

    const cx = size / 2, cy = size / 2, r = size / 2 - 6;
    ctx.beginPath();
    ctx.arc(cx, cy, r, -Math.PI / 2, Math.PI * 2 * (percent / 100) - Math.PI / 2);
    ctx.strokeStyle = color;
    ctx.lineWidth = 8;
    ctx.lineCap = 'round';
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(cx, cy, r, Math.PI * 2 * (percent / 100) - Math.PI / 2, Math.PI * 1.5, false);
    ctx.strokeStyle = '#21262d';
    ctx.lineWidth = 8;
    ctx.stroke();
}
