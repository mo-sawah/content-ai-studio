import { useState } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
  Card,
  CardBody,
  CardHeader,
  Flex,
  FlexItem,
  __experimentalSpacer as Spacer,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

const SettingsSection = ({ title, description, children, icon }) => (
  <Card className="atm-settings-card">
    <CardHeader>
      <Flex align="center" gap={3}>
        <span className="atm-section-icon">{icon}</span>
        <div>
          <h4 className="atm-section-title">{title}</h4>
          {description && (
            <p className="atm-section-description">{description}</p>
          )}
        </div>
      </Flex>
    </CardHeader>
    <CardBody>{children}</CardBody>
  </Card>
);

function CampaignSettingsForm({ campaignData, setCampaignData, isLoading }) {
  const schedulePresets = [
    {
      label: "Every 15 minutes",
      value: { schedule_value: 15, schedule_unit: "minute" },
    },
    {
      label: "Every 30 minutes",
      value: { schedule_value: 30, schedule_unit: "minute" },
    },
    {
      label: "Every hour",
      value: { schedule_value: 1, schedule_unit: "hour" },
    },
    {
      label: "Every 2 hours",
      value: { schedule_value: 2, schedule_unit: "hour" },
    },
    {
      label: "Every 6 hours",
      value: { schedule_value: 6, schedule_unit: "hour" },
    },
    {
      label: "Every 12 hours",
      value: { schedule_value: 12, schedule_unit: "hour" },
    },
    { label: "Daily", value: { schedule_value: 1, schedule_unit: "day" } },
    {
      label: "Every 2 days",
      value: { schedule_value: 2, schedule_unit: "day" },
    },
    { label: "Weekly", value: { schedule_value: 1, schedule_unit: "week" } },
    { label: "Custom", value: null },
  ];

  const currentScheduleLabel =
    schedulePresets.find(
      (preset) =>
        preset.value &&
        preset.value.schedule_value === campaignData.schedule_value &&
        preset.value.schedule_unit === campaignData.schedule_unit
    )?.label || "Custom";

  const handleSchedulePreset = (preset) => {
    if (preset.value) {
      setCampaignData({
        ...campaignData,
        schedule_value: preset.value.schedule_value,
        schedule_unit: preset.value.schedule_unit,
      });
    }
  };

  return (
    <div className="atm-campaign-settings">
      {/* Schedule Settings */}
      <SettingsSection
        title="Schedule Configuration"
        description="Control when and how often your campaign runs"
        icon="⏰"
      >
        <div className="atm-schedule-grid">
          <div className="atm-schedule-presets">
            <label className="atm-field-label">Quick Presets</label>
            <CustomDropdown
              text={currentScheduleLabel}
              options={schedulePresets}
              onChange={handleSchedulePreset}
              disabled={isLoading}
            />
          </div>

          <div className="atm-schedule-custom">
            <label className="atm-field-label">Custom Schedule</label>
            <Flex gap={2} align="end">
              <FlexItem>
                <TextControl
                  label="Every"
                  type="number"
                  value={campaignData.schedule_value}
                  onChange={(value) =>
                    setCampaignData({
                      ...campaignData,
                      schedule_value: Math.max(1, parseInt(value) || 1),
                    })
                  }
                  disabled={isLoading}
                  min="1"
                  className="atm-schedule-value"
                />
              </FlexItem>
              <FlexItem>
                <CustomDropdown
                  text={campaignData.schedule_unit}
                  options={[
                    { label: "Minutes", value: "minute" },
                    { label: "Hours", value: "hour" },
                    { label: "Days", value: "day" },
                    { label: "Weeks", value: "week" },
                  ]}
                  onChange={(option) =>
                    setCampaignData({
                      ...campaignData,
                      schedule_unit: option.value,
                    })
                  }
                  disabled={isLoading}
                />
              </FlexItem>
            </Flex>
          </div>
        </div>

        <Spacer marginTop={4} />

        <div className="atm-schedule-info">
          <div className="atm-info-card">
            <span className="atm-info-icon">📊</span>
            <div>
              <strong>Expected Output:</strong>
              <br />~
              {Math.round(
                (24 * 60) /
                  (campaignData.schedule_value *
                    (campaignData.schedule_unit === "minute"
                      ? 1
                      : campaignData.schedule_unit === "hour"
                        ? 60
                        : campaignData.schedule_unit === "day"
                          ? 1440
                          : 10080))
              )}{" "}
              posts per day
            </div>
          </div>
        </div>
      </SettingsSection>

      {/* Publishing Settings */}
      <SettingsSection
        title="Publishing & Organization"
        description="Control how and where your content is published"
        icon="📝"
      >
        <div className="atm-publishing-grid">
          <div className="atm-content-mode">
            <CustomDropdown
              label="Content Mode"
              text={
                campaignData.content_mode === "draft"
                  ? "Save as Draft"
                  : "Publish Immediately"
              }
              options={[
                { label: "Save as Draft", value: "draft" },
                { label: "Publish Immediately", value: "publish" },
              ]}
              onChange={(option) =>
                setCampaignData({
                  ...campaignData,
                  content_mode: option.value,
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

          <div className="atm-author-selection">
            <CustomDropdown
              label="Author"
              text={
                atm_automation_data?.authors?.find(
                  (a) => a.value == campaignData.author_id
                )?.label || "Select Author"
              }
              options={atm_automation_data?.authors || []}
              onChange={(option) =>
                setCampaignData({
                  ...campaignData,
                  author_id: option.value,
                })
              }
              disabled={isLoading}
            />
          </div>

          <div className="atm-category-selection">
            {/* Category selection component - implement based on your existing CategoryMultiSelect */}
            <label className="atm-field-label">Categories</label>
            <div className="atm-category-tags">
              {/* Display selected categories as tags */}
              <span className="atm-placeholder-text">Select categories...</span>
            </div>
          </div>
        </div>
      </SettingsSection>

      {/* AI & Content Settings */}
      <SettingsSection
        title="AI & Content Generation"
        description="Configure AI model, writing style, and content parameters"
        icon="🤖"
      >
        <div className="atm-ai-settings-grid">
          <CustomDropdown
            label="AI Model"
            text={campaignData.settings?.ai_model || "Use Default Model"}
            options={[
              { label: "Use Default Model", value: "" },
              { label: "GPT-4o (Recommended)", value: "openai/gpt-4o" },
              {
                label: "Claude 3.5 Sonnet",
                value: "anthropic/claude-3-5-sonnet-20241022",
              },
              { label: "GPT-4o Mini (Faster)", value: "openai/gpt-4o-mini" },
            ]}
            onChange={(option) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, ai_model: option.value },
              })
            }
            disabled={isLoading}
          />

          <CustomDropdown
            label="Writing Style"
            text={getWritingStyleLabel(campaignData.settings?.writing_style)}
            options={[
              { label: "Standard SEO", value: "default_seo" },
              { label: "Professional Business", value: "professional" },
              { label: "Conversational", value: "conversational" },
              { label: "Technical/Expert", value: "technical" },
              { label: "News Reporting", value: "news" },
              { label: "Educational", value: "educational" },
            ]}
            onChange={(option) =>
              setCampaignData({
                ...campaignData,
                settings: {
                  ...campaignData.settings,
                  writing_style: option.value,
                },
              })
            }
            disabled={isLoading}
          />

          <CustomDropdown
            label="Creativity Level"
            text={getCreativityLabel(campaignData.settings?.creativity_level)}
            options={[
              { label: "Conservative (Factual)", value: "low" },
              { label: "Balanced", value: "medium" },
              { label: "Creative (Dynamic)", value: "high" },
            ]}
            onChange={(option) =>
              setCampaignData({
                ...campaignData,
                settings: {
                  ...campaignData.settings,
                  creativity_level: option.value,
                },
              })
            }
            disabled={isLoading}
          />
        </div>

        <Spacer marginTop={4} />

        <div className="atm-content-params">
          <TextControl
            label="Target Word Count"
            type="number"
            placeholder="Leave empty for default (800-1200 words)"
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

          <ToggleControl
            label="Generate Featured Images"
            help="Automatically create AI-generated featured images for each post"
            checked={campaignData.settings?.generate_image || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, generate_image: value },
              })
            }
            disabled={isLoading}
          />
        </div>
      </SettingsSection>

      {/* Advanced Settings */}
      <SettingsSection
        title="Advanced Configuration"
        description="Custom prompts and advanced automation settings"
        icon="⚙️"
      >
        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Leave empty to use the selected writing style. If you write a prompt here, it will be used instead of the writing style template."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, custom_prompt: value },
            })
          }
          rows="6"
          disabled={isLoading}
          help="Custom prompts override the selected writing style. Use this for very specific content requirements."
        />

        <Spacer marginTop={4} />

        <div className="atm-advanced-toggles">
          <ToggleControl
            label="Campaign Active"
            help="Enable or disable this campaign"
            checked={campaignData.is_active}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                is_active: value,
              })
            }
            disabled={isLoading}
          />

          <ToggleControl
            label="Skip Weekends"
            help="Pause campaign execution on Saturdays and Sundays"
            checked={campaignData.settings?.skip_weekends || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, skip_weekends: value },
              })
            }
            disabled={isLoading}
          />

          <ToggleControl
            label="Quality Check Mode"
            help="Add extra validation to ensure higher content quality (slower execution)"
            checked={campaignData.settings?.quality_check || false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, quality_check: value },
              })
            }
            disabled={isLoading}
          />
        </div>
      </SettingsSection>
    </div>
  );
}

// Helper functions
const getWritingStyleLabel = (style) => {
  const styles = {
    default_seo: "Standard SEO",
    professional: "Professional Business",
    conversational: "Conversational",
    technical: "Technical/Expert",
    news: "News Reporting",
    educational: "Educational",
  };
  return styles[style] || "Standard SEO";
};

const getCreativityLabel = (level) => {
  const levels = {
    low: "Conservative (Factual)",
    medium: "Balanced",
    high: "Creative (Dynamic)",
  };
  return levels[level] || "Creative (Dynamic)";
};

export default CampaignSettingsForm;
