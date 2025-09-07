import { useState, useEffect, useRef } from "@wordpress/element";
import { Button, TextareaControl, TextControl } from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";

// Load ECharts library dynamically
const loadECharts = () => {
  return new Promise((resolve, reject) => {
    if (window.echarts) {
      return resolve(window.echarts);
    }
    const script = document.createElement("script");
    script.src =
      "https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js";
    script.onload = () => resolve(window.echarts);
    script.onerror = reject;
    document.head.appendChild(script);
  });
};

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

function ChartGenerator({ setActiveView }) {
  const [prompt, setPrompt] = useState("");
  const [chartConfig, setChartConfig] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState(
    "Describe the chart you want to create."
  );

  const chartRef = useRef(null);

  // State for saving the chart
  const [chartTitle, setChartTitle] = useState("");
  const [chartId, setChartId] = useState(null);

  useEffect(() => {
    let chartInstance = null;
    let resizeObserver = null;

    if (chartRef.current && chartConfig) {
      loadECharts()
        .then((echarts) => {
          chartInstance = echarts.init(chartRef.current, "light");
          chartInstance.setOption(JSON.parse(chartConfig));

          resizeObserver = new ResizeObserver(() => {
            chartInstance?.resize();
          });
          resizeObserver.observe(chartRef.current);
        })
        .catch((err) => console.error("ECharts loading failed:", err));
    }

    return () => {
      resizeObserver?.disconnect();
      chartInstance?.dispose();
    };
  }, [chartConfig]);

  const handleGenerate = async () => {
    if (!prompt.trim()) {
      alert("Please enter a description for your chart.");
      return;
    }
    setIsLoading(true);
    setStatusMessage("Generating chart data and configuration...");
    setChartConfig(null);

    try {
      const response = await callAjax("generate_chart_from_ai", { prompt });
      if (response.success) {
        setChartConfig(response.data.chart_config);
        setStatusMessage("✅ Chart generated successfully!");
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSaveChart = async () => {
    if (!chartTitle.trim()) {
      alert("Please provide a title for your chart before saving.");
      return;
    }
    setIsLoading(true);
    setStatusMessage("Saving chart...");

    try {
      const response = await callAjax("save_atm_chart", {
        title: chartTitle,
        chart_config: chartConfig,
        chart_id: chartId,
      });

      if (response.success) {
        setChartId(response.data.chart_id);
        setStatusMessage(
          `✅ Chart saved successfully! Use the shortcode to embed it.`
        );
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
        <Button
          isPrimary
          onClick={handleGenerate}
          disabled={isLoading || !prompt.trim()}
        >
          {isLoading ? (
            <>
              <CustomSpinner /> Generating...
            </>
          ) : (
            "Generate Chart"
          )}
        </Button>

        {statusMessage && <p className="atm-status-message">{statusMessage}</p>}

        <div className="atm-chart-preview-container">
          <div
            ref={chartRef}
            style={{
              width: "100%",
              height: "400px",
              display: chartConfig ? "block" : "none",
            }}
          ></div>
        </div>

        {chartConfig && (
          <div className="atm-chart-actions">
            <TextControl
              label="Chart Title (for your reference)"
              value={chartTitle}
              onChange={setChartTitle}
              placeholder="e.g., Q3 Sales Report"
              disabled={isLoading}
            />
            <Button
              isPrimary
              onClick={handleSaveChart}
              disabled={isLoading || !chartTitle.trim()}
            >
              {isLoading ? (
                <>
                  <CustomSpinner /> {chartId ? "Updating..." : "Saving..."}{" "}
                </>
              ) : chartId ? (
                "Update Chart"
              ) : (
                "Save Chart"
              )}
            </Button>

            {chartId && (
              <div className="atm-shortcode-display">
                <p>Embed this chart anywhere with this shortcode:</p>
                <TextControl
                  value={`[atm_chart id="${chartId}"]`}
                  readOnly
                  onClick={(e) => e.target.select()}
                />
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export default ChartGenerator;
