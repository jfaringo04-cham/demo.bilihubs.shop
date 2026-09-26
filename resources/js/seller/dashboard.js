document.addEventListener('DOMContentLoaded', function () {
  function parseJsonElement(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent);
    } catch {
      return null;
    }
  }

  const data = parseJsonElement('seller-dashboard-data');

  const statusCtx = document.getElementById('statusChart');
  if (statusCtx && window.Chart) {
    const counts = data?.statusCounts ?? {
      delivered: parseInt(statusCtx.dataset.delivered ?? '0', 10),
      processing: parseInt(statusCtx.dataset.processing ?? '0', 10),
      pending: parseInt(statusCtx.dataset.pending ?? '0', 10),
      shipped: parseInt(statusCtx.dataset.shipped ?? '0', 10),
      cancelled: parseInt(statusCtx.dataset.cancelled ?? '0', 10),
    };

    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: ['Delivered', 'Processing', 'Pending', 'Shipped', 'Cancelled'],
        datasets: [{
          data: [counts.delivered, counts.processing, counts.pending, counts.shipped, counts.cancelled],
          backgroundColor: ['#22c55e', '#8b5cf6', '#f59e0b', '#3b82f6', '#ef4444'],
          borderWidth: 0,
          hoverOffset: 8,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false } },
      },
    });
  }

  const revenueCtx = document.getElementById('revenueChart');
  const periodSelect = document.getElementById('revenuePeriod');
  let revenueChart = null;

  function renderRevenueChart(revenueData) {
    if (!revenueCtx || !window.Chart) return;
    if (revenueChart) revenueChart.destroy();

    revenueChart = new Chart(revenueCtx, {
      type: 'line',
      data: {
        labels: Object.keys(revenueData),
        datasets: [{
          label: 'Revenue',
          data: Object.values(revenueData),
          borderColor: '#8b5cf6',
          backgroundColor: 'rgba(139, 92, 246, 0.1)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#8b5cf6',
          tension: 0.3,
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { callback: (value) => '₱' + value },
          },
          x: { grid: { display: false } },
        },
        plugins: { legend: { display: false } },
      },
    });
  }

  async function fetchRevenueData(period) {
    try {
      const res = await fetch(`/seller/dashboard/revenue?period=${encodeURIComponent(period)}`, {
        headers: { 'Accept': 'application/json' },
      });
      if (!res.ok) throw new Error('Failed to fetch');
      return await res.json();
    } catch {
      return data?.revenueData ?? {};
    }
  }

  if (revenueCtx) {
    const initialData = data?.revenueData ?? {};
    renderRevenueChart(initialData);

    if (periodSelect) {
      periodSelect.addEventListener('change', async function () {
        const period = this.value;
        const revenueData = await fetchRevenueData(period);
        renderRevenueChart(revenueData);
      });
    }
  }
});