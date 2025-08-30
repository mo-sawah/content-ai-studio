document.addEventListener('DOMContentLoaded', function () {
    const chartContainers = document.querySelectorAll('.atm-chart-container');
    
    if (chartContainers.length === 0 || typeof echarts === 'undefined') {
        return;
    }

    chartContainers.forEach(container => {
        const chartId = container.getAttribute('data-chart-id');
        if (!chartId) return;

        const loadingSpinner = document.createElement('div');
        loadingSpinner.className = 'atm-chart-loading';
        container.appendChild(loadingSpinner);

        // Fetch chart configuration from our REST API endpoint
        wp.apiFetch({ path: `/atm/v1/charts/${chartId}` })
            .then(chartConfig => {
                container.removeChild(loadingSpinner);
                const chartInstance = echarts.init(container);
                chartInstance.setOption(chartConfig);

                // Make chart responsive
                window.addEventListener('resize', () => {
                    chartInstance.resize();
                });
            })
            .catch(error => {
                container.removeChild(loadingSpinner);
                console.error('Error fetching chart config:', error);
                container.innerHTML = `<p style="color: red;">Error: Could not load chart data.</p>`;
            });
    });
});