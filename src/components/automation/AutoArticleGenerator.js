// src/components/automation/AutoArticleGenerator.js (REVISED AND UNIFIED)
import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
  SelectControl,
  Spinner,
} from "@wordpress/components";

// Helper function to calculate post frequency
const calculatePostFrequency = (value, unit) => {
  if (!value || !unit) return { day: 0, week: 0, month: 0 };
  const minutes =
    value *
    (unit === "minute"
      ? 1
      : unit === "hour"
        ? 60
        : unit === "day"
          ? 1440
          : 10080);
  const perDay = minutes > 0 ? Math.round((24 * 60) / minutes) : 0;
  return {
    day: perDay,
    week: perDay * 7,
    month: Math.round(perDay * 30.4),
  };
};

// Article type configurations (from your original file)
const articleTypes = [
  {
    id: "standard",
    title: "Standard Articles",
    description: "High-quality SEO content with intelligent angle diversity",
  },
  {
    id: "trending",
    title: "Trending Articles",
    description: "Current hot topics and trending searches",
  },
  {
    id: "listicle",
    title: "Listicle Articles",
    description: "Numbered lists and top 10 style content",
  },
  {
    id: "multipage",
    title: "Multipage Articles",
    description: "Multi-part comprehensive guides and series",
  },
];

