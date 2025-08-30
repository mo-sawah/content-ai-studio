import * as echarts from 'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js';

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
        document.querySelectorAll('.atm-chart-container').forEach(container => {
            const chartInstance = echarts.getInstanceByDom(container.querySelector('.atm-chart'));
            if (chartInstance) {
                chartInstance.dispose(); // Dispose old instance
                renderChart(container.id.replace('atm-chart-wrapper-', '')); // Re-render with new theme
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
        const chartInstance = echarts.init(chartDom, currentTheme); // Pass currentTheme here

        // Fetch data and render chart
        fetch(`${atm_charts_data.chart_api_base}${chartId}`, {
            headers: {
                'X-WP-Nonce': atm_charts_data.nonce,
            },
        })
        .then(response => response.json())
        .then(data => {
            const option = data; // <-- THIS IS THE FIX
            // Apply common default styles
            option.backgroundColor = 'transparent'; // Ensure background is transparent

            // Set chart title style for better dark/light mode compatibility
            if (option.title) {
                option.title.textStyle = {
                    color: currentTheme === 'dark' ? '#eee' : '#333'
                };
            }

            chartInstance.setOption(option);
            // Adjust chart size dynamically
            setTimeout(() => chartInstance.resize(), 100); 
        })
        .catch(error => {
            console.error('Error fetching chart data:', error);
            chartDom.innerHTML = `<p style="color: red;">Error loading chart: ${error.message}</p>`;
        });

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