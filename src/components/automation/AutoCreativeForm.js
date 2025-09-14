// src/components/automation/AutoCreativeForm.js (UPDATED)
import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  ToggleControl,
  DropdownMenu,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

function AutoCreativeForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Local state for dropdown labels
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [writingStyleLabel, setWritingStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );
  const [wordCountLabel, setWordCountLabel] = useState("Default");
  const [creativityLabel, setCreativityLabel] = useState("Creative (Dynamic)");

  // Options for dropdowns with user-friendly labels
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

  // Initialize labels on mount
  useEffect(() => {
    const currentModel = modelOptions.find(
      (option) => option.value === (campaignData.settings?.ai_model || "")
    );
    if (currentModel) {
      setArticleModelLabel(currentModel.label);
    }

    const currentStyle = styleOptions.find(
      (option) =>
        option.value === (campaignData.settings?.writing_style || "default_seo")
    );
    if (currentStyle) {
      setWritingStyleLabel(currentStyle.label);
    }

    const currentWordCount = wordCountOptions.find(
      (option) =>
        option.value === (campaignData.settings?.word_count?.toString() || "")
    );
    if (currentWordCount) {
      setWordCountLabel(currentWordCount.label);
    }

    const currentCreativity = creativityOptions.find(
      (option) =>
        option.value === (campaignData.settings?.creativity_level || "high")
    );
    if (currentCreativity) {
      setCreativityLabel(currentCreativity.label);
    }
  }, [campaignData.settings]);

  // Custom dropdown component matching manual dashboard
  const CustomDropdown = ({ label, text, options, onChange, helpText }) => (
    <div className="atm-dropdown-field">
      <label className="atm-dropdown-label">{label}</label>
      <DropdownMenu
        className="atm-custom-dropdown"
        icon={chevronDown}
        text={text}
        controls={options.map((option) => ({
          title: option.label,
          onClick: () => onChange(option),
        }))}
        popoverProps={{
          className: "atm-dropdown-popover",
          position: "bottom left",
        }}
      />
      {helpText && <p className="atm-dropdown-help">{helpText}</p>}
    </div>
  );

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

  return (
    <div className="atm-form-container">
      {/* Content Configuration Section */}
      <div className="atm-form-section">
        <h3>Content Configuration</h3>

        <TextControl
          label="Keyword"
          placeholder="e.g., AI in digital marketing"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The main topic or keyword for your automated articles"
        />

        <TextControl
          label="Article Title (Optional)"
          placeholder="Leave empty to auto-generate unique titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Provide a specific title or let AI generate unique ones with different angles"
        />

        {/* AI Settings Grid */}
        <div className="atm-grid-3">
          <CustomDropdown
            label="AI Model"
            text={articleModelLabel}
            options={modelOptions}
            onChange={(option) => {
              updateSetting("ai_model", option.value);
              setArticleModelLabel(option.label);
            }}
            helpText="Choose the AI model for content generation"
          />

          <CustomDropdown
            label="Writing Style"
            text={writingStyleLabel}
            options={styleOptions}
            onChange={(option) => {
              updateSetting("writing_style", option.value);
              setWritingStyleLabel(option.label);
            }}
            helpText="Select the tone and style for your content"
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
        />

        {/* Advanced Options with Toggle Controls */}
        <div className="atm-form-section">
          <h4>Content Options</h4>
          <div className="atm-inline-toggles">
            {/* "Generate Featured Images" toggle is REMOVED from this file */}
            <ToggleControl
              label="Enable Web Search"
              checked={campaignData.settings?.enable_web_search !== false}
              onChange={(value) => updateSetting("enable_web_search", value)}
            />
            <ToggleControl
              label="Include Subheadlines"
              checked={campaignData.settings?.include_subheadlines !== false}
              onChange={(value) => updateSetting("include_subheadlines", value)}
            />
          </div>
        </div>

        {/* Custom Prompt */}
        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will override the writing style template."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={6}
          help="For very specific content requirements. This will override the selected writing style."
        />
      </div>
    </div>
  );
}

export default AutoCreativeForm;
