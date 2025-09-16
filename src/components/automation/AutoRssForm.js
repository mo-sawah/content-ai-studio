import { useState } from "@wordpress/element";
import {
  ToggleControl,
  TextControl,
  TextareaControl,
  Button,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

const AutoRssForm = ({ campaignData, setCampaignData, isLoading }) => {
  const [debugInfo, setDebugInfo] = useState("");

  const handleDebug = () => {
    const info = {
      name: campaignData.name,
      keyword: campaignData.keyword,
      settings: campaignData.settings,
      rss_urls: campaignData.settings?.rss_urls,
      rss_urls_length: (campaignData.settings?.rss_urls || "").length,
      full_json: JSON.stringify(campaignData),
    };

    console.log("=== RSS FORM DEBUG ===", info);
    setDebugInfo(JSON.stringify(info, null, 2));
    alert("Debug info logged to console and displayed below");
  };

  return (
    <div className="atm-form-section">
      <h4>RSS Feeds Configuration</h4>
      <p className="components-base-control__help">
        Generate articles from RSS feed sources automatically using AI.
      </p>

      {/* Debug button - temporary */}
      <Button
        isSecondary
        onClick={handleDebug}
        style={{ marginBottom: "20px", backgroundColor: "#f0f0f0" }}
      >
        🔍 Debug RSS State
      </Button>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., RSS Tech Articles"
          value={campaignData.name || ""}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Keywords/Topic (Optional)"
          placeholder="e.g., artificial intelligence, climate change (leave empty for all feed content)"
          value={campaignData.keyword || ""}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
          help="If specified, only RSS entries containing these keywords will be used. Leave empty to use all entries from feeds."
        />
      </div>

      <div className="atm-grid-3">
        <CustomDropdown
          label="Content Extraction"
          text={
            campaignData.settings?.use_full_content
              ? "Full article content"
              : "RSS summary only"
          }
          options={[
            { label: "RSS summary only", value: false },
            { label: "Full article content", value: true },
          ]}
          onChange={(option) => {
            console.log("Content extraction changed to:", option.value);
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                use_full_content: option.value,
              },
            });
          }}
          disabled={isLoading}
          help="Full content provides more context but may be slower"
        />

        <CustomDropdown
          label="AI Model"
          text={campaignData.settings?.ai_model || "GPT-4o"}
          options={[
            { label: "GPT-4o (Best Quality)", value: "openai/gpt-4o" },
            {
              label: "Claude 3 Opus (Premium)",
              value: "anthropic/claude-3-opus",
            },
            {
              label: "Claude 3 Haiku (Fast)",
              value: "anthropic/claude-3-haiku",
            },
            { label: "Gemini Flash 1.5", value: "google/gemini-flash-1.5" },
            { label: "Llama 3 70B", value: "meta-llama/llama-3-70b-instruct" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                ai_model: option.value,
              },
            })
          }
          disabled={isLoading}
        />

        <CustomDropdown
          label="Word Count"
          text={campaignData.settings?.word_count || "800-1000 words"}
          options={[
            { label: "400-600 words", value: "400-600" },
            { label: "600-800 words", value: "600-800" },
            { label: "800-1000 words", value: "800-1000" },
            { label: "1000-1200 words", value: "1000-1200" },
            { label: "1200-1500 words", value: "1200-1500" },
            { label: "1500-2000 words", value: "1500-2000" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                word_count: option.value,
              },
            })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <CustomDropdown
          label="Writing Style"
          text={campaignData.settings?.writing_style || "Professional"}
          options={[
            { label: "Professional", value: "professional" },
            { label: "Conversational", value: "conversational" },
            { label: "Academic", value: "academic" },
            { label: "News Reporter", value: "news_reporter" },
            { label: "Blog Style", value: "blog_style" },
            { label: "Technical", value: "technical" },
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

        <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
          <ToggleControl
            label="Enable web search"
            checked={campaignData.settings?.enable_web_search !== false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: {
                  ...campaignData.settings,
                  enable_web_search: value,
                },
              })
            }
            disabled={isLoading}
            help="Use web search to verify information and add context"
          />

          <ToggleControl
            label="Skip duplicate articles"
            checked={campaignData.settings?.skip_duplicates !== false}
            onChange={(value) =>
              setCampaignData({
                ...campaignData,
                settings: { ...campaignData.settings, skip_duplicates: value },
              })
            }
            disabled={isLoading}
            help="Prevent generating articles from RSS entries that have already been used"
          />
        </div>
      </div>

      <TextareaControl
        label="RSS Feed URLs"
        placeholder="Enter RSS feed URLs, one per line:
https://feeds.feedburner.com/TechCrunch
https://rss.cnn.com/rss/edition.rss
https://feeds.bbci.co.uk/news/rss.xml"
        value={campaignData.settings?.rss_urls || ""}
        onChange={(value) => {
          console.log("🔥 RSS URLs onChange triggered:", value);
          console.log("🔥 Current settings before:", campaignData.settings);

          const newCampaignData = {
            ...campaignData,
            settings: {
              ...campaignData.settings,
              rss_urls: value,
            },
          };

          console.log("🔥 New campaign data:", newCampaignData);
          setCampaignData(newCampaignData);
        }}
        rows="8"
        disabled={isLoading}
        help="One RSS feed URL per line. The system will check these feeds and generate articles from new entries."
      />

      {/* Debug display */}
      <div
        style={{
          marginTop: "10px",
          padding: "10px",
          backgroundColor: "#e8f4f8",
          border: "1px solid #b3d9e6",
          fontSize: "12px",
          fontFamily: "monospace",
        }}
      >
        <strong>🔍 Debug Info:</strong>
        <br />
        RSS URLs: "{campaignData.settings?.rss_urls || "EMPTY"}"<br />
        Length: {(campaignData.settings?.rss_urls || "").length} characters
        <br />
        Settings keys: {Object.keys(campaignData.settings || {}).join(", ")}
      </div>

      {debugInfo && (
        <textarea
          value={debugInfo}
          readOnly
          rows="10"
          style={{
            width: "100%",
            marginTop: "10px",
            fontFamily: "monospace",
            fontSize: "11px",
          }}
        />
      )}
    </div>
  );
};

export default AutoRssForm;
