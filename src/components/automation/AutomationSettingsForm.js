// src/components/automation/AutomationSettingsForm.js (FINAL VERSION)
import { useState, useEffect } from "@wordpress/element";
import {
  Dropdown,
  Button,
  Popover,
  CheckboxControl,
  DropdownMenu,
  TextControl,
  ToggleControl,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

// Reusable Custom Dropdown Component
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

// New Multi-Category Selector Component
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
        renderToggle={({ isOpen, onToggle }) => (
          <Button
            variant="secondary"
            onClick={onToggle}
            aria-expanded={isOpen}
            icon={chevronDown}
            iconPosition="right"
          >
            {getButtonText()}
          </Button>
        )}
        renderContent={() => (
          <div className="atm-category-list">
            {categories.map((cat) => (
              <CheckboxControl
                key={cat.id}
                label={cat.name}
                checked={selectedCategoryIds.includes(cat.id)}
                onChange={(isChecked) =>
                  handleCategoryChange(isChecked, cat.id)
                }
              />
            ))}
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
  // === State for Dropdown Labels ===
  const [unitLabel, setUnitLabel] = useState("Hours");
  const [contentModeLabel, setContentModeLabel] = useState("Save as Draft");
  const [authorLabel, setAuthorLabel] = useState("Default Author");
  const [dayOfWeekLabel, setDayOfWeekLabel] = useState("Any Day");

  // === Options for Dropdowns ===
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

  // === Effects to Sync Labels with Data ===
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
      day: Math.round(postsPerDay * 10) / 10,
      week: Math.round(postsPerDay * 7 * 10) / 10,
      month: Math.round(postsPerDay * 30.4 * 10) / 10,
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
              className={`atm-preset-btn ${campaignData.schedule_value === preset.value && campaignData.schedule_unit === preset.unit ? "active" : ""}`}
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
          <div className="atm-summary-stat">
            <span className="atm-stat-number">{posts.day}</span>
            <span className="atm-stat-label">Posts per day</span>
          </div>
          <div className="atm-summary-stat">
            <span className="atm-stat-number">{posts.week}</span>
            <span className="atm-stat-label">Posts per week</span>
          </div>
          <div className="atm-summary-stat">
            <span className="atm-stat-number">{posts.month}</span>
            <span className="atm-stat-label">Posts per month</span>
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
