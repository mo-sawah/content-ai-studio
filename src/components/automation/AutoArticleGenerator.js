// src/components/automation/AutoArticleGenerator.js
import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
  Spinner,
  BaseControl,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

// Multi-Select Category Component
const CategoryMultiSelect = ({
  selectedCategories,
  onCategoriesChange,
  categories,
}) => {
  const [isOpen, setIsOpen] = useState(false);

  const handleCategoryToggle = (categoryId) => {
    const newSelected = selectedCategories.includes(categoryId)
      ? selectedCategories.filter((id) => id !== categoryId)
      : [...selectedCategories, categoryId];
    onCategoriesChange(newSelected);
  };

  const handleRemoveCategory = (categoryId) => {
    const newSelected = selectedCategories.filter((id) => id !== categoryId);
    onCategoriesChange(newSelected);
  };

  const getSelectedCategoryNames = () => {
    return categories.filter((cat) => selectedCategories.includes(cat.id));
  };

  return (
    <div className="atm-category-multiselect">
      <BaseControl
        label="Category"
        help="Select one or more categories for your content"
      >
        <div className="atm-category-selector-wrapper">
          <button
            type="button"
            className="atm-category-dropdown-trigger"
            onClick={() => setIsOpen(!isOpen)}
            style={{
              width: "100%",
              padding: "8px 12px",
              border: "1px solid #ddd",
              borderRadius: "4px",
              background: "#fff",
              textAlign: "left",
              cursor: "pointer",
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              minHeight: "40px",
            }}
          >
            <span>
              {selectedCategories.length === 0
                ? "Select Categories"
                : `${selectedCategories.length} categories selected`}
            </span>
            <span
              style={{
                transform: isOpen ? "rotate(180deg)" : "rotate(0deg)",
                transition: "transform 0.2s",
              }}
            >
              ▼
            </span>
          </button>

          {isOpen && (
            <div className="atm-category-selector">
              {categories.map((category) => (
                <div key={category.id} className="atm-category-item">
                  <input
                    type="checkbox"
                    id={`category-${category.id}`}
                    checked={selectedCategories.includes(category.id)}
                    onChange={() => handleCategoryToggle(category.id)}
                  />
                  <label htmlFor={`category-${category.id}`}>
                    {category.name}
                  </label>
                </div>
              ))}
            </div>
          )}
        </div>

        {selectedCategories.length > 0 && (
          <div className="atm-selected-categories">
            {getSelectedCategoryNames().map((category) => (
              <span key={category.id} className="atm-category-tag">
                {category.name}
                <span
                  className="remove"
                  onClick={() => handleRemoveCategory(category.id)}
                >
                  ×
                </span>
              </span>
            ))}
          </div>
        )}
      </BaseControl>
    </div>
  );
};

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
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [authorId, setAuthorId] = useState(1);
  const [authorLabel, setAuthorLabel] = useState("Current User");

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

      // Handle both new multi-select and old single category formats
      if (
        editingCampaign.category_ids &&
        Array.isArray(editingCampaign.category_ids)
      ) {
        setSelectedCategories(editingCampaign.category_ids);
      } else if (
        editingCampaign.category_id &&
        editingCampaign.category_id > 0
      ) {
        setSelectedCategories([editingCampaign.category_id]); // Convert single ID to array
      } else {
        setSelectedCategories([]);
      }

      setAuthorId(editingCampaign.author_id);

      // Update labels based on loaded data
      updateLabelsFromValues(settings, editingCampaign);
    } else {
      // Set default values for new campaigns
      if (window.atm_automation_data?.authors?.[0]) {
        setAuthorId(window.atm_automation_data.authors[0].id);
        setAuthorLabel(window.atm_automation_data.authors[0].name);
      }
    }
  }, [editingCampaign]);

  const updateLabelsFromValues = (settings, campaign) => {
    // Update AI model label
    const modelOptions = getModelOptions();
    const selectedModel = modelOptions.find(
      (opt) => opt.value === (settings.ai_model || "")
    );
    if (selectedModel) setAiModelLabel(selectedModel.label);

    // Update writing style label
    const styleOptions = getStyleOptions();
    const selectedStyle = styleOptions.find(
      (opt) => opt.value === (settings.writing_style || "default_seo")
    );
    if (selectedStyle) setWritingStyleLabel(selectedStyle.label);

    // Update other labels similarly...
    const creativityOptions = getCreativityOptions();
    const selectedCreativity = creativityOptions.find(
      (opt) => opt.value === (settings.creativity_level || "high")
    );
    if (selectedCreativity) setCreativityLevelLabel(selectedCreativity.label);

    const lengthOptions = getLengthOptions();
    const selectedLength = lengthOptions.find(
      (opt) => opt.value === (settings.word_count || "")
    );
    if (selectedLength) setWordCountLabel(selectedLength.label);

    const scheduleUnitOptions = getScheduleUnitOptions();
    const selectedUnit = scheduleUnitOptions.find(
      (opt) => opt.value === (campaign.schedule_unit || "hour")
    );
    if (selectedUnit) setScheduleUnitLabel(selectedUnit.label);

    const contentModeOptions = getContentModeOptions();
    const selectedMode = contentModeOptions.find(
      (opt) => opt.value === (campaign.content_mode || "publish")
    );
    if (selectedMode) setContentModeLabel(selectedMode.label);

    const authorOptions = getAuthorOptions();
    const selectedAuthor = authorOptions.find(
      (opt) => opt.value === (campaign.author_id || 1)
    );
    if (selectedAuthor) setAuthorLabel(selectedAuthor.label);
  };

  // Safe data access with fallbacks
  const getModelOptions = () => {
    const models = window.atm_automation_data?.article_models || {};
    return [
      { label: "Use Default Model", value: "" },
      ...Object.entries(models).map(([value, label]) => ({ label, value })),
    ];
  };

  const getStyleOptions = () => {
    const styles = window.atm_automation_data?.writing_styles || {};
    return Object.entries(styles).map(([value, data]) => ({
      label: data.label || value,
      value,
    }));
  };

  const getCreativityOptions = () => [
    { label: "High Creativity", value: "high" },
    { label: "Medium Creativity", value: "medium" },
    { label: "Low Creativity", value: "low" },
  ];

  const getLengthOptions = () => [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Standard (~800 words)", value: "800" },
    { label: "Medium (~1200 words)", value: "1200" },
    { label: "Long (~2000 words)", value: "2000" },
  ];

  const getScheduleUnitOptions = () => [
    { label: "Minutes", value: "minute" },
    { label: "Hours", value: "hour" },
    { label: "Days", value: "day" },
    { label: "Weeks", value: "week" },
  ];

  const getContentModeOptions = () => [
    { label: "Auto-publish Immediately", value: "publish" },
    { label: "Save as Drafts", value: "draft" },
    { label: "Queue for Later", value: "queue" },
  ];

  const getAuthorOptions = () => {
    const authors = window.atm_automation_data?.authors || [];
    return authors.map((author) => ({ label: author.name, value: author.id }));
  };

  const handleSaveCampaign = async () => {
    if (!campaignName.trim() || !keyword.trim()) {
      setStatusMessage("Campaign name and keyword are required.");
      return;
    }

    if (selectedCategories.length === 0) {
      setStatusMessage("Please select at least one category.");
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
        category_ids: selectedCategories,
        author_id: authorId,
        is_active: true,
      };

      const response = await jQuery.ajax({
        url: window.atm_automation_data?.ajax_url || ajaxurl,
        type: "POST",
        data: {
          action: "atm_save_automation_campaign",
          nonce: window.atm_automation_data?.nonce,
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
        url: window.atm_automation_data?.ajax_url || ajaxurl,
        type: "POST",
        data: {
          action: "atm_run_automation_campaign_now",
          nonce: window.atm_automation_data?.nonce,
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

  if (!window.atm_automation_data) {
    return (
      <div className="atm-form-container">
        <div className="atm-status-message error">
          Automation data not loaded. Please refresh the page.
        </div>
      </div>
    );
  }

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
            options={getModelOptions()}
            onChange={(option) => {
              setAiModel(option.value);
              setAiModelLabel(option.label);
            }}
            disabled={isLoading}
          />

          <CustomDropdown
            label="Writing Style"
            text={writingStyleLabel}
            options={getStyleOptions()}
            onChange={(option) => {
              setWritingStyle(option.value);
              setWritingStyleLabel(option.label);
            }}
            disabled={isLoading}
          />

          <CustomDropdown
            label="Creativity Level"
            text={creativityLevelLabel}
            options={getCreativityOptions()}
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
          options={getLengthOptions()}
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
            <div className="schedule-input-row">
              <input
                type="number"
                min={scheduleUnit === "minute" ? 10 : 1}
                value={scheduleValue}
                onChange={(e) =>
                  setScheduleValue(parseInt(e.target.value) || 1)
                }
                disabled={isLoading}
              />
              <div>
                <CustomDropdown
                  text={scheduleUnitLabel}
                  options={getScheduleUnitOptions()}
                  onChange={(option) => {
                    setScheduleUnit(option.value);
                    setScheduleUnitLabel(option.label);
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
            options={getContentModeOptions()}
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
          <CategoryMultiSelect
            selectedCategories={selectedCategories}
            onCategoriesChange={setSelectedCategories}
            categories={window.atm_automation_data?.categories || []}
          />

          <CustomDropdown
            label="Author"
            text={authorLabel}
            options={getAuthorOptions()}
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
            disabled={
              isLoading ||
              !campaignName.trim() ||
              !keyword.trim() ||
              selectedCategories.length === 0
            }
          >
            {isLoading ? (
              <>
                <Spinner />
                Saving...
              </>
            ) : editingCampaign ? (
              "Update Campaign"
            ) : (
              "Create Campaign"
            )}
          </Button>

          {editingCampaign && (
            <Button isSecondary onClick={handleTestRun} disabled={isLoading}>
              Test Run Now
            </Button>
          )}

          <Button
            isSecondary
            onClick={() => setActiveView("campaigns")}
            disabled={isLoading}
          >
            Cancel
          </Button>
        </div>

        {statusMessage && (
          <div
            className={`atm-status-message ${
              statusMessage.includes("successfully")
                ? "success"
                : statusMessage.includes("Error")
                  ? "error"
                  : "info"
            }`}
          >
            {statusMessage}
          </div>
        )}
      </div>
    </div>
  );
}

export default AutoArticleGenerator;
