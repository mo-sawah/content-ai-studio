// src/components/automation/AutomationSettingsForm.js (TEMPORARY SIMPLE VERSION)
import { useState } from "@wordpress/element";
import {
  TextControl,
  SelectControl,
  ToggleControl,
} from "@wordpress/components";

function AutomationSettingsForm({ campaignData, setCampaignData, isLoading }) {
  const schedulePresets = [
    { label: "Every 15 mins", value: 15, unit: "minute" },
    { label: "Every 30 mins", value: 30, unit: "minute" },
    { label: "Every hour", value: 1, unit: "hour" },
    { label: "Daily", value: 1, unit: "day" },
  ];

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
          />

          <SelectControl
            label="Unit"
            value={campaignData.schedule_unit || "hour"}
            onChange={(value) =>
              setCampaignData({ ...campaignData, schedule_unit: value })
            }
            options={[
              { label: "Minutes", value: "minute" },
              { label: "Hours", value: "hour" },
              { label: "Days", value: "day" },
              { label: "Weeks", value: "week" },
            ]}
          />
        </div>
      </div>

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
          />

          <SelectControl
            label="Author"
            value={campaignData.author_id || 1}
            onChange={(value) =>
              setCampaignData({ ...campaignData, author_id: parseInt(value) })
            }
            options={[{ label: "Default Author", value: 1 }]}
          />
        </div>
      </div>

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
          />

          <ToggleControl
            label="Campaign Active"
            checked={campaignData.is_active !== false}
            onChange={(value) =>
              setCampaignData({ ...campaignData, is_active: value })
            }
          />
        </div>
      </div>
    </div>
  );
}

export default AutomationSettingsForm;
