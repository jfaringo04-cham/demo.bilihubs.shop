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

  // =========================================================
  // ORDER STATUS CHART
  // =========================================================

  const statusCtx = document.getElementById('statusChart');

  if (statusCtx && window.Chart) {
    const counts = data?.statusCounts ?? {};

    const statusConfig = [
      { key: 'placed', label: 'Placed', color: '#64748b' },
      { key: 'confirmed', label: 'Confirmed', color: '#3b82f6' },
      { key: 'preparing', label: 'Preparing', color: '#8b5cf6' },
      { key: 'ready_for_pickup', label: 'Ready for Pickup', color: '#f59e0b' },
      { key: 'delivered', label: 'Delivered', color: '#22c55e' },
      { key: 'cancelled', label: 'Cancelled', color: '#ef4444' },
    ];

    const activeStatuses = statusConfig.filter(
      (status) => Number(counts[status.key] ?? 0) > 0
    );

    new Chart(statusCtx, {
      type: 'doughnut',

      data: {
        labels: activeStatuses.map((status) => status.label),

        datasets: [
          {
            data: activeStatuses.map(
              (status) => Number(counts[status.key] ?? 0)
            ),

            backgroundColor: activeStatuses.map(
              (status) => status.color
            ),

            borderWidth: 0,
            hoverOffset: 8,
          },
        ],
      },

      options: {
  responsive: true,
  maintainAspectRatio: true,
  aspectRatio: 2,
cutout: '68%',
radius: '72%',

        plugins: {
          // Legend will be displayed by the Blade template instead.
          legend: {
            display: false,
          },

          tooltip: {
            callbacks: {
              label: function (context) {
                const value = context.raw ?? 0;

                return `${context.label}: ${value} order${
                  value !== 1 ? 's' : ''
                }`;
              },
            },
          },
        },
      },
    });
  }

  // =========================================================
  // REVENUE CHART
  // =========================================================

  const revenueCtx = document.getElementById('revenueChart');
  const periodSelect = document.getElementById('revenuePeriod');

  let revenueChart = null;

  function renderRevenueChart(revenueData) {
    if (!revenueCtx || !window.Chart) return;

    if (revenueChart) {
      revenueChart.destroy();
    }

    revenueChart = new Chart(revenueCtx, {
      type: 'line',

      data: {
        labels: Object.keys(revenueData),

        datasets: [
          {
            label: 'Revenue',
            data: Object.values(revenueData),

            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',

            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#8b5cf6',

            tension: 0.3,
            fill: true,
          },
        ],
      },

      options: {
        responsive: true,
        maintainAspectRatio: false,

        scales: {
          y: {
            beginAtZero: true,

            grid: {
              color: '#f1f5f9',
            },

            ticks: {
              callback: (value) =>
                '₱' + Number(value).toLocaleString('en-PH'),
            },
          },

          x: {
            grid: {
              display: false,
            },
          },
        },

        plugins: {
          legend: {
            display: false,
          },

          tooltip: {
            callbacks: {
              label: function (context) {
                const value = Number(context.raw ?? 0);

                return (
                  'Revenue: ₱' +
                  value.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                );
              },
            },
          },
        },
      },
    });
  }

  // =========================================================
  // FETCH REVENUE DATA
  // =========================================================

  async function fetchRevenueData(period) {
    try {
      const res = await fetch(
        `/seller/dashboard/revenue?period=${encodeURIComponent(period)}`,
        {
          headers: {
            Accept: 'application/json',
          },
        }
      );

      if (!res.ok) {
        throw new Error('Failed to fetch revenue data.');
      }

      return await res.json();
    } catch {
      return data?.revenueData ?? {};
    }
  }

  // =========================================================
  // INITIALIZE REVENUE CHART
  // =========================================================

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