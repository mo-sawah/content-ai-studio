// src/components/automation/AutoTrendingForm.js (SIMPLIFIED FOR AUTOMATION)
import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  DropdownMenu,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

function AutoTrendingForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Local state for dropdown labels
  const [regionLabel, setRegionLabel] = useState("Global");
  const [languageLabel, setLanguageLabel] = useState("English");
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
  const regionOptions = [
    { label: "Global", value: "" },
    { label: "United States", value: "US" },
    { label: "United Kingdom", value: "GB" },
    { label: "Canada", value: "CA" },
    { label: "Australia", value: "AU" },
    { label: "Germany", value: "DE" },
    { label: "France", value: "FR" },
    { label: "India", value: "IN" },
    { label: "Brazil", value: "BR" },
    { label: "Japan", value: "JP" },
  ];

  const languageOptions = [
    { label: "English", value: "en" },
    { label: "Spanish", value: "es" },
    { label: "French", value: "fr" },
    { label: "German", value: "de" },
    { label: "Portuguese", value: "pt" },
    { label: "Italian", value: "it" },
  ];

  const styleOptions = atm_studio_data?.writing_styles
    ? Object.entries(atm_studio_data.writing_styles).map(
        ([value, { label }]) => ({ label, value })
      )
    : [{ label: "Standard SEO", value: "default_seo" }];

  // Initialize labels on mount
  useEffect(() => {
    // Set Region label
    const currentRegion = regionOptions.find(
      (option) =>
        option.value === (campaignData.settings?.trending_region || "")
    );
    if (currentRegion) {
      setRegionLabel(currentRegion.label);
    }

    // Set Language label
    const currentLanguage = languageOptions.find(
      (option) =>
        option.value === (campaignData.settings?.trending_language || "en")
    );
    if (currentLanguage) {
      setLanguageLabel(currentLanguage.label);
    }

    // Set Writing Style label
    const currentStyle = styleOptions.find(
      (option) =>
        option.value === (campaignData.settings?.writing_style || "default_seo")
    );
    if (currentStyle) {
      setWritingStyleLabel(currentStyle.label);
    }
  }, [campaignData.settings, regionOptions, languageOptions, styleOptions]);

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
        <h3>Trending Articles Configuration</h3>

        <TextControl
          label="Keyword"
          placeholder="e.g., AI, renewable energy, crypto"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="Search for trending topics related to this keyword"
        />

        <TextControl
          label="Custom Title Template (Optional)"
          placeholder="Leave empty to use trending titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Optional title template, or let AI generate from trending topics"
        />

        {/* Trending Settings Grid */}
        <div className="atm-grid-3">
          <CustomDropdown
            label="Region"
            text={regionLabel}
            options={regionOptions}
            onChange={(option) => {
              updateSetting("trending_region", option.value);
              setRegionLabel(option.label);
            }}
            helpText="Geographic region for trending topics"
          />

          <CustomDropdown
            label="Language"
            text={languageLabel}
            options={languageOptions}
            onChange={(option) => {
              updateSetting("trending_language", option.value);
              setLanguageLabel(option.label);
            }}
            helpText="Language for trend search and article generation"
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

        {/* Custom Instructions */}
        <TextareaControl
          label="Custom Instructions (Optional)"
          placeholder="Add specific requirements for trending article generation..."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={4}
          help="Additional instructions for trending article automation"
        />
      </div>
    </div>
  );
}

export default AutoTrendingForm;
