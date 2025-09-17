import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  ToggleControl,
  Button,
  Spinner,
  Notice,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

function AutoTitleBasedForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
  isLoading = false,
}) {
  // Local state for dropdown labels
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [writingStyleLabel, setWritingStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );
  const [wordCountLabel, setWordCountLabel] = useState("Default");
  const [creativityLabel, setCreativityLabel] = useState("Creative (Dynamic)");
  const [batchSizeLabel, setBatchSizeLabel] = useState("100 Titles");

  // NEW: Additional state for title generation options
  const [titleModelLabel, setTitleModelLabel] = useState("Use Default Model");
  const [titleStyleLabel, setTitleStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );

  // Loading states
  const [isGeneratingTitles, setIsGeneratingTitles] = useState(false);
  const [generateMessage, setGenerateMessage] = useState("");

  // Options for dropdowns
  const modelOptions = [
    { label: "Use Default Model", value: "" },
    { label: "OpenAI: GPT-4o (Best All-Around)", value: "openai/gpt-4o" },
    {
      label: "Anthropic: Claude 3 Opus (Top-Tier Writing)",
      value: "anthropic/claude-3-5-sonnet-20241022",
    },
    {
      label: "Google: Gemini 1.5 Flash (Fast & Capable)",
      value: "google/gemini-flash-1.5",
    },
    {
      label: "Meta: Llama 3 70B (Great Open Source)",
      value: "meta-llama/llama-3-70b-instruct",
    },
  ];

  const styleOptions = [
    { label: "Standard / SEO-Optimized", value: "default_seo" },
    { label: "Professional Business", value: "professional" },
    { label: "Conversational & Friendly", value: "conversational" },
    { label: "Technical / Expert", value: "technical" },
    { label: "News / Journalistic", value: "news" },
    { label: "Educational / Tutorial", value: "educational" },
  ];

  const wordCountOptions = [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Medium (~800 words)", value: "800" },
    { label: "Long (~1200 words)", value: "1200" },
    { label: "Very Long (~2000 words)", value: "2000" },
  ];

  const creativityOptions = [
    { label: "Conservative (Factual)", value: "low" },
    { label: "Balanced", value: "medium" },
    { label: "Creative (Dynamic)", value: "high" },
  ];

  const batchSizeOptions = [
    { label: "50 Titles", value: 50 },
    { label: "100 Titles", value: 100 },
    { label: "200 Titles", value: 200 },
    { label: "500 Titles", value: 500 },
    { label: "1000 Titles", value: 1000 },
  ];

  // Initialize labels on mount
  useEffect(() => {
    const currentModel = modelOptions.find(
      (option) => option.value === (campaignData.settings?.ai_model || "")
    );
    if (currentModel) setArticleModelLabel(currentModel.label);

    const currentStyle = styleOptions.find(
      (option) =>
        option.value === (campaignData.settings?.writing_style || "default_seo")
    );
    if (currentStyle) setWritingStyleLabel(currentStyle.label);

    const currentWordCount = wordCountOptions.find(
      (option) =>
        option.value === (campaignData.settings?.word_count?.toString() || "")
    );
    if (currentWordCount) setWordCountLabel(currentWordCount.label);

    const currentCreativity = creativityOptions.find(
      (option) =>
        option.value === (campaignData.settings?.creativity_level || "high")
    );
    if (currentCreativity) setCreativityLabel(currentCreativity.label);

    const currentBatchSize = batchSizeOptions.find(
      (option) =>
        option.value === (campaignData.settings?.titles_batch_size || 100)
    );
    if (currentBatchSize) setBatchSizeLabel(currentBatchSize.label);

    // NEW: Initialize title generation labels
    const currentTitleModel = modelOptions.find(
      (option) => option.value === (campaignData.settings?.title_ai_model || "")
    );
    if (currentTitleModel) setTitleModelLabel(currentTitleModel.label);

    const currentTitleStyle = styleOptions.find(
      (option) =>
        option.value ===
        (campaignData.settings?.title_writing_style || "default_seo")
    );
    if (currentTitleStyle) setTitleStyleLabel(currentTitleStyle.label);
  }, [campaignData.settings]);

  // Update campaign data helpers
  const updateSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      settings: {
        ...prev.settings,
        [key]: value,
      },
    }));
  };

  const updateBasicSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  // Generate titles function
  const handleGenerateTitles = async () => {
    if (!campaignData.keyword?.trim()) {
      setGenerateMessage("Please enter a keyword first.");
      return;
    }

    setIsGeneratingTitles(true);
    setGenerateMessage("Generating titles... This may take a few moments.");

    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_generate_article_titles",
          nonce: atm_automation_data.nonce,
          keyword: campaignData.keyword,
          batch_size: campaignData.settings?.titles_batch_size || 100,
          ai_model: campaignData.settings?.title_ai_model || "",
          writing_style:
            campaignData.settings?.title_writing_style || "default_seo",
          enable_web_search: campaignData.settings?.title_web_search !== false,
          existing_titles: JSON.stringify(
            campaignData.settings?.generated_titles || []
          ),
        },
      });

      if (response.success) {
        const newTitles = response.data.titles || [];
        updateSetting("generated_titles", newTitles);
        setGenerateMessage(
          `Successfully generated ${newTitles.length} unique titles!`
        );
      } else {
        throw new Error(response.data || "Failed to generate titles");
      }
    } catch (error) {
      setGenerateMessage(`Error: ${error.message}`);
      console.error("Title generation error:", error);
    } finally {
      setIsGeneratingTitles(false);
    }
  };

  // Remove title function
  const removeTitle = (indexToRemove) => {
    const currentTitles = campaignData.settings?.generated_titles || [];
    const updatedTitles = currentTitles.filter(
      (_, index) => index !== indexToRemove
    );
    updateSetting("generated_titles", updatedTitles);
  };

  // Clear all titles
  const clearAllTitles = () => {
    updateSetting("generated_titles", []);
    updateSetting("used_titles", []);
    setGenerateMessage("");
  };

  const generatedTitles = campaignData.settings?.generated_titles || [];
  const usedTitles = campaignData.settings?.used_titles || [];
  const remainingTitles = generatedTitles.length - usedTitles.length;

  return (
    <div className="atm-form-container">
      {/* Content Configuration Section */}
      <div className="atm-form-section">
        <h3>Title-Based Content Configuration</h3>
        <p className="atm-description">
          Generate a batch of article titles in advance. The system will create
          articles based on these pre-generated titles, ensuring consistent and
          planned content output.
        </p>

        <TextControl
          label="Keyword/Topic"
          placeholder="e.g., AI in digital marketing"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The main topic that all generated titles will be based on"
          disabled={isLoading}
        />

        {/* Title Generation Section */}
        <div className="atm-title-generation-section">
          <div className="atm-generation-header">
            <h4>Title Generation</h4>
            <div className="atm-generation-controls">
              <CustomDropdown
                label="Batch Size"
                text={batchSizeLabel}
                options={batchSizeOptions}
                onChange={(option) => {
                  updateSetting("titles_batch_size", option.value);
                  setBatchSizeLabel(option.label);
                }}
                disabled={isLoading || isGeneratingTitles}
              />
              <Button
                isPrimary
                onClick={handleGenerateTitles}
                disabled={
                  isLoading ||
                  isGeneratingTitles ||
                  !campaignData.keyword?.trim()
                }
                className="atm-generate-btn"
              >
                {isGeneratingTitles ? (
                  <>
                    <Spinner /> Generating...
                  </>
                ) : (
                  "Generate Titles"
                )}
              </Button>
            </div>
          </div>

          {/* Title Generation Options */}
          <div className="atm-title-generation-options">
            <h5>Title Generation Options</h5>
            <div className="atm-grid-3">
              <CustomDropdown
                label="AI Model for Titles"
                text={titleModelLabel}
                options={modelOptions}
                onChange={(option) => {
                  updateSetting("title_ai_model", option.value);
                  setTitleModelLabel(option.label);
                }}
                disabled={isLoading || isGeneratingTitles}
              />
              <CustomDropdown
                label="Title Writing Style"
                text={titleStyleLabel}
                options={styleOptions}
                onChange={(option) => {
                  updateSetting("title_writing_style", option.value);
                  setTitleStyleLabel(option.label);
                }}
                disabled={isLoading || isGeneratingTitles}
              />
              <div className="atm-toggle-field">
                <ToggleControl
                  label="Web Search for Titles"
                  checked={campaignData.settings?.title_web_search !== false}
                  onChange={(value) => updateSetting("title_web_search", value)}
                  disabled={isLoading || isGeneratingTitles}
                />
              </div>
            </div>
            <p className="atm-option-description">
              These settings control how titles are generated. Web search helps
              create more current and relevant titles.
            </p>
          </div>

          {generateMessage && (
            <Notice
              status={generateMessage.includes("Error") ? "error" : "success"}
              isDismissible={false}
              className="atm-generate-notice"
            >
              {generateMessage}
            </Notice>
          )}

          {/* Titles Display */}
          {generatedTitles.length > 0 && (
            <div className="atm-titles-container">
              <div className="atm-titles-header">
                <div className="atm-titles-stats">
                  <span className="atm-stat">
                    <strong>{generatedTitles.length}</strong> Total Titles
                  </span>
                  <span className="atm-stat">
                    <strong>{usedTitles.length}</strong> Used
                  </span>
                  <span className="atm-stat">
                    <strong>{remainingTitles}</strong> Remaining
                  </span>
                </div>
                <Button
                  isDestructive
                  isSmall
                  onClick={clearAllTitles}
                  disabled={isLoading}
                >
                  Clear All
                </Button>
              </div>

              <div className="atm-titles-list">
                {generatedTitles.map((title, index) => {
                  const isUsed = usedTitles.includes(title);
                  return (
                    <div
                      key={index}
                      className={`atm-title-item ${isUsed ? "used" : ""}`}
                    >
                      <div className="atm-title-content">
                        <span className="atm-title-number">{index + 1}</span>
                        <span className="atm-title-text">{title}</span>
                        {isUsed && (
                          <span className="atm-title-status">Used</span>
                        )}
                      </div>
                      {!isUsed && (
                        <Button
                          isSmall
                          isDestructive
                          onClick={() => removeTitle(index)}
                          disabled={isLoading}
                          className="atm-remove-title"
                        >
                          Remove
                        </Button>
                      )}
                    </div>
                  );
                })}
              </div>

              {remainingTitles === 0 && (
                <Notice
                  status="warning"
                  isDismissible={false}
                  className="atm-warning-notice"
                >
                  All titles have been used.{" "}
                  {campaignData.settings?.auto_regenerate_titles
                    ? "New titles will be automatically generated when needed."
                    : "Enable auto-regeneration or manually generate more titles."}
                </Notice>
              )}
            </div>
          )}
        </div>

        {/* AI Settings Grid */}
        <div className="atm-content-generation-options">
          <h4>Content Generation Options</h4>
          <div className="atm-grid-3">
            <CustomDropdown
              label="AI Model for Content"
              text={articleModelLabel}
              options={modelOptions}
              onChange={(option) => {
                updateSetting("ai_model", option.value);
                setArticleModelLabel(option.label);
              }}
              helpText="Choose the AI model for content generation"
              disabled={isLoading}
            />

            <CustomDropdown
              label="Content Writing Style"
              text={writingStyleLabel}
              options={styleOptions}
              onChange={(option) => {
                updateSetting("writing_style", option.value);
                setWritingStyleLabel(option.label);
              }}
              helpText="Select the tone and style for your content"
              disabled={isLoading}
            />

            <CustomDropdown
              label="Word Count"
              text={wordCountLabel}
              options={wordCountOptions}
              onChange={(option) => {
                updateSetting("word_count", parseInt(option.value) || 0);
                setWordCountLabel(option.label);
              }}
              helpText="Target article length"
              disabled={isLoading}
            />
          </div>

          {/* Creativity Level */}
          <CustomDropdown
            label="Creativity Level"
            text={creativityLabel}
            options={creativityOptions}
            onChange={(option) => {
              updateSetting("creativity_level", option.value);
              setCreativityLabel(option.label);
            }}
            helpText="Control how creative vs factual the content should be"
            disabled={isLoading}
          />

          {/* Content Toggle Options */}
          <div className="atm-content-toggles">
            <div className="atm-toggles-row">
              <ToggleControl
                label="Web Search for Content"
                checked={campaignData.settings?.enable_web_search !== false}
                onChange={(value) => updateSetting("enable_web_search", value)}
                disabled={isLoading}
              />
              <ToggleControl
                label="Include Subheadlines"
                checked={campaignData.settings?.include_subheadlines !== false}
                onChange={(value) =>
                  updateSetting("include_subheadlines", value)
                }
                disabled={isLoading}
              />
            </div>
            <p className="atm-option-description">
              Web search gathers current information during content generation.
              Subheadlines improve article structure and readability.
            </p>
          </div>
        </div>

        {/* Title Management Options */}
        <div className="atm-title-management-section">
          <h4>Title Management Options</h4>
          <div className="atm-inline-toggles">
            <ToggleControl
              label="Auto-regenerate titles when depleted"
              checked={campaignData.settings?.auto_regenerate_titles !== false}
              onChange={(value) =>
                updateSetting("auto_regenerate_titles", value)
              }
              disabled={isLoading}
              help="Automatically generate new titles when all current titles are used"
            />
          </div>
          <p className="atm-option-description">
            When enabled, the system will automatically generate new titles when
            the current batch is exhausted.
          </p>
        </div>

        {/* Custom Prompt */}
        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will override the writing style template."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={6}
          help="For very specific content requirements. This will override the selected writing style."
          disabled={isLoading}
        />
      </div>
    </div>
  );
}

export default AutoTitleBasedForm;
