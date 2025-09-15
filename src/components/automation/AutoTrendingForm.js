import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  ToggleControl,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

function AutoTrendingForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Local state for dropdown labels
  const [regionLabel, setRegionLabel] = useState("Global");
  const [languageLabel, setLanguageLabel] = useState("English");
  const [writingStyleLabel, setWritingStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );
  const [creativityLabel, setCreativityLabel] = useState("Creative (Dynamic)");
  const [wordCountLabel, setWordCountLabel] = useState("Default");

  // Options for dropdowns
  const regionOptions = [
    { label: "Global", value: "" },
    { label: "United States", value: "US" },
    { label: "United Kingdom", value: "GB" },
    { label: "Canada", value: "CA" },
    { label: "Australia", value: "AU" },
    { label: "Germany", value: "DE" },
    { label: "France", value: "FR" },
    { label: "India", value: "IN" },
    { label: "Brazil", value: "BR" },
    { label: "Japan", value: "JP" },
    { label: "South Korea", value: "KR" },
    { label: "Italy", value: "IT" },
    { label: "Spain", value: "ES" },
  ];

  const languageOptions = [
    { label: "English", value: "en" },
    { label: "Spanish", value: "es" },
    { label: "French", value: "fr" },
    { label: "German", value: "de" },
    { label: "Portuguese", value: "pt" },
    { label: "Italian", value: "it" },
    { label: "Japanese", value: "ja" },
    { label: "Korean", value: "ko" },
    { label: "Chinese", value: "zh" },
  ];

  const styleOptions = [
    { label: "Standard / SEO-Optimized", value: "default_seo" },
    { label: "Professional Business", value: "professional" },
    { label: "Conversational & Friendly", value: "conversational" },
    { label: "Technical / Expert", value: "technical" },
    { label: "News / Journalistic", value: "news" },
    { label: "Educational / Tutorial", value: "educational" },
  ];

  const wordCountOptions = [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Medium (~800 words)", value: "800" },
    { label: "Long (~1200 words)", value: "1200" },
    { label: "Very Long (~2000 words)", value: "2000" },
  ];

  const creativityOptions = [
    { label: "Conservative (Factual)", value: "low" },
    { label: "Balanced", value: "medium" },
    { label: "Creative (Dynamic)", value: "high" },
  ];

  // Initialize labels on mount
  useEffect(() => {
    const currentRegion = regionOptions.find(
      (option) =>
        option.value === (campaignData.settings?.trending_region || "")
    );
    if (currentRegion) {
      setRegionLabel(currentRegion.label);
    }

    const currentLanguage = languageOptions.find(
      (option) =>
        option.value === (campaignData.settings?.trending_language || "en")
    );
    if (currentLanguage) {
      setLanguageLabel(currentLanguage.label);
    }

    const currentStyle = styleOptions.find(
      (option) =>
        option.value === (campaignData.settings?.writing_style || "default_seo")
    );
    if (currentStyle) {
      setWritingStyleLabel(currentStyle.label);
    }

    const currentWordCount = wordCountOptions.find(
      (option) =>
        option.value === (campaignData.settings?.word_count?.toString() || "")
    );
    if (currentWordCount) {
      setWordCountLabel(currentWordCount.label);
    }

    const currentCreativity = creativityOptions.find(
      (option) =>
        option.value === (campaignData.settings?.creativity_level || "high")
    );
    if (currentCreativity) {
      setCreativityLabel(currentCreativity.label);
    }
  }, [campaignData.settings]);

  // Update campaign data helpers
  const updateSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      settings: {
        ...prev.settings,
        [key]: value,
      },
    }));
  };

  const updateBasicSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  return (
    <div className="atm-form-container">
      {/* Content Configuration Section */}
      <div className="atm-form-section">
        <h3>Trending Content Configuration</h3>

        <TextControl
          label="Base Keyword"
          placeholder="e.g., artificial intelligence, renewable energy, cryptocurrency"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The system will find trending topics related to this keyword and generate unique articles automatically"
        />

        <TextControl
          label="Custom Title Template (Optional)"
          placeholder="Leave empty for AI-generated trending titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Optional title template. Leave empty to let AI generate titles from trending topics"
        />

        {/* Trending Settings Grid */}
        <div className="atm-grid-3">
          <CustomDropdown
            label="Region"
            text={regionLabel}
            options={regionOptions}
            onChange={(option) => {
              updateSetting("trending_region", option.value);
              setRegionLabel(option.label);
            }}
            helpText="Geographic region for trending topics discovery"
          />

          <CustomDropdown
            label="Language"
            text={languageLabel}
            options={languageOptions}
            onChange={(option) => {
              updateSetting("trending_language", option.value);
              setLanguageLabel(option.label);
            }}
            helpText="Language for trend search and article generation"
          />

          <CustomDropdown
            label="Writing Style"
            text={writingStyleLabel}
            options={styleOptions}
            onChange={(option) => {
              updateSetting("writing_style", option.value);
              setWritingStyleLabel(option.label);
            }}
            helpText="Select the tone and style for trending articles"
          />
        </div>

        {/* Advanced AI Settings */}
        <div className="atm-grid-2">
          <CustomDropdown
            label="Word Count"
            text={wordCountLabel}
            options={wordCountOptions}
            onChange={(option) => {
              updateSetting("word_count", parseInt(option.value) || 0);
              setWordCountLabel(option.label);
            }}
            helpText="Target article length for trending content"
          />

          <CustomDropdown
            label="Creativity Level"
            text={creativityLabel}
            options={creativityOptions}
            onChange={(option) => {
              updateSetting("creativity_level", option.value);
              setCreativityLabel(option.label);
            }}
            helpText="Control how creative vs factual the trending content should be"
          />
        </div>

        {/* Trending-Specific Options */}
        <div className="atm-form-section">
          <h4>Trending Intelligence Settings</h4>
          <div className="atm-inline-toggles">
            <ToggleControl
              label="Smart Angle Detection"
              checked={campaignData.settings?.smart_angles !== false}
              onChange={(value) => updateSetting("smart_angles", value)}
              help="AI automatically finds unique angles for trending topics to avoid duplicate content"
            />
            <ToggleControl
              label="Real-time Trend Monitoring"
              checked={campaignData.settings?.real_time_trends !== false}
              onChange={(value) => updateSetting("real_time_trends", value)}
              help="Check for the latest trending topics at each campaign execution"
            />
            <ToggleControl
              label="Include Breaking News"
              checked={campaignData.settings?.include_breaking_news || false}
              onChange={(value) =>
                updateSetting("include_breaking_news", value)
              }
              help="Prioritize breaking news and urgent trending topics when available"
            />
          </div>
        </div>

        {/* Trend Uniqueness Settings */}
        <div className="atm-form-section">
          <h4>Content Uniqueness Control</h4>

          <CustomDropdown
            label="Angle Refresh Period"
            text={
              campaignData.settings?.angle_refresh_days
                ? `${campaignData.settings.angle_refresh_days} days`
                : "7 days"
            }
            options={[
              { label: "1 day", value: "1" },
              { label: "3 days", value: "3" },
              { label: "7 days", value: "7" },
              { label: "14 days", value: "14" },
              { label: "30 days", value: "30" },
              { label: "No restrictions", value: "0" },
            ]}
            onChange={(option) => {
              updateSetting("angle_refresh_days", parseInt(option.value));
            }}
            helpText="How often the same trending topic can be covered from different angles"
          />

          <TextControl
            label="Minimum Trend Score"
            type="number"
            placeholder="1000"
            value={campaignData.settings?.min_trend_score || ""}
            onChange={(value) =>
              updateSetting("min_trend_score", parseInt(value) || 0)
            }
            help="Minimum popularity score for trending topics (higher = more popular trends only)"
          />
        </div>

        {/* Custom Instructions */}
        <TextareaControl
          label="Custom Trending Instructions (Optional)"
          placeholder="Add specific requirements for trending article automation..."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={5}
          help="Additional instructions for how to handle trending topics and generate unique content angles"
        />
      </div>
    </div>
  );
}

export default AutoTrendingForm;
