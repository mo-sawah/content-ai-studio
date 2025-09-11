// src/components/HumanizeComponent.js - Updated with requested fixes
// - Advanced button removed; settings always visible
// - Three checkboxes converted to toggles and shown on one line
// - Labels in dark settings panel forced to white
// - Disabled/unclickable buttons no longer show spinners (spinner only on main CTA)

import { useState, useEffect } from "@wordpress/element";
import { ToggleControl } from "@wordpress/components";

function HumanizeComponent({ setActiveView }) {
  const [isLoading, setIsLoading] = useState(false);
  const [content, setContent] = useState("");
  const [humanizedContent, setHumanizedContent] = useState("");
  const [settings, setSettings] = useState({
    provider: "stealthgpt",
    tone: "conversational",
    mode: "High",
    businessMode: true,
    preserveFormatting: true,
    autoReplace: false,
    model: "anthropic/claude-3.5-sonnet", // For OpenRouter
  });
  const [stats, setStats] = useState({
    originalWords: 0,
    humanizedWords: 0,
    detectionScore: null,
    creditsUsed: 0,
    processingTime: 0,
  });
  const [progress, setProgress] = useState("");

  // Provider options
  const providers = {
    stealthgpt: "StealthGPT (Recommended)",
    openrouter: "OpenRouter (Claude/GPT-4)",
    undetectable: "Undetectable.AI",
    combo: "Combo (Multiple Passes)",
  };

  // OpenRouter models
  const openrouterModels = {
    "anthropic/claude-3.5-sonnet": "Claude 3.5 Sonnet (Best Overall)",
    "openai/gpt-4o": "GPT-4o (OpenAI Latest)",
    "anthropic/claude-3-opus": "Claude 3 Opus (Most Intelligent)",
    "openai/gpt-4-turbo": "GPT-4 Turbo",
    "google/gemini-pro-1.5": "Gemini Pro 1.5",
    "meta-llama/llama-3.1-405b-instruct": "Llama 3.1 405B",
    "mistralai/mistral-large": "Mistral Large",
    "cohere/command-r-plus": "Command R+",
  };

  // Tone options
  const toneOptions = {
    conversational: "Conversational & Natural",
    professional: "Professional",
    casual: "Casual & Friendly",
    academic: "Academic",
    journalistic: "Journalistic",
    creative: "Creative & Engaging",
    technical: "Technical",
    persuasive: "Persuasive",
    storytelling: "Storytelling",
  };

  // Load editor content on component mount
  useEffect(() => {
    loadEditorContent();
  }, []);

  const loadEditorContent = () => {
    try {
      if (window.wp && window.wp.data) {
        const editorContent = window.wp.data
          .select("core/editor")
          .getEditedPostContent();
        if (editorContent) {
          const plainText = stripHtml(editorContent);
          setContent(plainText);
          setStats((prev) => ({
            ...prev,
            originalWords: countWords(plainText),
          }));
        }
      } else if (window.tinymce && window.tinymce.activeEditor) {
        const editorContent = window.tinymce.activeEditor.getContent({
          format: "text",
        });
        setContent(editorContent);
        setStats((prev) => ({
          ...prev,
          originalWords: countWords(editorContent),
        }));
      } else {
        console.warn("No editor found. Content must be entered manually.");
      }
    } catch (error) {
      console.error("Error loading editor content:", error);
    }
  };

  const stripHtml = (html) => {
    const div = document.createElement("div");
    div.innerHTML = html;
    return div.textContent || div.innerText || "";
  };

  const countWords = (text) => {
    if (!text || typeof text !== "string") return 0;
    return text
      .trim()
      .split(/\s+/)
      .filter((word) => word.length > 0).length;
  };

  const handleHumanize = async () => {
    if (!content.trim()) {
      alert(
        "Please load content from the editor first or enter content manually."
      );
      return;
    }

    if (content.length < 50) {
      alert("Content must be at least 50 characters long for humanization.");
      return;
    }

    if (settings.provider === "combo" && stats.originalWords > 500) {
      if (
        !confirm(
          "Combo mode with large content will use many credits. Continue?"
        )
      ) {
        return;
      }
    }

    setIsLoading(true);
    setProgress("Initializing humanization...");

    try {
      const requestData = {
        action: "humanize_content",
        nonce: atm_ajax.nonce,
        content: content,
        provider: settings.provider,
        tone: settings.tone,
        mode: settings.mode,
        business_mode: settings.businessMode,
        preserve_formatting: settings.preserveFormatting,
      };

      // Add model for OpenRouter
      if (settings.provider === "openrouter") {
        requestData.model = settings.model;
      }

      setProgress(`Processing with ${providers[settings.provider]}...`);

      const response = await fetch(atm_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams(requestData),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (data.success) {
        setHumanizedContent(data.data.humanized_content);
        setStats((prev) => ({
          ...prev,
          humanizedWords: countWords(data.data.humanized_content),
          detectionScore: data.data.detection_score || null,
          creditsUsed: data.data.credits_used || 0,
          processingTime: data.data.processing_time || 0,
        }));

        const creditsText = data.data.credits_used
          ? ` (${data.data.credits_used} credits used)`
          : "";
        const timeText = data.data.processing_time
          ? ` in ${Math.round(data.data.processing_time / 1000)}s`
          : "";
        setProgress(
          `✅ Content successfully humanized${creditsText}${timeText}!`
        );

        if (settings.autoReplace) {
          // If auto-replace is enabled, optionally replace immediately
          await handleReplaceContent(true);
        }
      } else {
        throw new Error(data.data || "Humanization failed");
      }
    } catch (error) {
      console.error("Humanization error:", error);
      setProgress("");
      alert(`❌ Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      setTimeout(() => setProgress(""), 8000);
    }
  };

  const handleReplaceContent = async (silent = false) => {
    if (!humanizedContent) {
      if (!silent) alert("No humanized content available to replace.");
      return;
    }

    try {
      if (window.wp && window.wp.data) {
        const { dispatch } = window.wp.data;
        dispatch("core/editor").editPost({ content: humanizedContent });
      } else if (window.tinymce && window.tinymce.activeEditor) {
        window.tinymce.activeEditor.setContent(humanizedContent);
      } else {
        throw new Error("No editor found to replace content");
      }

      if (!silent) alert("✅ Content successfully replaced in editor!");

      if (settings.autoReplace) {
        setActiveView("hub");
      }
    } catch (error) {
      console.error("Content replacement error:", error);
      if (!silent)
        alert("❌ Failed to replace content in editor: " + error.message);
    }
  };

  const handleCopyContent = async () => {
    if (!humanizedContent) {
      alert("No humanized content to copy.");
      return;
    }

    try {
      await navigator.clipboard.writeText(humanizedContent);
      alert("📋 Humanized content copied to clipboard!");
    } catch (err) {
      console.error("Copy failed:", err);
      // Fallback for older browsers
      const textArea = document.createElement("textarea");
      textArea.value = humanizedContent;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand("copy");
      document.body.removeChild(textArea);
      alert("📋 Humanized content copied to clipboard!");
    }
  };

  const detectAI = async () => {
    if (!humanizedContent) {
      alert("No humanized content to check.");
      return;
    }

    setIsLoading(true);
    setProgress("Running AI detection analysis...");

    try {
      const response = await fetch(atm_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "check_ai_detection",
          nonce: atm_ajax.nonce,
          content: humanizedContent,
        }),
      });

      const data = await response.json();

      if (data.success) {
        setStats((prev) => ({
          ...prev,
          detectionScore: data.data.detection_score,
        }));
        setProgress(
          `🔍 Detection Score: ${data.data.detection_score}% AI detected - ${data.data.status}`
        );
      } else {
        throw new Error(data.data || "AI detection failed");
      }
    } catch (error) {
      console.error("AI detection error:", error);
      alert(`❌ Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      setTimeout(() => setProgress(""), 7000);
    }
  };

  const getProviderInfo = () => {
    switch (settings.provider) {
      case "stealthgpt":
        return {
          description:
            "Most effective for bypassing AI detection. Uses advanced algorithms specialized for undetectability.",
          cost: `${settings.businessMode ? "30" : "10"} credits per 100 words`,
          speed: "Fast (30-60 seconds)",
          features: [
            "Best AI detection bypass",
            "Business mode available",
            "Multiple tones",
          ],
        };
      case "openrouter":
        return {
          description:
            "Uses the most powerful AI models like Claude 3.5 Sonnet for natural, high-quality humanization.",
          cost: "5-15 credits per 100 words (varies by model)",
          speed: "Very Fast (10-30 seconds)",
          features: [
            "Most natural results",
            "Latest AI models",
            "Fast processing",
          ],
        };
      case "undetectable":
        return {
          description:
            "Specialized service focused specifically on making content undetectable by AI detection tools.",
          cost: "15 credits per 100 words",
          speed: "Moderate (60-120 seconds)",
          features: [
            "Detection-focused",
            "Reliable results",
            "Good alternative",
          ],
        };
      case "combo":
        return {
          description:
            "Uses multiple providers in sequence for maximum human-like quality and undetectability.",
          cost: "40-50 credits per 100 words",
          speed: "Slow (120-180 seconds)",
          features: ["Highest quality", "Multiple passes", "Best results"],
        };
      default:
        return { description: "", cost: "", speed: "", features: [] };
    }
  };

  const getEstimatedCost = () => {
    const words = stats.originalWords;
    const blocks = Math.ceil(words / 100);

    switch (settings.provider) {
      case "stealthgpt":
        return blocks * (settings.businessMode ? 30 : 10);
      case "openrouter":
        const modelCosts = {
          "anthropic/claude-3.5-sonnet": 8,
          "openai/gpt-4o": 6,
          "anthropic/claude-3-opus": 15,
          "openai/gpt-4-turbo": 10,
          "google/gemini-pro-1.5": 8,
          "meta-llama/llama-3.1-405b-instruct": 12,
          "mistralai/mistral-large": 10,
          "cohere/command-r-plus": 8,
        };
        return blocks * (modelCosts[settings.model] || 8);
      case "undetectable":
        return blocks * 15;
      case "combo":
        return blocks * 45;
      default:
        return 0;
    }
  };

  const providerInfo = getProviderInfo();

  return (
    <div className="atm-humanize-container">
      <div className="atm-humanize-header">
        <h2>🧠 Humanize AI Content</h2>
        <p>
          Transform AI-generated content into natural, human-like text that
          bypasses AI detection with advanced algorithms and multiple provider
          options.
        </p>
      </div>

      {/* Provider Selection */}
      <div className="atm-provider-panel">
        <h3>Choose Humanization Provider</h3>
        <div className="atm-provider-grid">
          {Object.entries(providers).map(([key, label]) => (
            <div
              key={key}
              className={`atm-provider-card ${
                settings.provider === key ? "active" : ""
              }`}
              onClick={() =>
                setSettings((prev) => ({ ...prev, provider: key }))
              }
            >
              <div className="provider-header">
                <input
                  type="radio"
                  name="provider"
                  value={key}
                  checked={settings.provider === key}
                  onChange={() =>
                    setSettings((prev) => ({ ...prev, provider: key }))
                  }
                />
                <label>{label}</label>
                {key === "stealthgpt" && (
                  <span className="recommended-badge">Recommended</span>
                )}
                {key === "combo" && (
                  <span
                    className="recommended-badge"
                    style={{
                      background:
                        "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)",
                    }}
                  >
                    Premium
                  </span>
                )}
              </div>
              <div className="provider-info">
                <p className="description">{providerInfo.description}</p>
                <div className="provider-stats">
                  <span className="cost">
                    💰{" "}
                    {key === settings.provider
                      ? providerInfo.cost
                      : getProviderInfo().cost}
                  </span>
                  <span className="speed">
                    ⚡{" "}
                    {key === settings.provider
                      ? providerInfo.speed
                      : getProviderInfo().speed}
                  </span>
                </div>
                {key === settings.provider && providerInfo.features && (
                  <div className="provider-features">
                    {providerInfo.features.map((feature, index) => (
                      <span key={index} className="feature-tag">
                        {feature}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Settings Panel (always open; Advanced button removed) */}
      <div className="atm-settings-panel">
        <div className="settings-header">
          <h3 style={{ color: "#fff" }}>Humanization Settings</h3>
        </div>

        <div className="atm-settings-grid">
          <div className="atm-setting-group">
            <label style={{ color: "#fff" }}>Writing Tone:</label>
            <select
              value={settings.tone}
              onChange={(e) =>
                setSettings((prev) => ({ ...prev, tone: e.target.value }))
              }
              disabled={isLoading}
            >
              {Object.entries(toneOptions).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
            <p className="setting-description">
              Choose the writing style that best fits your content&apos;s
              purpose.
            </p>
          </div>

          {settings.provider === "openrouter" && (
            <div className="atm-setting-group">
              <label style={{ color: "#fff" }}>AI Model:</label>
              <select
                value={settings.model}
                onChange={(e) =>
                  setSettings((prev) => ({ ...prev, model: e.target.value }))
                }
                disabled={isLoading}
              >
                {Object.entries(openrouterModels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
              <p className="setting-description">
                Select the AI model for humanization. Claude 3.5 Sonnet provides
                the best balance.
              </p>
            </div>
          )}

          {(settings.provider === "stealthgpt" ||
            settings.provider === "combo") && (
            <>
              <div className="atm-setting-group">
                <label style={{ color: "#fff" }}>Quality Mode:</label>
                <select
                  value={settings.mode}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, mode: e.target.value }))
                  }
                  disabled={isLoading}
                >
                  <option value="High">
                    High Quality (slower, better results)
                  </option>
                  <option value="Medium">
                    Balanced (good speed and quality)
                  </option>
                  <option value="Low">Fast (quick results)</option>
                </select>
                <p className="setting-description">
                  Higher quality takes longer but produces better humanization.
                </p>
              </div>
            </>
          )}
        </div>

        {/* Three toggles in one row */}
        <div
          className="atm-advanced-toggles"
          style={{
            display: "flex",
            gap: "16px",
            alignItems: "center",
            marginTop: "8px",
            flexWrap: "nowrap",
          }}
        >
          {(settings.provider === "stealthgpt" ||
            settings.provider === "combo") && (
            <ToggleControl
              className="atm-toggle"
              label={
                <span style={{ color: "#fff" }}>
                  Use Enhanced Model (10x more powerful, 3x cost)
                </span>
              }
              checked={!!settings.businessMode}
              onChange={(value) =>
                setSettings((prev) => ({ ...prev, businessMode: !!value }))
              }
              disabled={isLoading}
            />
          )}

          <ToggleControl
            className="atm-toggle"
            label={
              <span style={{ color: "#fff" }}>Preserve HTML Formatting</span>
            }
            checked={!!settings.preserveFormatting}
            onChange={(value) =>
              setSettings((prev) => ({ ...prev, preserveFormatting: !!value }))
            }
            disabled={isLoading}
          />

          <ToggleControl
            className="atm-toggle"
            label={
              <span style={{ color: "#fff" }}>
                Auto-replace in editor after humanization
              </span>
            }
            checked={!!settings.autoReplace}
            onChange={(value) =>
              setSettings((prev) => ({ ...prev, autoReplace: !!value }))
            }
            disabled={isLoading}
          />
        </div>
      </div>

      {/* Content Area */}
      <div className="atm-content-area">
        <div className="atm-content-section">
          <h3>📝 Original Content</h3>
          <div className="atm-content-actions">
            <button
              onClick={loadEditorContent}
              className="atm-btn atm-btn-secondary"
              disabled={isLoading}
            >
              📄 Load from Editor
            </button>
            <span className="atm-word-count">
              {stats.originalWords} words
              {stats.originalWords > 0 && (
                <span className="cost-preview">
                  {" "}
                  • ~{getEstimatedCost()} credits
                </span>
              )}
            </span>
          </div>
          <textarea
            value={content}
            onChange={(e) => {
              setContent(e.target.value);
              setStats((prev) => ({
                ...prev,
                originalWords: countWords(e.target.value),
              }));
            }}
            placeholder={`Content will be loaded from the editor, or you can paste it here...

Example:
- Blog posts from ChatGPT, Claude, or Gemini
- AI-generated articles that need humanization
- Content that was flagged by AI detectors
- Any text that sounds too robotic or formal`}
            rows={14}
            className="atm-textarea"
            disabled={isLoading}
          />
        </div>

        <div className="atm-content-section">
          <h3>✨ Humanized Content</h3>
          <div className="atm-content-actions">
            <button
              onClick={() => handleReplaceContent(false)}
              disabled={!humanizedContent || isLoading}
              className={`atm-btn atm-btn-primary ${
                !humanizedContent || isLoading ? "disabled" : ""
              }`}
            >
              ✅ Replace in Editor
            </button>
            <button
              onClick={handleCopyContent}
              disabled={!humanizedContent || isLoading}
              className={`atm-btn atm-btn-secondary ${
                !humanizedContent || isLoading ? "disabled" : ""
              }`}
            >
              📋 Copy
            </button>
            <button
              onClick={detectAI}
              disabled={!humanizedContent || isLoading}
              className={`atm-btn atm-btn-outline ${
                !humanizedContent || isLoading ? "disabled" : ""
              }`}
            >
              🔍 Check Detection
            </button>
            <span className="atm-word-count">
              {stats.humanizedWords} words
              {stats.creditsUsed > 0 && (
                <span className="credits-used">
                  {" "}
                  • {stats.creditsUsed} credits used
                </span>
              )}
              {stats.processingTime > 0 && (
                <span className="processing-time">
                  {" "}
                  • {Math.round(stats.processingTime / 1000)}s
                </span>
              )}
            </span>
          </div>
          <textarea
            value={humanizedContent}
            onChange={(e) => setHumanizedContent(e.target.value)}
            placeholder={`Humanized content will appear here...

The result will be:
✅ Natural and human-like
✅ Bypasses AI detection
✅ Maintains original meaning
✅ Improved readability
✅ Authentic tone and flow`}
            rows={14}
            className="atm-textarea"
            disabled={isLoading}
          />
        </div>
      </div>

      {/* Statistics */}
      {stats.detectionScore !== null && (
        <div className="atm-stats-panel">
          <h3>🎯 AI Detection Analysis</h3>
          <div
            className={`atm-detection-score ${
              stats.detectionScore < 10
                ? "success"
                : stats.detectionScore < 30
                  ? "warning"
                  : "danger"
            }`}
          >
            <div className="score-main">
              <span className="score-value">{stats.detectionScore}%</span>
              <span className="score-label">AI detected</span>
            </div>
            <div className="score-status">
              {stats.detectionScore < 10 &&
                "✅ Excellent - Passes as human-written! Ready to publish."}
              {stats.detectionScore >= 10 &&
                stats.detectionScore < 30 &&
                "⚠️ Good - Minor AI signatures detected. Consider light editing."}
              {stats.detectionScore >= 30 &&
                "❌ Poor - Clearly AI-generated. Try re-humanizing with enhanced settings."}
            </div>
            <div className="score-recommendation">
              {stats.detectionScore < 10 &&
                "Your content will bypass AI detectors like GPTZero, Originality.AI, and Turnitin."}
              {stats.detectionScore >= 10 &&
                stats.detectionScore < 30 &&
                "Try using business mode or combo provider for better results."}
              {stats.detectionScore >= 30 &&
                "Consider manual editing or using a different tone/provider combination."}
            </div>
          </div>
        </div>
      )}

      {/* Action Panel */}
      <div className="atm-action-panel">
        <button
          onClick={handleHumanize}
          disabled={isLoading || !content.trim()}
          className="atm-btn atm-btn-primary atm-btn-large"
        >
          {isLoading ? (
            <>
              <span className="spinner"></span>
              Processing...
            </>
          ) : (
            `🚀 Humanize with ${providers[settings.provider]}`
          )}
        </button>

        {progress && (
          <div
            className={`atm-progress-message ${
              progress.includes("✅")
                ? "success"
                : progress.includes("❌")
                  ? "error"
                  : ""
            }`}
          >
            {progress}
          </div>
        )}

        {content.length > 0 && content.length < 50 && (
          <div className="atm-warning-message">
            ⚠️ Content is too short. Minimum 50 characters required for
            humanization.
          </div>
        )}
      </div>

      {/* Help Section */}
      <div className="atm-help-section">
        <h3>💡 Tips for Best Results</h3>
        <div className="atm-tips-grid">
          <div className="tip-item">
            <h4>🎯 Provider Selection</h4>
            <ul>
              <li>
                <strong>StealthGPT:</strong> Best for bypassing AI detection
                tools like Turnitin and GPTZero
              </li>
              <li>
                <strong>OpenRouter:</strong> Most natural-sounding results using
                advanced AI models
              </li>
              <li>
                <strong>Combo:</strong> Maximum quality by combining multiple
                providers
              </li>
              <li>
                <strong>Undetectable.AI:</strong> Reliable alternative focused
                on detection bypass
              </li>
            </ul>
          </div>
          <div className="tip-item">
            <h4>⚙️ Settings Optimization</h4>
            <ul>
              <li>
                Use <strong>Enhanced Model</strong> for critical content that
                must pass detection
              </li>
              <li>
                <strong>Conversational tone</strong> works best for blogs and
                articles
              </li>
              <li>
                <strong>Professional tone</strong> for business and formal
                content
              </li>
              <li>
                <strong>Academic tone</strong> for research papers and scholarly
                writing
              </li>
            </ul>
          </div>
          <div className="tip-item">
            <h4>📏 Content Guidelines</h4>
            <ul>
              <li>Minimum 50 characters required for processing</li>
              <li>Works best with 100+ words for optimal results</li>
              <li>Break very long content (5000+ words) into sections</li>
              <li>Preserve important formatting when possible</li>
            </ul>
          </div>
          <div className="tip-item">
            <h4>🔍 Detection Scores</h4>
            <ul>
              <li>
                <strong>&lt;10%:</strong> Excellent - Publish with confidence
              </li>
              <li>
                <strong>10-30%:</strong> Good - Light manual editing recommended
              </li>
              <li>
                <strong>30-60%:</strong> Fair - Re-humanize with different
                settings
              </li>
              <li>
                <strong>&gt;60%:</strong> Poor - Manual rewriting or combo mode
                needed
              </li>
            </ul>
          </div>
        </div>
      </div>

      {/* Cost Estimator */}
      <div className="atm-cost-estimator">
        <h3>💰 Cost Estimator</h3>
        <div className="cost-breakdown">
          <div className="cost-item">
            <span>Current Content:</span>
            <span>{stats.originalWords} words</span>
          </div>
          <div className="cost-item">
            <span>Estimated Cost:</span>
            <span
              className={
                getEstimatedCost() > 100
                  ? "high-cost"
                  : getEstimatedCost() > 50
                    ? "medium-cost"
                    : "low-cost"
              }
            >
              {getEstimatedCost()} credits
            </span>
          </div>
          <div className="cost-item">
            <span>Provider:</span>
            <span>{providers[settings.provider]}</span>
          </div>
          {stats.creditsUsed > 0 && (
            <div className="cost-item">
              <span>Last Used:</span>
              <span className="used-credits">{stats.creditsUsed} credits</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default HumanizeComponent;
