const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initProfit = () => {
    const chartElement = document.getElementById('profitChart');
    const data = readPayload('rider-profit-data');
    if (!chartElement || !data || !window.Chart) return;

    new window.Chart(chartElement.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Deliveries',
                data: data.values || [],
                backgroundColor: 'rgba(78, 115, 223, 0.5)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                },
            },
        },
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfit, { once: true });
} else {
    initProfit();
}

export { initProfit };
