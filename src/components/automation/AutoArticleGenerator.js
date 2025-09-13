// src/components/automation/AutoArticleGenerator.js
import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

function AutoArticleGenerator({ setActiveView, editingCampaign }) {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  // Campaign basic settings
  const [campaignName, setCampaignName] = useState("");
  const [keyword, setKeyword] = useState("");

  // Content settings
  const [aiModel, setAiModel] = useState("");
  const [aiModelLabel, setAiModelLabel] = useState("Use Default Model");
  const [writingStyle, setWritingStyle] = useState("default_seo");
  const [writingStyleLabel, setWritingStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );
  const [creativityLevel, setCreativityLevel] = useState("high");
  const [creativityLevelLabel, setCreativityLevelLabel] =
    useState("High Creativity");
  const [wordCount, setWordCount] = useState("");
  const [wordCountLabel, setWordCountLabel] = useState("Default");
  const [customPrompt, setCustomPrompt] = useState("");
  const [generateImage, setGenerateImage] = useState(false);

  // Schedule settings
  const [scheduleValue, setScheduleValue] = useState(1);
  const [scheduleUnit, setScheduleUnit] = useState("hour");
  const [scheduleUnitLabel, setScheduleUnitLabel] = useState("Hours");

  // Publishing settings
  const [contentMode, setContentMode] = useState("publish");
  const [contentModeLabel, setContentModeLabel] = useState(
    "Auto-publish Immediately"
  );
  const [categoryId, setCategoryId] = useState(0);
  const [categoryLabel, setCategoryLabel] = useState("Select Category");
  const [authorId, setAuthorId] = useState(
    atm_automation_data?.authors?.[0]?.id || 1
  );
  const [authorLabel, setAuthorLabel] = useState(
    atm_automation_data?.authors?.[0]?.name || "Current User"
  );

  // Load editing campaign data
  useEffect(() => {
    if (editingCampaign) {
      setCampaignName(editingCampaign.name);
      setKeyword(editingCampaign.keyword);

      const settings = editingCampaign.settings || {};
      setAiModel(settings.ai_model || "");
      setWritingStyle(settings.writing_style || "default_seo");
      setCreativityLevel(settings.creativity_level || "high");
      setWordCount(settings.word_count || "");
      setCustomPrompt(settings.custom_prompt || "");
      setGenerateImage(settings.generate_image || false);

      setScheduleValue(editingCampaign.schedule_value);
      setScheduleUnit(editingCampaign.schedule_unit);
      setContentMode(editingCampaign.content_mode);
      setCategoryId(editingCampaign.category_id);
      setAuthorId(editingCampaign.author_id);
    }
  }, [editingCampaign]);

  // Dropdown options
  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...Object.entries(atm_automation_data?.article_models || {}).map(
      ([value, label]) => ({
        label,
        value,
      })
    ),
  ];

  const styleOptions = Object.entries(
    atm_automation_data?.writing_styles || {}
  ).map(([value, data]) => ({ label: data.label || value, value }));

  const creativityOptions = [
    { label: "High Creativity", value: "high" },
    { label: "Medium Creativity", value: "medium" },
    { label: "Low Creativity", value: "low" },
  ];

  const lengthOptions = [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Standard (~800 words)", value: "800" },
    { label: "Medium (~1200 words)", value: "1200" },
    { label: "Long (~2000 words)", value: "2000" },
  ];

  const scheduleUnitOptions = [
    { label: "Minutes", value: "minute" },
    { label: "Hours", value: "hour" },
    { label: "Days", value: "day" },
    { label: "Weeks", value: "week" },
  ];

  const contentModeOptions = [
    { label: "Auto-publish Immediately", value: "publish" },
    { label: "Save as Drafts", value: "draft" },
    { label: "Queue for Later", value: "queue" },
  ];

  const categoryOptions = [
    { label: "Select Category", value: 0 },
    ...(atm_automation_data?.categories || []).map((cat) => ({
      label: cat.name,
      value: cat.id,
    })),
  ];

  const authorOptions = (atm_automation_data?.authors || []).map((author) => ({
    label: author.name,
    value: author.id,
  }));

  const handleSaveCampaign = async () => {
    if (!campaignName.trim() || !keyword.trim()) {
      setStatusMessage("Campaign name and keyword are required.");
      return;
    }

    // Validate minimum schedule
    const minValues = { minute: 10, hour: 1, day: 1, week: 1 };
    if (scheduleValue < minValues[scheduleUnit]) {
      setStatusMessage(
        `Minimum value for ${scheduleUnit}s is ${minValues[scheduleUnit]}.`
      );
      return;
    }

    setIsLoading(true);
    setStatusMessage("Saving campaign...");

    try {
      const campaignData = {
        campaign_id: editingCampaign?.id || 0,
        name: campaignName,
        type: "articles",
        keyword: keyword,
        settings: {
          ai_model: aiModel,
          writing_style: writingStyle,
          creativity_level: creativityLevel,
          word_count: wordCount ? parseInt(wordCount) : 0,
          custom_prompt: customPrompt,
          generate_image: generateImage,
        },
        schedule_type: "interval",
        schedule_value: scheduleValue,
        schedule_unit: scheduleUnit,
        content_mode: contentMode,
        category_id: categoryId,
        author_id: authorId,
        is_active: true,
      };

      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_save_automation_campaign",
          nonce: atm_automation_data.nonce,
          ...campaignData,
        },
      });

      if (response.success) {
        setStatusMessage("Campaign saved successfully!");
        setTimeout(() => {
          setActiveView("campaigns");
        }, 1000);
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleTestRun = async () => {
    if (!editingCampaign?.id) {
      setStatusMessage("Please save the campaign first before testing.");
      return;
    }

    setIsLoading(true);
    setStatusMessage("Running test execution...");

    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_run_automation_campaign_now",
          nonce: atm_automation_data.nonce,
          campaign_id: editingCampaign.id,
        },
      });

      if (response.success) {
        setStatusMessage("Test run completed successfully!");
        if (response.data.post_url) {
          setTimeout(() => {
            window.open(response.data.post_url, "_blank");
          }, 1000);
        }
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <h4>Campaign Configuration</h4>
        <p className="components-base-control__help">
          Configure an automated article generation campaign that will create
          content based on your keyword and settings.
        </p>

        <div className="atm-grid-2">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Daily Tech Articles"
            value={campaignName}
            onChange={setCampaignName}
            disabled={isLoading}
            help="A descriptive name for this automation campaign"
          />

          <TextControl
            label="Keyword/Topic"
            placeholder="e.g., artificial intelligence"
            value={keyword}
            onChange={setKeyword}
            disabled={isLoading}
            help="Main topic or keyword for article generation"
          />
        </div>

        <h4>Content Settings</h4>
        <div className="atm-grid-3">
          <CustomDropdown
            label="AI Model"
            text={aiModelLabel}
            options={modelOptions}
            onChange={(option) => {
              setAiModel(option.value);
              setAiModelLabel(option.label);
            }}
            disabled={isLoading}
          />

          <CustomDropdown
            label="Writing Style"
            text={writingStyleLabel}
            options={styleOptions}
            onChange={(option) => {
              setWritingStyle(option.value);
              setWritingStyleLabel(option.label);
            }}
            disabled={isLoading}
          />

          <CustomDropdown
            label="Creativity Level"
            text={creativityLevelLabel}
            options={creativityOptions}
            onChange={(option) => {
              setCreativityLevel(option.value);
              setCreativityLevelLabel(option.label);
            }}
            disabled={isLoading}
          />
        </div>

        <CustomDropdown
          label="Word Count"
          text={wordCountLabel}
          options={lengthOptions}
          onChange={(option) => {
            setWordCount(option.value);
            setWordCountLabel(option.label);
          }}
          disabled={isLoading}
        />

        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will be used instead."
          value={customPrompt}
          onChange={setCustomPrompt}
          rows={6}
          disabled={isLoading}
        />

        <ToggleControl
          label="Generate featured images"
          help={
            generateImage
              ? "Featured images will be generated for each article"
              : "Articles will be generated without featured images"
          }
          checked={generateImage}
          onChange={setGenerateImage}
          disabled={isLoading}
        />

        <h4>Schedule Settings</h4>
        <div className="atm-grid-2">
          <div>
            <label className="components-base-control__label">Run Every</label>
            <div style={{ display: "flex", gap: "8px", alignItems: "end" }}>
              <input
                type="number"
                min={scheduleUnit === "minute" ? 10 : 1}
                value={scheduleValue}
                onChange={(e) =>
                  setScheduleValue(parseInt(e.target.value) || 1)
                }
                disabled={isLoading}
                style={{
                  width: "80px",
                  height: "40px",
                  padding: "0 8px",
                  border: "1px solid #ddd",
                  borderRadius: "4px",
                }}
              />
              <div style={{ flex: 1 }}>
                <CustomDropdown
                  text={scheduleUnitLabel}
                  options={scheduleUnitOptions}
                  onChange={(option) => {
                    setScheduleUnit(option.value);
                    setScheduleUnitLabel(option.label);
                    // Auto-adjust value if below minimum
                    const minValues = { minute: 10, hour: 1, day: 1, week: 1 };
                    if (scheduleValue < minValues[option.value]) {
                      setScheduleValue(minValues[option.value]);
                    }
                  }}
                  disabled={isLoading}
                />
              </div>
            </div>
            <p className="components-base-control__help">
              Minimum:{" "}
              {scheduleUnit === "minute" ? "10 minutes" : `1 ${scheduleUnit}`}
            </p>
          </div>

          <CustomDropdown
            label="Content Mode"
            text={contentModeLabel}
            options={contentModeOptions}
            onChange={(option) => {
              setContentMode(option.value);
              setContentModeLabel(option.label);
            }}
            disabled={isLoading}
            helpText="How to handle generated content"
          />
        </div>

        <h4>Publishing Settings</h4>
        <div className="atm-grid-2">
          <CustomDropdown
            label="Category"
            text={categoryLabel}
            options={categoryOptions}
            onChange={(option) => {
              setCategoryId(option.value);
              setCategoryLabel(option.label);
            }}
            disabled={isLoading}
          />

          <CustomDropdown
            label="Author"
            text={authorLabel}
            options={authorOptions}
            onChange={(option) => {
              setAuthorId(option.value);
              setAuthorLabel(option.label);
            }}
            disabled={isLoading}
          />
        </div>

        <div className="atm-form-actions">
          <Button
            isPrimary
            onClick={handleSaveCampaign}
            disabled={isLoading || !campaignName.trim() || !keyword.trim()}
          >
            {isLoading
              ? "Saving..."
              : editingCampaign
                ? "Update Campaign"
                : "Create Campaign"}
          </Button>

          {editingCampaign && (
            <Button isSecondary onClick={handleTestRun} disabled={isLoading}>
              Test Run Now
            </Button>
          )}

          <Button
            isTertiary
            onClick={() => setActiveView("campaigns")}
            disabled={isLoading}
          >
            Cancel
          </Button>
        </div>

        {statusMessage && (
          <p
            className={`atm-status-message ${
              statusMessage.includes("successfully")
                ? "success"
                : statusMessage.includes("Error")
                  ? "error"
                  : "info"
            }`}
          >
            {statusMessage}
          </p>
        )}
      </div>
    </div>
  );
}

export default AutoArticleGenerator;
