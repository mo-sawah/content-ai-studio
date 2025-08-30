import { useState, useEffect, useRef } from '@wordpress/element'; // <-- useRef is the key
import { Button, TextareaControl, Spinner } from '@wordpress/components';

// Load ECharts library dynamically
const loadECharts = () => {
    return new Promise((resolve, reject) => {
        if (window.echarts) {
            return resolve(window.echarts);
        }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js';
        script.onload = () => resolve(window.echarts);
        script.onerror = reject;
        document.head.appendChild(script);
    });
};

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

function ChartGenerator({ setActiveView }) {
    const [prompt, setPrompt] = useState('');
    const [chartConfig, setChartConfig] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('Describe the chart you want to create.');
    
    // --- THIS IS THE FIX: Use useRef to create a stable reference to the DOM element ---
    const chartRef = useRef(null);

    useEffect(() => {
        let chartInstance = null;
        if (chartRef.current && chartConfig) {
            loadECharts().then(echarts => {
                // Initialize ECharts on the .current property of the ref
                chartInstance = echarts.init(chartRef.current, 'dark');
                chartInstance.setOption(JSON.parse(chartConfig));

                // Add a resize listener to make the chart responsive
                const resizeObserver = new ResizeObserver(() => {
                    chartInstance?.resize();
                });
                resizeObserver.observe(chartRef.current);
                
                // Cleanup when the component unmounts
                return () => {
                    resizeObserver.disconnect();
                    chartInstance?.dispose();
                };
            }).catch(err => console.error("ECharts loading failed:", err));
        }
    }, [chartConfig]);

    const handleGenerate = async () => {
        if (!prompt.trim()) {
            alert('Please enter a description for your chart.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Generating chart data and configuration...');
        setChartConfig(null);

        try {
            const response = await callAjax('generate_chart_from_ai', { prompt });
            if (response.success) {
                setChartConfig(response.data.chart_config);
                setStatusMessage('✅ Chart generated successfully!');
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>AI Chart Generator</h3>
            </div>
            <div className="atm-form-container">
                <TextareaControl
                    label="Chart Description"
                    help="Describe the chart you want to create. Be specific about the type and data."
                    value={prompt}
                    onChange={setPrompt}
                    placeholder="e.g., A bar chart showing monthly revenue for the last 6 months"
                    rows={4}
                    disabled={isLoading}
                />
                <Button isPrimary onClick={handleGenerate} disabled={isLoading || !prompt.trim()}>
                    {isLoading ? <Spinner /> : 'Generate Chart'}
                </Button>
                
                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}

                <div className="atm-chart-preview-container">
                    {/* The ref is now correctly assigned to the div */}
                    <div ref={chartRef} style={{ width: '100%', height: '400px', display: chartConfig ? 'block' : 'none' }}></div>
                </div>

                {chartConfig && (
                    <div className="atm-chart-actions">
                        <p>Once saved, you will get a shortcode to embed this chart.</p>
                        <Button isSecondary disabled>Save Chart (Coming Soon)</Button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default ChartGenerator;