function AutoArticleGenerator({
  setActiveView,
  editingCampaign,
  categories = [],
  authors = [],
}) {
  const [activeTab, setActiveTab] = useState("standard");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  const [campaignData, setCampaignData] = useState({
    name: "",
    keyword: "",
    type: "articles",
    sub_type: "standard",
    settings: {
      ai_model: "",
      writing_style: "default_seo",
      creativity_level: "high",
      word_count: "",
      custom_prompt: "",
      generate_image: true,
      skip_weekends: false,
      quality_check: false,
    },
    schedule_value: 1,
    schedule_unit: "hour",
    schedule_time: "",
    content_mode: "draft",
    category_ids: [],
    author_id: 1,
    is_active: true,
  });

  useEffect(() => {
    if (editingCampaign) {
      setCampaignData({ ...campaignData, ...editingCampaign });
      setActiveTab(editingCampaign.sub_type || "standard");
    }
  }, [editingCampaign]);

  useEffect(() => {
    setCampaignData((prev) => ({ ...prev, sub_type: activeTab }));
  }, [activeTab]);

  const handleSaveCampaign = async () => {
    if (!campaignData.name.trim() || !campaignData.keyword.trim()) {
      setStatusMessage("Error: Campaign Name and Keyword/Topic are required.");
      return;
    }
    setIsLoading(true);
    setStatusMessage("");

    try {
      // Note: Your original AJAX call in AutoArticleGenerator.js was correct.
      // The bug was in the PHP backend, which is fixed in Step 3.
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_save_automation_campaign",
          nonce: atm_automation_data.nonce,
          campaign_data: JSON.stringify(campaignData),
          campaign_id: editingCampaign?.id || 0,
        },
      });
      if (response.success) {
        setStatusMessage(
          editingCampaign
            ? "Campaign updated successfully!"
            : "Campaign created successfully!"
        );
        setTimeout(() => setActiveView("campaigns"), 1500);
      } else {
        throw new Error(response.data || "Failed to save campaign");
      }
    } catch (error) {
      setStatusMessage("Error: " + error.message);
    } finally {
      setIsLoading(false);
    }
  };

  const schedulePresets = [
    { label: "Every 15 mins", value: 15, unit: "minute" },
    { label: "Every 30 mins", value: 30, unit: "minute" },
    { label: "Every hour", value: 1, unit: "hour" },
    { label: "Every 2 hours", value: 2, unit: "hour" },
    { label: "Daily", value: 1, unit: "day" },
    { label: "Weekly", value: 1, unit: "week" },
  ];

  const applySchedulePreset = (preset) => {
    setCampaignData({
      ...campaignData,
      schedule_value: preset.value,
      schedule_unit: preset.unit,
    });
  };

  const frequency = calculatePostFrequency(
    campaignData.schedule_value,
    campaignData.schedule_unit
  );

  return (
    <div className="atm-generator-view">
      {/* Article Type Selector - This part is already well-designed */}
      <div className="atm-type-selector">
        {articleTypes.map((type) => (
          <div
            key={type.id}
            className={`atm-type-card ${activeTab === type.id ? "active" : ""}`}
            onClick={() => setActiveTab(type.id)}
          >
            <div className="atm-type-content">
              <h3>{type.title}</h3>
              <p>{type.description}</p>
            </div>
          </div>
        ))}
      </div>

      {/* --- UNIFIED FORM STARTS HERE --- */}
      <div className="atm-form-container">
        {/* SECTION 1: CAMPAIGN & CONTENT */}
        <div className="atm-form-section">
          <h3>
            {articleTypes.find((t) => t.id === activeTab)?.title} Configuration
          </h3>
          <div className="atm-form-row">
            <TextControl
              label="Campaign Name"
              value={campaignData.name || ""}
              onChange={(value) =>
                setCampaignData({ ...campaignData, name: value })
              }
              disabled={isLoading}
            />
            <TextControl
              label="Keyword/Topic"
              value={campaignData.keyword || ""}
              onChange={(value) =>
                setCampaignData({ ...campaignData, keyword: value })
              }
              disabled={isLoading}
            />
          </div>
        </div>

        {/* SECTION 2: AI & CONTENT SETTINGS */}
        <div className="atm-form-section">
          <h3>AI & Content Settings</h3>
          <div
            className="atm-form-row"
            style={{ gridTemplateColumns: "1fr 1fr 1fr" }}
          >
            <SelectControl
              label="AI Model"
              value={campaignData.settings.ai_model}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, ai_model: v },
                })
              }
              options={
                [{ label: "Default", value: "" }] /* Add other options */
              }
            />
            <SelectControl
              label="Writing Style"
              value={campaignData.settings.writing_style}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, writing_style: v },
                })
              }
              options={
                [
                  { label: "Standard SEO", value: "default_seo" },
                ] /* Add other options */
              }
            />
            <SelectControl
              label="Creativity Level"
              value={campaignData.settings.creativity_level}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, creativity_level: v },
                })
              }
              options={
                [
                  { label: "Creative (Dynamic)", value: "high" },
                ] /* Add other options */
              }
            />
          </div>
          <div className="atm-form-row">
            <TextControl
              label="Target Word Count"
              type="number"
              value={campaignData.settings.word_count}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, word_count: v },
                })
              }
              help="Leave empty for automatic sizing."
            />
            <TextareaControl
              label="Custom Prompt (Optional)"
              value={campaignData.settings.custom_prompt}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, custom_prompt: v },
                })
              }
              help="Overrides the selected writing style."
            />
          </div>
        </div>

        {/* SECTION 3: SCHEDULE & PUBLISHING */}
        <div className="atm-form-section">
          <h3>Schedule & Publishing</h3>
          <label className="components-base-control__label">
            Quick Presets
          </label>
          <div className="atm-preset-buttons">
            {schedulePresets.map((preset) => (
              <button
                key={preset.label}
                type="button"
                className={`atm-preset-btn ${campaignData.schedule_value == preset.value && campaignData.schedule_unit === preset.unit ? "active" : ""}`}
                onClick={() => applySchedulePreset(preset)}
                disabled={isLoading}
              >
                {preset.label}
              </button>
            ))}
          </div>
          <p className="atm-schedule-summary">
            This schedule will generate approximately{" "}
            <strong>{frequency.day}</strong> posts per day.
          </p>
          <div className="atm-form-row">
            <TextControl
              label="Every"
              type="number"
              value={campaignData.schedule_value || 1}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  schedule_value: Math.max(1, parseInt(v) || 1),
                })
              }
              min="1"
            />
            <SelectControl
              label="Unit"
              value={campaignData.schedule_unit || "hour"}
              onChange={(v) =>
                setCampaignData({ ...campaignData, schedule_unit: v })
              }
              options={[
                { label: "Minutes", value: "minute" },
                { label: "Hours", value: "hour" },
                { label: "Days", value: "day" },
              ]}
            />
            <SelectControl
              label="Content Mode"
              value={campaignData.content_mode || "draft"}
              onChange={(v) =>
                setCampaignData({ ...campaignData, content_mode: v })
              }
              options={[
                { label: "Save as Draft", value: "draft" },
                { label: "Publish Immediately", value: "publish" },
              ]}
            />
            <SelectControl
              label="Author"
              value={campaignData.author_id || 1}
              onChange={(v) =>
                setCampaignData({ ...campaignData, author_id: parseInt(v) })
              }
              options={
                authors.length > 0
                  ? authors
                  : [{ label: "Default Author", value: 1 }]
              }
            />
          </div>
        </div>

        {/* SECTION 4: CAMPAIGN CONTROLS */}
        <div className="atm-form-section">
          <h3>Campaign Controls</h3>
          <div
            className="atm-form-row"
            style={{ gridTemplateColumns: "1fr 1fr 1fr" }}
          >
            <ToggleControl
              label="Campaign Active"
              checked={!!campaignData.is_active}
              onChange={(v) =>
                setCampaignData({ ...campaignData, is_active: v })
              }
              help="Enable or disable this campaign."
            />
            <ToggleControl
              label="Generate Featured Image"
              checked={!!campaignData.settings.generate_image}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, generate_image: v },
                })
              }
              help="Create an AI image for each post."
            />
            <ToggleControl
              label="Skip Weekends"
              checked={!!campaignData.settings.skip_weekends}
              onChange={(v) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, skip_weekends: v },
                })
              }
              help="Pause execution on Saturday & Sunday."
            />
          </div>
        </div>

        {/* FINAL ACTIONS */}
        <div className="atm-form-actions">
          <Button isPrimary onClick={handleSaveCampaign} disabled={isLoading}>
            {isLoading ? (
              <>
                <Spinner /> Saving...
              </>
            ) : editingCampaign ? (
              "Update Campaign"
            ) : (
              "Create Campaign"
            )}
          </Button>
          <Button
            isSecondary
            onClick={() => setActiveView("hub")}
            disabled={isLoading}
          >
            Cancel
          </Button>
        </div>
        {statusMessage && (
          <div
            className={`atm-status-message ${statusMessage.includes("Error") ? "error" : "success"}`}
          >
            {statusMessage}
          </div>
        )}
      </div>
    </div>
  );
}

export default AutoArticleGenerator;
