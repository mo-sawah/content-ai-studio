import { useState, useEffect } from "@wordpress/element";
import { ToggleControl, Button, Spinner } from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

const AutomationHumanizer = ({ campaignData, setCampaignData, isLoading }) => {
  const [isTestingProvider, setIsTestingProvider] = useState(false);
  const [testResult, setTestResult] = useState(null);

  // Available humanization providers
  const providerOptions = [
    { label: "StealthGPT (Recommended)", value: "stealthgpt" },
    { label: "OpenRouter (Claude/GPT-4)", value: "openrouter" },
    { label: "Undetectable.AI", value: "undetectable" },
    { label: "Combo (Multiple Passes)", value: "combo" },
  ];

  // OpenRouter models for humanization
  const openRouterModels = [
    {
      label: "Claude 3.5 Sonnet (Best Overall)",
      value: "anthropic/claude-3.5-sonnet",
    },
    { label: "GPT-4o (OpenAI Latest)", value: "openai/gpt-4o" },
    {
      label: "Claude 3 Opus (Most Intelligent)",
      value: "anthropic/claude-3-opus",
    },
    { label: "GPT-4 Turbo", value: "openai/gpt-4-turbo" },
    { label: "Gemini Pro 1.5", value: "google/gemini-pro-1.5" },
  ];

  // Humanization tones
  const toneOptions = [
    { label: "Conversational & Natural", value: "conversational" },
    { label: "Professional", value: "professional" },
    { label: "Casual & Friendly", value: "casual" },
    { label: "Journalistic", value: "journalistic" },
    { label: "Academic", value: "academic" },
    { label: "Creative & Engaging", value: "creative" },
  ];

  // Humanization modes
  const modeOptions = [
    { label: "Light (Fast & Economical)", value: "Low" },
    { label: "Standard (Balanced)", value: "Medium" },
    { label: "Aggressive (Best Results)", value: "High" },
  ];

  const humanizationSettings = campaignData.settings?.humanization || {};

  const updateHumanizationSetting = (key, value) => {
    setCampaignData({
      ...campaignData,
      settings: {
        ...campaignData.settings,
        humanization: {
          ...humanizationSettings,
          [key]: value,
        },
      },
    });
  };

  // Test humanization provider
  // Update the testProvider function in AutomationHumanizer.js
  const testProvider = async () => {
    console.log("Available nonces:", {
      atm_studio: window.atm_studio_data?.nonce,
      atm_automation: window.atm_automation_data?.nonce,
      automation_nonce: window.atm_automation_data?.automation_nonce,
    });
    setIsTestingProvider(true);
    setTestResult(null);

    try {
      const testContent =
        "This is a test message to validate the humanization provider and check if it's working correctly for automation campaigns.";

      console.log("Testing humanization with:", {
        provider: humanizationSettings.provider || "stealthgpt",
        tone: humanizationSettings.tone || "conversational",
        mode: humanizationSettings.mode || "Medium",
      });

      const response = await fetch(atm_automation_data.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "atm_test_automation_humanization",
          nonce: atm_automation_data.nonce, // Instead of .nonce
          content: testContent,
          provider: humanizationSettings.provider || "stealthgpt",
          tone: humanizationSettings.tone || "conversational",
          mode: humanizationSettings.mode || "Medium",
          model:
            humanizationSettings.openrouter_model ||
            "anthropic/claude-3.5-sonnet",
        }),
      });

      console.log("Response status:", response.status);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();
      console.log("Response data:", result);

      if (result.success) {
        setTestResult({
          success: true,
          humanizedContent: result.data.humanized_content,
          creditsUsed: result.data.credits_used,
          detectionScore: result.data.detection_score,
          providerUsed: result.data.provider_used,
        });
      } else {
        setTestResult({
          success: false,
          error: result.data || "Test failed",
        });
      }
    } catch (error) {
      console.error("Test humanization error:", error);
      setTestResult({
        success: false,
        error: error.message,
      });
    } finally {
      setIsTestingProvider(false);
    }
  };

  // Calculate estimated cost per article
  const calculateHumanizationCost = () => {
    const provider = humanizationSettings.provider || "stealthgpt";
    const mode = humanizationSettings.mode || "Medium";
    const businessMode = humanizationSettings.business_mode !== false;

    // Rough cost estimates based on average 800-word article
    const costEstimates = {
      stealthgpt: {
        Low: businessMode ? 0.24 : 0.08,
        Medium: businessMode ? 0.36 : 0.12,
        High: businessMode ? 0.48 : 0.16,
      },
      openrouter: {
        Low: 0.03,
        Medium: 0.05,
        High: 0.08,
      },
      undetectable: {
        Low: 0.12,
        Medium: 0.18,
        High: 0.24,
      },
      combo: {
        Low: 0.35,
        Medium: 0.53,
        High: 0.72,
      },
    };

    return costEstimates[provider]?.[mode] || 0.15;
  };

  return (
    <div className="atm-form-section">
      <h3>Content Humanization</h3>
      <p className="components-base-control__help">
        Automatically humanize generated content to bypass AI detection before
        publishing.
      </p>

      <div className="atm-humanization-toggle">
        <ToggleControl
          label="Enable content humanization"
          checked={humanizationSettings.enabled !== false}
          onChange={(value) => updateHumanizationSetting("enabled", value)}
          disabled={isLoading}
          help="Automatically process content through humanization before publishing"
        />
      </div>

      {humanizationSettings.enabled !== false && (
        <div className="atm-humanization-settings">
          <div className="atm-grid-2">
            <CustomDropdown
              label="Humanization Provider"
              text={
                providerOptions.find(
                  (opt) =>
                    opt.value ===
                    (humanizationSettings.provider || "stealthgpt")
                )?.label || "StealthGPT (Recommended)"
              }
              options={providerOptions}
              onChange={(option) =>
                updateHumanizationSetting("provider", option.value)
              }
              disabled={isLoading}
              help="Choose your preferred humanization service"
            />

            <CustomDropdown
              label="Humanization Mode"
              text={
                modeOptions.find(
                  (opt) => opt.value === (humanizationSettings.mode || "Medium")
                )?.label || "Standard (Balanced)"
              }
              options={modeOptions}
              onChange={(option) =>
                updateHumanizationSetting("mode", option.value)
              }
              disabled={isLoading}
              help="Higher modes provide better results but cost more"
            />
          </div>

          <div className="atm-grid-2">
            <CustomDropdown
              label="Writing Tone"
              text={
                toneOptions.find(
                  (opt) =>
                    opt.value ===
                    (humanizationSettings.tone || "conversational")
                )?.label || "Conversational & Natural"
              }
              options={toneOptions}
              onChange={(option) =>
                updateHumanizationSetting("tone", option.value)
              }
              disabled={isLoading}
              help="The desired tone for humanized content"
            />

            {(humanizationSettings.provider === "openrouter" ||
              !humanizationSettings.provider ||
              humanizationSettings.provider === "combo") && (
              <CustomDropdown
                label="OpenRouter Model"
                text={
                  openRouterModels.find(
                    (opt) =>
                      opt.value ===
                      (humanizationSettings.openrouter_model ||
                        "anthropic/claude-3.5-sonnet")
                  )?.label || "Claude 3.5 Sonnet (Best Overall)"
                }
                options={openRouterModels}
                onChange={(option) =>
                  updateHumanizationSetting("openrouter_model", option.value)
                }
                disabled={isLoading}
                help="Model used for OpenRouter humanization"
              />
            )}
          </div>

          <div className="atm-grid-2">
            <ToggleControl
              label="Business mode (StealthGPT)"
              checked={humanizationSettings.business_mode !== false}
              onChange={(value) =>
                updateHumanizationSetting("business_mode", value)
              }
              disabled={
                isLoading || humanizationSettings.provider === "openrouter"
              }
              help="Higher quality but 3x cost for StealthGPT"
            />

            <ToggleControl
              label="Preserve formatting"
              checked={humanizationSettings.preserve_formatting !== false}
              onChange={(value) =>
                updateHumanizationSetting("preserve_formatting", value)
              }
              disabled={isLoading}
              help="Maintain HTML structure and links"
            />
          </div>

          <div className="atm-grid-2">
            <ToggleControl
              label="Retry on high AI detection"
              checked={humanizationSettings.retry_on_detection || false}
              onChange={(value) =>
                updateHumanizationSetting("retry_on_detection", value)
              }
              disabled={isLoading}
              help="Automatically retry humanization if AI detection score is too high"
            />

            <ToggleControl
              label="Fallback to draft on failure"
              checked={humanizationSettings.fallback_to_draft !== false}
              onChange={(value) =>
                updateHumanizationSetting("fallback_to_draft", value)
              }
              disabled={isLoading}
              help="Save as draft instead of publishing if humanization fails"
            />
          </div>

          {/* Cost and Test Section */}
          <div className="atm-humanization-info">
            <div className="atm-cost-estimate">
              <div className="atm-cost-row">
                <span className="atm-cost-label">
                  Estimated cost per article:
                </span>
                <span className="atm-cost-value">
                  ${calculateHumanizationCost().toFixed(3)}
                </span>
              </div>
              <div className="atm-cost-note">
                * Based on ~800 words. Actual cost varies by content length.
              </div>
            </div>

            <div className="atm-test-section">
              <Button
                isSecondary
                onClick={testProvider}
                disabled={isTestingProvider || isLoading}
                className="atm-test-button"
              >
                {isTestingProvider ? (
                  <>
                    <Spinner /> Testing...
                  </>
                ) : (
                  "Test Humanization"
                )}
              </Button>

              {testResult && (
                <div
                  className={`atm-test-result ${testResult.success ? "success" : "error"}`}
                >
                  {testResult.success ? (
                    <div>
                      <div className="atm-test-success">
                        ✓ Humanization test successful!
                      </div>
                      <div className="atm-test-details">
                        <div>Credits used: {testResult.creditsUsed}</div>
                        {testResult.detectionScore && (
                          <div>AI detection: {testResult.detectionScore}%</div>
                        )}
                      </div>
                    </div>
                  ) : (
                    <div className="atm-test-error">
                      ✗ Test failed: {testResult.error}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AutomationHumanizer;
