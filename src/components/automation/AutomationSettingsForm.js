// src/components/automation/AutomationSettingsForm.js (UPDATED with style fixes)
import { useState, useEffect } from "@wordpress/element";
import {
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

function AutomationSettingsForm({
  campaignData,
  setCampaignData,
  isLoading,
  authors = [], // Assuming authors are passed as props
}) {
  // === State for Dropdown Labels ===
  const [unitLabel, setUnitLabel] = useState("Hours");
  const [contentModeLabel, setContentModeLabel] = useState("Save as Draft");
  const [authorLabel, setAuthorLabel] = useState("Default Author");

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

  const contentModeOptions = [
    { label: "Save as Draft", value: "draft" },
    { label: "Publish Immediately", value: "publish" },
  ];

  const authorOptions =
    authors.length > 0
      ? authors.map((author) => ({
          label: author.name,
          value: author.id,
        }))
      : [{ label: "Default Author", value: 1 }];

  // === Effects to Sync Labels with Data ===
  useEffect(() => {
    const currentUnit = unitOptions.find(
      (opt) => opt.value === (campaignData.schedule_unit || "hour")
    );
    if (currentUnit) setUnitLabel(currentUnit.label);

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

  return (
    <div className="atm-form-container">
      <div className="atm-form-section">
        <h3>Schedule & Frequency</h3>
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

        <div className="atm-grid-2">
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
        </div>
      </div>

      <div className="atm-form-section">
        <h3>Publishing Settings</h3>
        <div className="atm-grid-2">
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
