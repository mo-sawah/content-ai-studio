import { useState } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
  SelectControl,
  Flex,
  FlexItem,
  Card,
  CardBody,
  __experimentalSpacer as Spacer,
} from "@wordpress/components";

function CampaignSettingsForm({
  campaignData,
  setCampaignData,
  isLoading,
  categories = [],
  authors = [],
}) {
  const [expandedSections, setExpandedSections] = useState({
    schedule: true,
    publishing: true,
    ai: true,
    advanced: false,
  });

  const toggleSection = (section) => {
    setExpandedSections((prev) => ({
      ...prev,
      [section]: !prev[section],
    }));
  };

  const schedulePresets = [
    { label: "Every 15 minutes", value: 15, unit: "minute" },
    { label: "Every 30 minutes", value: 30, unit: "minute" },
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
    return Math.round((24 * 60) / minutes);
  };

  return (
    <div className="atm-campaign-settings-form">
      {/* Schedule Settings */}
      <Card className="atm-settings-section">
        <div
          className="atm-section-header"
          onClick={() => toggleSection("schedule")}
        >
          <div className="atm-section-title">
            <span className="atm-section-icon">⏰</span>
            <h3>Schedule Settings</h3>
          </div>
          <button
            className={`atm-collapse-btn ${expandedSections.schedule ? "expanded" : ""}`}
          >
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        </div>

        {expandedSections.schedule && (
          <CardBody>
            <div className="atm-schedule-presets">
              <label className="atm-label">Quick Presets</label>
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
            </div>

            <Spacer marginTop={4} />

            <div className="atm-custom-schedule">
              <label className="atm-label">Custom Schedule</label>
              <div className="atm-schedule-inputs">
                <div className="atm-schedule-input-group">
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
                </div>
                <div className="atm-schedule-input-group">
                  <SelectControl
                    label="Unit"
                    value={campaignData.schedule_unit || "hour"}
                    options={[
                      { label: "Minutes", value: "minute" },
                      { label: "Hours", value: "hour" },
                      { label: "Days", value: "day" },
                      { label: "Weeks", value: "week" },
                    ]}
                    onChange={(value) =>
                      setCampaignData({
                        ...campaignData,
                        schedule_unit: value,
                      })
                    }
                    disabled={isLoading}
                  />
                </div>
                <div className="atm-schedule-time-group">
                  <TextControl
                    label="At time (optional)"
                    type="time"
                    value={campaignData.schedule_time || ""}
                    onChange={(value) =>
                      setCampaignData({
                        ...campaignData,
                        schedule_time: value,
                      })
                    }
                    disabled={isLoading}
                    help="Specify exact time for daily/weekly schedules"
                  />
                </div>
              </div>
            </div>

            <div className="atm-schedule-preview">
              <div className="atm-preview-card">
                <div className="atm-preview-stat">
                  <span className="atm-stat-number">
                    {calculatePostsPerDay()}
                  </span>
                  <span className="atm-stat-label">Posts per day</span>
                </div>
                <div className="atm-preview-stat">
                  <span className="atm-stat-number">
                    {calculatePostsPerDay() * 7}
                  </span>
                  <span className="atm-stat-label">Posts per week</span>
                </div>
                <div className="atm-preview-stat">
                  <span className="atm-stat-number">
                    {Math.round(calculatePostsPerDay() * 30.4)}
                  </span>
                  <span className="atm-stat-label">Posts per month</span>
                </div>
              </div>
            </div>
          </CardBody>
        )}
      </Card>

      {/* Publishing Settings */}
      <Card className="atm-settings-section">
        <div
          className="atm-section-header"
          onClick={() => toggleSection("publishing")}
        >
          <div className="atm-section-title">
            <span className="atm-section-icon">
              <svg
                width="20"
                height="20"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                <path
                  fillRule="evenodd"
                  d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                  clipRule="evenodd"
                />
              </svg>
            </span>
            <h3>Publishing & Organization</h3>
          </div>
          <button
            className={`atm-collapse-btn ${expandedSections.publishing ? "expanded" : ""}`}
          >
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        </div>

        {expandedSections.publishing && (
          <CardBody>
            <div className="atm-publishing-grid">
              <div className="atm-form-group">
                <SelectControl
                  label="Content Mode"
                  value={campaignData.content_mode || "draft"}
                  options={[
                    { label: "Save as Draft", value: "draft" },
                    { label: "Publish Immediately", value: "publish" },
                  ]}
                  onChange={(value) =>
                    setCampaignData({
                      ...campaignData,
                      content_mode: value,
                    })
                  }
                  disabled={isLoading}
                  help={
                    campaignData.content_mode === "draft"
                      ? "Posts will be saved as drafts for manual review"
                      : "Posts will be published automatically"
                  }
                />
              </div>

              <div className="atm-form-group">
                <SelectControl
                  label="Author"
                  value={campaignData.author_id || 1}
                  options={
                    authors.length > 0
                      ? authors.map((author) => ({
                          label: author.label,
                          value: author.value,
                        }))
                      : [{ label: "Default Author", value: 1 }]
                  }
                  onChange={(value) =>
                    setCampaignData({
                      ...campaignData,
                      author_id: parseInt(value),
                    })
                  }
                  disabled={isLoading}
                />
              </div>
            </div>

            <div className="atm-categories-section">
              <label className="atm-label">Categories</label>
              {categories.length > 0 ? (
                <div className="atm-category-grid">
                  {categories.map((category) => (
                    <div key={category.value} className="atm-category-item">
                      <input
                        type="checkbox"
                        id={`cat-${category.value}`}
                        checked={
                          campaignData.category_ids?.includes(category.value) ||
                          false
                        }
                        onChange={(e) => {
                          const currentIds = campaignData.category_ids || [];
                          const newIds = e.target.checked
                            ? [...currentIds, category.value]
                            : currentIds.filter((id) => id !== category.value);
                          setCampaignData({
                            ...campaignData,
                            category_ids: newIds,
                          });
                        }}
                        disabled={isLoading}
                      />
                      <label htmlFor={`cat-${category.value}`}>
                        {category.label}
                      </label>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="atm-no-categories">
                  No categories available. Categories will be loaded from
                  WordPress.
                </p>
              )}
            </div>
          </CardBody>
        )}
      </Card>

      {/* AI Settings */}
      <Card className="atm-settings-section">
        <div className="atm-section-header" onClick={() => toggleSection("ai")}>
          <div className="atm-section-title">
            <span className="atm-section-icon">
              <svg
                width="20"
                height="20"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clipRule="evenodd"
                />
              </svg>
            </span>
            <h3>AI & Content Settings</h3>
          </div>
          <button
            className={`atm-collapse-btn ${expandedSections.ai ? "expanded" : ""}`}
          >
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        </div>

        {expandedSections.ai && (
          <CardBody>
            <div className="atm-ai-grid">
              <SelectControl
                label="AI Model"
                value={campaignData.settings?.ai_model || ""}
                options={[
                  { label: "Use Default Model", value: "" },
                  { label: "GPT-4o (Recommended)", value: "openai/gpt-4o" },
                  {
                    label: "Claude 3.5 Sonnet",
                    value: "anthropic/claude-3-5-sonnet-20241022",
                  },
                  {
                    label: "GPT-4o Mini (Faster)",
                    value: "openai/gpt-4o-mini",
                  },
                ]}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: { ...campaignData.settings, ai_model: value },
                  })
                }
                disabled={isLoading}
              />

              <SelectControl
                label="Writing Style"
                value={campaignData.settings?.writing_style || "default_seo"}
                options={[
                  { label: "Standard SEO", value: "default_seo" },
                  { label: "Professional Business", value: "professional" },
                  { label: "Conversational", value: "conversational" },
                  { label: "Technical/Expert", value: "technical" },
                  { label: "News Reporting", value: "news" },
                  { label: "Educational", value: "educational" },
                ]}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      writing_style: value,
                    },
                  })
                }
                disabled={isLoading}
              />

              <SelectControl
                label="Creativity Level"
                value={campaignData.settings?.creativity_level || "high"}
                options={[
                  { label: "Conservative (Factual)", value: "low" },
                  { label: "Balanced", value: "medium" },
                  { label: "Creative (Dynamic)", value: "high" },
                ]}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      creativity_level: value,
                    },
                  })
                }
                disabled={isLoading}
              />

              <TextControl
                label="Target Word Count"
                type="number"
                placeholder="Leave empty for default"
                value={campaignData.settings?.word_count || ""}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      word_count: parseInt(value) || 0,
                    },
                  })
                }
                disabled={isLoading}
                help="Specify desired article length. Leave empty for automatic sizing."
              />
            </div>

            <Spacer marginTop={4} />

            <div className="atm-content-options">
              <ToggleControl
                label="Generate Featured Images"
                checked={campaignData.settings?.generate_image || false}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      generate_image: value,
                    },
                  })
                }
                disabled={isLoading}
                help="Automatically create AI-generated featured images for each post"
              />
            </div>
          </CardBody>
        )}
      </Card>

      {/* Advanced Settings */}
      <Card className="atm-settings-section">
        <div
          className="atm-section-header"
          onClick={() => toggleSection("advanced")}
        >
          <div className="atm-section-title">
            <span className="atm-section-icon">
              <svg
                width="20"
                height="20"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                  clipRule="evenodd"
                />
              </svg>
            </span>
            <h3>Advanced Settings</h3>
          </div>
          <button
            className={`atm-collapse-btn ${expandedSections.advanced ? "expanded" : ""}`}
          >
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        </div>

        {expandedSections.advanced && (
          <CardBody>
            <TextareaControl
              label="Custom Prompt (Optional)"
              placeholder="Leave empty to use the selected writing style. Custom prompts override the writing style template."
              value={campaignData.settings?.custom_prompt || ""}
              onChange={(value) =>
                setCampaignData({
                  ...campaignData,
                  settings: { ...campaignData.settings, custom_prompt: value },
                })
              }
              rows="6"
              disabled={isLoading}
              help="For very specific content requirements. This will override the selected writing style."
            />

            <Spacer marginTop={4} />

            <div className="atm-advanced-options">
              <ToggleControl
                label="Campaign Active"
                checked={campaignData.is_active !== false}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    is_active: value,
                  })
                }
                disabled={isLoading}
                help="Enable or disable this campaign"
              />

              <ToggleControl
                label="Skip Weekends"
                checked={campaignData.settings?.skip_weekends || false}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      skip_weekends: value,
                    },
                  })
                }
                disabled={isLoading}
                help="Pause campaign execution on Saturdays and Sundays"
              />

              <ToggleControl
                label="Quality Check Mode"
                checked={campaignData.settings?.quality_check || false}
                onChange={(value) =>
                  setCampaignData({
                    ...campaignData,
                    settings: {
                      ...campaignData.settings,
                      quality_check: value,
                    },
                  })
                }
                disabled={isLoading}
                help="Add extra validation to ensure higher content quality (slower execution)"
              />
            </div>
          </CardBody>
        )}
      </Card>
    </div>
  );
}

export default CampaignSettingsForm;
