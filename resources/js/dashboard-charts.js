import Chart from 'chart.js/auto';

function parseChartPayload() {
    const el = document.getElementById('dashboard-chart-data');
    if (!el || !el.textContent) {
        return null;
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

function sumNumeric(values) {
    return values.reduce((acc, v) => acc + (parseFloat(v) || 0), 0);
}

function showEmpty(canvas, message) {
    const wrap = canvas.closest('.chart-wrap');
    if (!wrap) {
        return;
    }
    let note = wrap.querySelector('.chart-empty-note');
    if (!note) {
        note = document.createElement('div');
        note.className = 'chart-empty-note text-muted small text-center py-4';
        wrap.appendChild(note);
    }
    note.textContent = message;
    canvas.style.display = 'none';
}

function renderDoughnut(data) {
    const canvas = document.getElementById('chartMovementDoughnut');
    if (!canvas || !data?.doughnut) {
        return;
    }

    const values = [
        data.doughnut.receive,
        data.doughnut.issue,
        data.doughnut.transfer,
        data.doughnut.adjustment,
    ];

    if (sumNumeric(values) <= 0) {
        showEmpty(canvas, 'No movement summary data for today. Use Refresh Today Summary to populate.');
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Receive', 'Issue', 'Transfer', 'Adjustment'],
            datasets: [{
                data: values.map((v) => parseFloat(v) || 0),
                backgroundColor: ['#198754', '#fd7e14', '#0dcaf0', '#6c757d'],
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    });
}

function renderTopGoods(data) {
    const canvas = document.getElementById('chartTopGoods');
    if (!canvas || !data?.top_goods) {
        return;
    }

    const items = data.top_goods;
    if (!items.length || sumNumeric(items.map((i) => i.gross_movement_qty)) <= 0) {
        showEmpty(canvas, 'No gross movement by goods for today.');
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: items.map((i) => i.sku || i.goods_name || `#${i.goods_id}`),
            datasets: [{
                label: 'Gross Movement Qty',
                data: items.map((i) => parseFloat(i.gross_movement_qty) || 0),
                backgroundColor: '#0d6efd',
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } },
        },
    });
}

function renderByWarehouse(data) {
    const canvas = document.getElementById('chartWarehouseMovement');
    if (!canvas || !data?.by_warehouse) {
        return;
    }

    const items = data.by_warehouse;
    if (!items.length || sumNumeric(items.map((i) => i.gross_movement_qty)) <= 0) {
        showEmpty(canvas, 'No gross movement by warehouse for today.');
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: items.map((i) => i.warehouse_code || i.warehouse_name || `#${i.warehouse_id}`),
            datasets: [{
                label: 'Gross Movement Qty',
                data: items.map((i) => parseFloat(i.gross_movement_qty) || 0),
                backgroundColor: '#6610f2',
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const data = parseChartPayload();
    if (!data) {
        return;
    }
    renderDoughnut(data);
    renderTopGoods(data);
    renderByWarehouse(data);
});
