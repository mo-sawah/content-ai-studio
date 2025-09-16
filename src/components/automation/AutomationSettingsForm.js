import { useState, useEffect } from "@wordpress/element";
import {
  Dropdown,
  Button,
  CheckboxControl,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";
import CustomDropdown from "../common/CustomDropdown";
import AutomationHumanizer from "./AutomationHumanizer";

/**
 * Enhanced Schedule Summary Component with Cost Calculator
 * Add this to AutomationSettingsForm.js to replace the existing schedule summary
 */

// Add this function at the top of AutomationSettingsForm.js
const calculateAutomationCost = (campaignType, settings) => {
  // Base web search costs based on settings
  const webSearchSetting = window.atm_studio_data?.web_search_max_results || 5;

  // Cost mapping based on web search results
  const webSearchCosts = {
    1: 0.009,
    2: 0.013,
    3: 0.017,
    4: 0.021,
    5: 0.025, // 0.02 + 0.005 base
    6: 0.029,
    7: 0.033,
    8: 0.037,
    9: 0.041,
    10: 0.045,
  };

  const webSearchCost = webSearchCosts[webSearchSetting] || 0.02;

  // Campaign type multipliers
  const campaignCosts = {
    articles: {
      standard: webSearchCost * 1, // 1 web search call
      trending: webSearchCost * 2, // 2 web search calls (research + content)
      listicle: webSearchCost * 1.5, // 1.5x for research
      multipage: webSearchCost * 2.5, // Multiple pages
    },
    news: {
      search: webSearchCost * 1.5, // Google News + content
      twitter: webSearchCost * 1.2, // Twitter + content
      rss: webSearchCost * 0.8, // Minimal web search
      apis: webSearchCost * 1.3, // API + content
      live: webSearchCost * 1.8, // Live news + content
    },
    videos: webSearchCost * 0.7, // YouTube search + description
    podcasts: webSearchCost * 1.5, // Content + script generation
  };

  const subType = settings?.sub_type || "standard";
  let baseCost = 0;

  if (campaignType === "articles") {
    baseCost =
      campaignCosts.articles[subType] || campaignCosts.articles.standard;
  } else if (campaignType === "news") {
    baseCost = campaignCosts.news[subType] || campaignCosts.news.search;
  } else {
    baseCost = campaignCosts[campaignType] || webSearchCost;
  }

  // Add image generation cost if enabled
  if (settings?.generate_image) {
    baseCost += 0.04; // Approximate image generation cost
  }

  return baseCost;
};

// Category Selector Component
const CategorySelector = ({ campaignData, setCampaignData, categories }) => {
  const selectedCategoryIds = campaignData.settings?.category_ids || [];

  const handleCategoryChange = (isChecked, categoryId) => {
    const newIds = isChecked
      ? [...selectedCategoryIds, categoryId]
      : selectedCategoryIds.filter((id) => id !== categoryId);
    setCampaignData({
      ...campaignData,
      settings: { ...campaignData.settings, category_ids: newIds },
    });
  };

  const getButtonText = () => {
    if (!categories || categories.length === 0) return "No Categories Found";
    if (selectedCategoryIds.length === 0) return "Select Categories";
    if (selectedCategoryIds.length === 1) return "1 Category Selected";
    return `${selectedCategoryIds.length} Categories Selected`;
  };

  return (
    <div className="atm-dropdown-field">
      <label className="atm-dropdown-label">Categories</label>
      <Dropdown
        className="atm-custom-dropdown"
        contentClassName="atm-category-popover"
        popoverProps={{ position: "bottom left" }}
        renderToggle={({ isOpen, onToggle }) => (
          <Button
            variant="secondary"
            onClick={onToggle}
            aria-expanded={isOpen}
            icon={chevronDown}
            iconPosition="right"
            disabled={!categories || categories.length === 0}
            style={{ width: "100%", justifyContent: "space-between" }}
          >
            {getButtonText()}
          </Button>
        )}
        renderContent={() => (
          <div className="atm-category-list">
            {categories.length > 0 ? (
              categories.map((cat) => (
                <CheckboxControl
                  key={cat.id}
                  label={cat.name}
                  checked={selectedCategoryIds.includes(cat.id)}
                  onChange={(isChecked) =>
                    handleCategoryChange(isChecked, cat.id)
                  }
                />
              ))
            ) : (
              <p style={{ padding: "8px 12px", color: "#64748b" }}>
                No categories available.
              </p>
            )}
          </div>
        )}
      />
    </div>
  );
};

function AutomationSettingsForm({
  campaignData,
  setCampaignData,
  isLoading,
  authors = [],
  categories = [],
}) {
  const [unitLabel, setUnitLabel] = useState("Hours");
  const [contentModeLabel, setContentModeLabel] = useState("Save as Draft");
  const [authorLabel, setAuthorLabel] = useState("Default Author");
  const [dayOfWeekLabel, setDayOfWeekLabel] = useState("Any Day");

  const schedulePresets = [
    { label: "Every 15 mins", value: 15, unit: "minute" },
    { label: "Every 30 mins", value: 30, unit: "minute" },
    { label: "Every hour", value: 1, unit: "hour" },
    { label: "Daily", value: 1, unit: "day" },
  ];

  const unitOptions = [
    { label: "Minutes", value: "minute" },
    { label: "Hours", value: "hour" },
    { label: "Days", value: "day" },
    { label: "Weeks", value: "week" },
  ];

  const dayOfWeekOptions = [
    { label: "Any Day", value: "" },
    { label: "Sunday", value: "Sunday" },
    { label: "Monday", value: "Monday" },
    { label: "Tuesday", value: "Tuesday" },
    { label: "Wednesday", value: "Wednesday" },
    { label: "Thursday", value: "Thursday" },
    { label: "Friday", value: "Friday" },
    { label: "Saturday", value: "Saturday" },
  ];

  const contentModeOptions = [
    { label: "Save as Draft", value: "draft" },
    { label: "Publish Immediately", value: "publish" },
  ];

  const authorOptions =
    authors.length > 0
      ? authors.map((author) => ({ label: author.name, value: author.id }))
      : [{ label: "Default Author", value: 1 }];

  useEffect(() => {
    const currentUnit = unitOptions.find(
      (opt) => opt.value === (campaignData.schedule_unit || "hour")
    );
    if (currentUnit) setUnitLabel(currentUnit.label);

    const currentDay = dayOfWeekOptions.find(
      (opt) => opt.value === (campaignData.settings?.schedule_day || "")
    );
    if (currentDay) setDayOfWeekLabel(currentDay.label);

    const currentMode = contentModeOptions.find(
      (opt) => opt.value === (campaignData.content_mode || "draft")
    );
    if (currentMode) setContentModeLabel(currentMode.label);

    const currentAuthor = authorOptions.find(
      (opt) => opt.value === (campaignData.author_id || 1)
    );
    if (currentAuthor) setAuthorLabel(currentAuthor.label);
  }, [campaignData, authors]);

  const applySchedulePreset = (preset) => {
    setCampaignData({
      ...campaignData,
      schedule_value: preset.value,
      schedule_unit: preset.unit,
    });
  };

  const calculatePostsPerPeriod = () => {
    const value = campaignData.schedule_value || 1;
    const unit = campaignData.schedule_unit || "hour";
    let postsPerDay = 0;
    switch (unit) {
      case "minute":
        postsPerDay = (24 * 60) / value;
        break;
      case "hour":
        postsPerDay = 24 / value;
        break;
      case "day":
        postsPerDay = 1 / value;
        break;
      case "week":
        postsPerDay = 1 / (value * 7);
        break;
      default:
        postsPerDay = 0;
    }
    return {
      day: postsPerDay < 1 ? postsPerDay.toFixed(2) : Math.round(postsPerDay),
      week:
        postsPerDay < 1
          ? (postsPerDay * 7).toFixed(2)
          : Math.round(postsPerDay * 7),
      month:
        postsPerDay < 1
          ? (postsPerDay * 30.4).toFixed(2)
          : Math.round(postsPerDay * 30.4),
    };
  };

  const posts = calculatePostsPerPeriod();
  const showTimeInput =
    campaignData.schedule_unit === "day" ||
    campaignData.schedule_unit === "week";
  const showDayInput = campaignData.schedule_unit === "week";

  return (
    <div className="atm-form-container">
      <div className="atm-form-section">
        <h3>Schedule & Frequency</h3>
        <p className="description">
          Set how often the campaign should run. Note: For specific time/day
          scheduling to work, your server's cron job system must be configured
          to interpret these settings.
        </p>

        <div className="atm-preset-buttons">
          {schedulePresets.map((preset, index) => (
            <button
              key={index}
              type="button"
              className={`atm-preset-btn ${
                campaignData.schedule_value === preset.value &&
                campaignData.schedule_unit === preset.unit
                  ? "active"
                  : ""
              }`}
              onClick={() => applySchedulePreset(preset)}
              disabled={isLoading}
            >
              {preset.label}
            </button>
          ))}
        </div>

        <div className="atm-schedule-grid">
          <TextControl
            label="Every"
            type="number"
            value={campaignData.schedule_value || 1}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                schedule_value: Math.max(1, parseInt(value) || 1),
              })
            }
            disabled={isLoading}
          />
          <CustomDropdown
            label="Unit"
            text={unitLabel}
            options={unitOptions}
            onChange={(option) => {
              setCampaignData({ ...campaignData, schedule_unit: option.value });
              setUnitLabel(option.label);
            }}
          />
          {showDayInput && (
            <CustomDropdown
              label="On"
              text={dayOfWeekLabel}
              options={dayOfWeekOptions}
              onChange={(option) => {
                setCampaignData({
                  ...campaignData,
                  settings: {
                    ...campaignData.settings,
                    schedule_day: option.value,
                  },
                });
                setDayOfWeekLabel(option.label);
              }}
            />
          )}
          {showTimeInput && (
            <TextControl
              label="At Time"
              type="time"
              value={campaignData.settings?.schedule_time || ""}
              onChange={(value) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, schedule_time: value },
                })
              }
              disabled={isLoading}
            />
          )}
        </div>

        <div className="atm-schedule-summary">
          <div className="atm-summary-content">
            <div className="atm-summary-header">
              <h4>Publishing Frequency & Cost Analysis</h4>
              <span className="atm-summary-badge">Active Schedule</span>
            </div>
            <div className="atm-summary-stats">
              <div className="atm-summary-stat">
                <span className="atm-stat-number">{posts.day}</span>
                <span className="atm-stat-label">per day</span>
              </div>
              <div className="atm-summary-divider"></div>
              <div className="atm-summary-stat">
                <span className="atm-stat-number">{posts.week}</span>
                <span className="atm-stat-label">per week</span>
              </div>
              <div className="atm-summary-divider"></div>
              <div className="atm-summary-stat">
                <span className="atm-stat-number">{posts.month}</span>
                <span className="atm-stat-label">per month</span>
              </div>
              <div className="atm-summary-divider"></div>
              <div className="atm-summary-stat">
                <span className="atm-stat-number">
                  $
                  {calculateAutomationCost(
                    campaignData.type,
                    campaignData.sub_type,
                    campaignData.settings
                  ).toFixed(3)}
                </span>
                <span className="atm-stat-label">per article</span>
              </div>
            </div>

            <div className="atm-cost-breakdown">
              <div className="atm-cost-row">
                <span className="atm-cost-label">Daily Cost:</span>
                <span className="atm-cost-value">
                  $
                  {(
                    posts.day *
                    calculateAutomationCost(
                      campaignData.type,
                      campaignData.sub_type,
                      campaignData.settings
                    )
                  ).toFixed(2)}
                </span>
              </div>
              <div className="atm-cost-row">
                <span className="atm-cost-label">Monthly Cost:</span>
                <span className="atm-cost-value">
                  $
                  {(
                    posts.month *
                    calculateAutomationCost(
                      campaignData.type,
                      campaignData.sub_type,
                      campaignData.settings
                    )
                  ).toFixed(2)}
                </span>
              </div>
              {campaignData.settings?.generate_image && (
                <div className="atm-cost-note">
                  * Includes image generation (~$0.040 per article)
                </div>
              )}
              <div className="atm-cost-note">
                * Based on {window.atm_studio_data?.web_search_max_results || 5}{" "}
                web search results
              </div>
              {campaignData.type === "articles" &&
                campaignData.sub_type === "trending" && (
                  <div className="atm-cost-note">
                    * Trending articles use 2 API calls for research + content
                  </div>
                )}
            </div>
          </div>
        </div>
      </div>

      <div className="atm-form-section">
        <h3>Publishing Settings</h3>
        <div className="atm-publishing-grid">
          <CustomDropdown
            label="Content Mode"
            text={contentModeLabel}
            options={contentModeOptions}
            onChange={(option) => {
              setCampaignData({ ...campaignData, content_mode: option.value });
              setContentModeLabel(option.label);
            }}
          />
          <CustomDropdown
            label="Author"
            text={authorLabel}
            options={authorOptions}
            onChange={(option) => {
              setCampaignData({
                ...campaignData,
                author_id: parseInt(option.value),
              });
              setAuthorLabel(option.label);
            }}
          />
          <CategorySelector
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            categories={categories}
          />
        </div>
      </div>

      <div className="atm-form-section">
        <h3>Campaign Options</h3>
        <AutomationHumanizer
          campaignData={campaignData}
          setCampaignData={setCampaignData}
          isLoading={isLoading}
        />
        <div className="atm-inline-toggles">
          <ToggleControl
            label="Generate Featured Images"
            checked={campaignData.settings?.generate_image || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, generate_image: value },
              })
            }
            disabled={isLoading}
          />
          <ToggleControl
            label="Campaign Active"
            checked={campaignData.is_active !== false}
            onChange={(value) =>
              setCampaignData({ ...campaignData, is_active: value })
            }
            disabled={isLoading}
          />
        </div>
      </div>
    </div>
  );
}

export default AutomationSettingsForm;
