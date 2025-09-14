// src/components/automation/AutoMultipageArticlesForm.js (SIMPLIFIED FOR AUTOMATION)
import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  RangeControl,
  DropdownMenu,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

function AutoMultipageArticlesForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Local state for dropdown labels
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [writingStyleLabel, setWritingStyleLabel] = useState("Standard SEO");

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
          className: "atm-popover",
        }}
      />
      {helpText && <p className="atm-dropdown-help">{helpText}</p>}
    </div>
  );

  // Options for dropdowns
  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...(atm_studio_data?.article_models
      ? Object.entries(atm_studio_data.article_models).map(
          ([value, label]) => ({ label, value })
        )
      : []),
  ];

  const styleOptions = atm_studio_data?.writing_styles
    ? Object.entries(atm_studio_data.writing_styles).map(
        ([value, { label }]) => ({ label, value })
      )
    : [{ label: "Standard SEO", value: "default_seo" }];

  // Initialize labels on mount
  useEffect(() => {
    // Set AI Model label
    const currentModel = modelOptions.find(
      (option) => option.value === (campaignData.settings?.ai_model || "")
    );
    if (currentModel) {
      setArticleModelLabel(currentModel.label);
    }

    // Set Writing Style label
    const currentStyle = styleOptions.find(
      (option) =>
        option.value === (campaignData.settings?.writing_style || "default_seo")
    );
    if (currentStyle) {
      setWritingStyleLabel(currentStyle.label);
    }
  }, [campaignData.settings, modelOptions, styleOptions]);

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
          placeholder="e.g., The future of renewable energy"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The main topic for your multipage articles"
        />

        <TextControl
          label="Article Title (Optional)"
          placeholder="Leave empty to auto-generate titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Provide a specific title template, or let AI create one from your keyword"
        />

        <div className="atm-grid-2">
          <CustomDropdown
            label="AI Model"
            text={articleModelLabel}
            options={modelOptions}
            onChange={(option) => {
              updateSetting("ai_model", option.value);
              setArticleModelLabel(option.label);
            }}
          />

          <CustomDropdown
            label="Writing Style"
            text={writingStyleLabel}
            options={styleOptions}
            onChange={(option) => {
              updateSetting("writing_style", option.value);
              setWritingStyleLabel(option.label);
            }}
          />
        </div>
      </div>

      {/* Multipage Structure Section */}
      <div className="atm-form-section">
        <h3>Multipage Structure</h3>

        <div className="atm-grid-2">
          <div>
            <RangeControl
              label={`Number of Pages: ${campaignData.settings?.page_count || 3}`}
              value={campaignData.settings?.page_count || 3}
              onChange={(value) => updateSetting("page_count", value)}
              min={2}
              max={10}
              help="How many pages to create for each article"
            />
          </div>

          <div>
            <RangeControl
              label={`Words per Page: ~${campaignData.settings?.words_per_page || 800}`}
              value={campaignData.settings?.words_per_page || 800}
              onChange={(value) => updateSetting("words_per_page", value)}
              min={300}
              max={1500}
              step={50}
              help="Target word count for each page"
            />
          </div>
        </div>
      </div>

      {/* Advanced Options Section */}
      <div className="atm-form-section">
        <h3>Advanced Options</h3>

        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Add specific instructions for multipage article generation..."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={4}
          help="Custom instructions for multipage automation"
        />
      </div>
    </div>
  );
}

export default AutoMultipageArticlesForm;
