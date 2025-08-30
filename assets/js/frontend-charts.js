import { init, getInstanceByDom } from 'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.esm.min.js';

document.addEventListener('DOMContentLoaded', () => {
    // Get the current theme from localStorage or default to 'light'
    let currentTheme = localStorage.getItem('atmChartTheme') || atm_charts_data.atm_theme_mode;
    document.body.classList.add(`atm-chart-theme-${currentTheme}`);

    // Function to apply theme
    const applyTheme = (theme) => {
        document.body.classList.remove('atm-chart-theme-dark', 'atm-chart-theme-light');
        document.body.classList.add(`atm-chart-theme-${theme}`);
        localStorage.setItem('atmChartTheme', theme);
        currentTheme = theme;
        // Update all ECharts instances on the page
        updateAllChartsTheme();
    };

    // Function to update the theme of all ECharts instances
    const updateAllChartsTheme = () => {
        document.querySelectorAll('.atm-chart-wrapper').forEach(wrapper => {
            const chartInstance = getInstanceByDom(wrapper.querySelector('.atm-chart'));
            if (chartInstance) {
                chartInstance.dispose(); // Dispose old instance
                renderChart(wrapper.id.replace('atm-chart-wrapper-', '')); // Re-render with new theme
            }
        });
    };

    // Function to render a single chart
    const renderChart = (chartId) => {
        const wrapper = document.getElementById(`atm-chart-wrapper-${chartId}`);
        if (!wrapper) return;

        const chartDom = wrapper.querySelector('.atm-chart');
        if (!chartDom) return;

        // Initialize ECharts with the current theme
        const chartInstance = init(chartDom, currentTheme); // No "echarts."

        // Fetch data and render chart
        fetch(`${atm_charts_data.chart_api_base}${chartId}`, {
            headers: {
                'X-WP-Nonce': atm_charts_data.nonce,
            },
        })
        .then(response => response.json())
        .then(data => {
    const option = data;

    // Get CSS variable values for the current theme
    const rootStyles = getComputedStyle(document.body);
    const textColor = rootStyles.getPropertyValue('--atm-chart-text-color').trim();
    const gridLineColor = rootStyles.getPropertyValue('--atm-chart-grid-line-color').trim();
    const axisLabelColor = rootStyles.getPropertyValue('--atm-chart-axis-label-color').trim();
    const legendColor = rootStyles.getPropertyValue('--atm-chart-legend-color').trim();
    const dataZoomHandleColor = rootStyles.getPropertyValue('--atm-chart-data-zoom-handle-color').trim();
    const dataZoomMaskColor = rootStyles.getPropertyValue('--atm-chart-data-zoom-mask-color').trim();

    // Apply common default styles
    option.backgroundColor = 'transparent'; // Ensure background is transparent

    // Set chart title style
    if (option.title) {
        option.title.textStyle = { color: textColor };
    }

    // Set grid line colors
    if (option.xAxis && Array.isArray(option.xAxis)) {
        option.xAxis.forEach(axis => {
            axis.axisLine = { lineStyle: { color: gridLineColor } };
            axis.axisLabel = { color: axisLabelColor };
            axis.splitLine = { lineStyle: { color: gridLineColor } };
        });
    } else if (option.xAxis) { // For a single xAxis config
        option.xAxis.axisLine = { lineStyle: { color: gridLineColor } };
        option.xAxis.axisLabel = { color: axisLabelColor };
        option.xAxis.splitLine = { lineStyle: { color: gridLineColor } };
    }

    if (option.yAxis && Array.isArray(option.yAxis)) {
        option.yAxis.forEach(axis => {
            axis.axisLine = { lineStyle: { color: gridLineColor } };
            axis.axisLabel = { color: axisLabelColor };
            axis.splitLine = { lineStyle: { color: gridLineColor } };
        });
    } else if (option.yAxis) { // For a single yAxis config
        option.yAxis.axisLine = { lineStyle: { color: gridLineColor } };
        option.yAxis.axisLabel = { color: axisLabelColor };
        option.yAxis.splitLine = { lineStyle: { color: gridLineColor } };
    }

    // Set legend text color
    if (option.legend) {
        option.legend.textStyle = { color: legendColor };
    }


    // Set DataZoom styles
    if (option.dataZoom) {
        if (Array.isArray(option.dataZoom)) {
            option.dataZoom.forEach(dz => {
                dz.handleStyle = { color: dataZoomHandleColor };
                dz.fillerColor = dataZoomMaskColor;
            });
        } else {
            option.dataZoom.handleStyle = { color: dataZoomHandleColor };
            option.dataZoom.fillerColor = dataZoomMaskColor;
        }
    }
    
    if (option.radar) {
        // This handles single or multiple radar configs
        const radars = Array.isArray(option.radar) ? option.radar : [option.radar];
        radars.forEach(rad => {
            if (rad.axisName) {
                rad.axisName.color = axisLabelColor;
            }
            if (rad.splitLine) {
                rad.splitLine.lineStyle = { color: gridLineColor };
            }
            if (rad.splitArea && rad.splitArea.areaStyle) {
                // Optional: Style the alternating background bands
                rad.splitArea.areaStyle.color = [
                    'rgba(250, 250, 250, 0.05)',
                    'rgba(200, 200, 200, 0.05)'
                ];
            }
        });
    }

    chartInstance.setOption(option);
    // Adjust chart size dynamically
    setTimeout(() => chartInstance.resize(), 100); 
})

        // Make chart responsive
        window.addEventListener('resize', () => chartInstance.resize());
    };

    // Find all chart wrappers and render them
    document.querySelectorAll('.atm-chart-wrapper').forEach(wrapper => {
        const chartId = wrapper.id.replace('atm-chart-wrapper-', '');
        renderChart(chartId);

        // Add theme toggle button
        const toggleButton = document.createElement('button');
        toggleButton.classList.add('atm-theme-toggle');
        toggleButton.innerHTML = `<span class="dashicons dashicons-lightbulb"></span>`;
        toggleButton.title = `Switch to ${currentTheme === 'dark' ? 'light' : 'dark'} mode`;
        wrapper.prepend(toggleButton); // Add to the top of the wrapper

        toggleButton.addEventListener('click', () => {
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            toggleButton.title = `Switch to ${newTheme === 'dark' ? 'light' : 'dark'} mode`;
        });
    });
});