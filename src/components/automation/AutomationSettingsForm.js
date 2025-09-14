import { useState, useEffect, useRef } from "@wordpress/element";
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
import CustomDropdown from "../common/CustomDropdown";

// Reusable Custom Dropdown Component with width matching
// New Multi-Category Selector Component with width matching

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
              <h4>Publishing Frequency</h4>
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
