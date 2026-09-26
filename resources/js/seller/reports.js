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

  const data = parseJsonElement('seller-reports-data');

  const ctx = document.getElementById('salesChart');
  if (ctx && window.Chart) {
    const labels = data?.labels ?? JSON.parse(ctx.dataset.labels ?? '[]');
    const chartData = data?.data ?? JSON.parse(ctx.dataset.data ?? '[]');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Sales',
          data: chartData,
          borderColor: '#8b5cf6',
          backgroundColor: 'rgba(139, 92, 246, 0.1)',
          fill: true,
          tension: 0.3,
        }],
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true },
        },
      },
    });
  }
});