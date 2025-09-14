// src/components/automation/AutomationSettingsForm.js (UPDATED TO MATCH MANUAL STYLING)
import {
  TextControl,
  SelectControl,
  ToggleControl,
  RangeControl,
  TextareaControl,
} from "@wordpress/components";

function AutomationSettingsForm({ campaignData, setCampaignData, isLoading }) {
  const schedulePresets = [
    { label: "Every 15 mins", value: 15, unit: "minute" },
    { label: "Every 30 mins", value: 30, unit: "minute" },
    { label: "Every hour", value: 1, unit: "hour" },
    { label: "Every 2 hours", value: 2, unit: "hour" },
    { label: "Every 6 hours", value: 6, unit: "hour" },
    { label: "Every 12 hours", value: 12, unit: "hour" },
    { label: "Daily", value: 1, unit: "day" },
    { label: "Every 2 days", value: 2, unit: "day" },
    { label: "Weekly", value: 1, unit: "week" },
  ];

  const applySchedulePreset = (preset) => {
    setCampaignData({
      ...campaignData,
      schedule_value: preset.value,
      schedule_unit: preset.unit,
    });
  };

  const calculatePostsPerDay = () => {
    const minutes =
      campaignData.schedule_value *
      (campaignData.schedule_unit === "minute"
        ? 1
        : campaignData.schedule_unit === "hour"
          ? 60
          : campaignData.schedule_unit === "day"
            ? 1440
            : 10080); // week
    return Math.round(((24 * 60) / minutes) * 10) / 10; // Round to 1 decimal place
  };

  return (
    <div className="atm-form-container">
      {/* SECTION 1: SCHEDULE & FREQUENCY */}
      <div className="atm-form-section">
        <h3>Schedule & Frequency</h3>

        {/* Quick Presets - Matching manual dashboard button style */}
        <label className="components-base-control__label">Quick Presets</label>
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

        {/* Custom Schedule - 2-column grid like manual dashboard */}
        <div className="atm-grid-2" style={{ marginTop: "20px" }}>
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
            min="1"
          />

          <CustomDropdown
            label="Unit"
            text={scheduleUnitLabel}
            options={scheduleUnitOptions}
            onChange={(option) => {
              setCampaignData({ ...campaignData, schedule_unit: option.value });
              setScheduleUnitLabel(option.label);
            }}
          />
        </div>

        {/* Schedule Preview - Clean summary box */}
        <div className="atm-schedule-summary">
          Will generate approximately <strong>{calculatePostsPerDay()}</strong>{" "}
          posts per day
          {calculatePostsPerDay() < 1 && " (less than 1 post per day)"}
        </div>
      </div>

      {/* SECTION 2: PUBLISHING SETTINGS */}
      <div className="atm-form-section">
        <h3>Publishing Settings</h3>

        <div className="atm-grid-2">
          <SelectControl
            label="Content Mode"
            value={campaignData.content_mode || "draft"}
            onChange={(value) =>
              setCampaignData({ ...campaignData, content_mode: value })
            }
            options={[
              { label: "Save as Draft", value: "draft" },
              { label: "Publish Immediately", value: "publish" },
            ]}
            disabled={isLoading}
            help={
              campaignData.content_mode === "draft"
                ? "Posts will be saved as drafts for manual review"
                : "Posts will be published automatically"
            }
          />

          <SelectControl
            label="Author"
            value={campaignData.author_id || 1}
            onChange={(value) =>
              setCampaignData({ ...campaignData, author_id: parseInt(value) })
            }
            options={[
              { label: "Default Author", value: 1 },
              // Add more authors from WordPress if available
            ]}
            disabled={isLoading}
          />
        </div>
      </div>

      {/* SECTION 3: CONTENT OPTIONS */}
      <div className="atm-form-section">
        <h3>Content Options</h3>

        <div className="atm-grid-2">
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
            help="Automatically create AI-generated featured images for each post"
          />

          <ToggleControl
            label="Skip Weekends"
            checked={campaignData.settings?.skip_weekends || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, skip_weekends: value },
              })
            }
            disabled={isLoading}
            help="Pause campaign execution on Saturdays and Sundays"
          />
        </div>
      </div>

      {/* SECTION 4: ADVANCED OPTIONS */}
      <div className="atm-form-section">
        <h3>Advanced Options</h3>

        <div className="atm-grid-2">
          <ToggleControl
            label="Campaign Active"
            checked={campaignData.is_active !== false}
            onChange={(value) =>
              setCampaignData({ ...campaignData, is_active: value })
            }
            disabled={isLoading}
            help="Enable or disable this campaign"
          />

          <ToggleControl
            label="Quality Check Mode"
            checked={campaignData.settings?.quality_check || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, quality_check: value },
              })
            }
            disabled={isLoading}
            help="Add extra validation to ensure higher content quality (slower execution)"
          />
        </div>
      </div>
    </div>
  );
}

export default AutomationSettingsForm;
