const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initDashboard = () => {
    const chartElement = document.getElementById('shipmentChart');
    const data = readPayload('logistic-dashboard-data');
    if (!chartElement || !data || !window.Chart) return;

    new window.Chart(chartElement.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Delivered', 'In Transit', 'Pending', 'Delayed', 'Cancelled'],
            datasets: [{
                data: [
                    data.delivered || 0,
                    data.inTransit || 0,
                    data.pending || 0,
                    data.delayed || 0,
                    data.cancelled || 0,
                ],
                backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#F97316', '#EF4444'],
                borderWidth: 0,
                cutout: '70%',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const label = context.label || '';
                            return context.parsed !== undefined && context.parsed !== null
                                ? `${label}: ${context.parsed}`
                                : label;
                        },
                    },
                },
            },
            animation: { duration: 0 },
        },
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard, { once: true });
} else {
    initDashboard();
}

export { initDashboard };
