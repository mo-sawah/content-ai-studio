// src/components/automation/AutoListicleForm.js (SIMPLIFIED FOR AUTOMATION)
import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  CheckboxControl,
  RangeControl,
  DropdownMenu,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

function AutoListicleForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Local state for dropdown labels
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [categoryLabel, setCategoryLabel] = useState("Technology");

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

  const categoryOptions = [
    { label: "Technology", value: "technology" },
    { label: "Business", value: "business" },
    { label: "Health & Fitness", value: "health" },
    { label: "Travel", value: "travel" },
    { label: "Food & Cooking", value: "food" },
    { label: "Fashion & Beauty", value: "fashion" },
    { label: "Home & Garden", value: "home" },
    { label: "Entertainment", value: "entertainment" },
    { label: "Education", value: "education" },
    { label: "Finance", value: "finance" },
    { label: "Lifestyle", value: "lifestyle" },
    { label: "Sports", value: "sports" },
  ];

  // Initialize labels on mount
  useEffect(() => {
    // Set AI Model label
    const currentModel = modelOptions.find(
      (option) => option.value === (campaignData.settings?.ai_model || "")
    );
    if (currentModel) {
      setArticleModelLabel(currentModel.label);
    }

    // Set Category label
    const currentCategory = categoryOptions.find(
      (option) =>
        option.value ===
        (campaignData.settings?.listicle_category || "technology")
    );
    if (currentCategory) {
      setCategoryLabel(currentCategory.label);
    }
  }, [campaignData.settings, modelOptions]);

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
        <h3>Listicle Configuration</h3>

        <TextControl
          label="Topic or Theme"
          placeholder="e.g., best productivity apps, top marketing tools"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The main topic for your automated listicles (what you want to list)"
        />

        <TextControl
          label="Custom Title (Optional)"
          placeholder="Leave empty to auto-generate titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Provide a specific title template or let AI generate unique ones"
        />

        {/* Listicle Structure */}
        <div className="atm-grid-2">
          <div>
            <RangeControl
              label={`Number of Items: ${campaignData.settings?.item_count || 10}`}
              value={campaignData.settings?.item_count || 10}
              onChange={(value) => updateSetting("item_count", value)}
              min={5}
              max={25}
              help="How many items to include in your listicles"
            />
          </div>

          <CustomDropdown
            label="Category"
            text={categoryLabel}
            options={categoryOptions}
            onChange={(option) => {
              updateSetting("listicle_category", option.value);
              setCategoryLabel(option.label);
            }}
            helpText="Category helps generate more relevant content"
          />
        </div>

        {/* AI Model Selection */}
        <CustomDropdown
          label="AI Model"
          text={articleModelLabel}
          options={modelOptions}
          onChange={(option) => {
            updateSetting("ai_model", option.value);
            setArticleModelLabel(option.label);
          }}
        />

        {/* Content Options */}
        <div className="atm-grid-2">
          <CheckboxControl
            label="Include pricing information"
            checked={campaignData.settings?.include_pricing || false}
            onChange={(value) => updateSetting("include_pricing", value)}
            help="Add price details when relevant"
          />

          <CheckboxControl
            label="Include ratings/scores"
            checked={campaignData.settings?.include_ratings !== false} // Default true
            onChange={(value) => updateSetting("include_ratings", value)}
            help="Add star ratings or numerical scores"
          />
        </div>

        {/* Custom Instructions */}
        <TextareaControl
          label="Custom Instructions (Optional)"
          placeholder="Add specific requirements, tone, or focus areas..."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={4}
          help="Additional instructions to customize the listicle content"
        />
      </div>
    </div>
  );
}

export default AutoListicleForm;
