import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  TextControl,
  TextareaControl,
  ToggleControl,
  Spinner,
} from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";
// IMPORT a missing component that was causing the crash
import AutomationSettingsForm from "./AutomationSettingsForm";
import AutoNewsForm from "./AutoNewsForm";

// News Search Form (Google News/SerpAPI)
const NewsSearchForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Google News Search Configuration</h4>
      <p className="components-base-control__help">
        Search Google News and automatically generate articles from trending
        news sources.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Daily Tech News"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Search Keywords"
          placeholder="e.g., artificial intelligence, climate change"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-3">
        <CustomDropdown
          label="Article Language"
          text={campaignData.settings?.article_language || "English"}
          options={[
            { label: "English", value: "English" },
            { label: "Spanish", value: "Spanish" },
            { label: "French", value: "French" },
            { label: "German", value: "German" },
            { label: "Italian", value: "Italian" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                article_language: option.value,
              },
            })
          }
          disabled={isLoading}
        />

        <CustomDropdown
          label="Source Languages"
          text={
            campaignData.settings?.source_languages?.length
              ? `${campaignData.settings.source_languages.length} selected`
              : "All languages"
          }
          options={[
            { label: "English", value: "en" },
            { label: "Spanish", value: "es" },
            { label: "French", value: "fr" },
            { label: "German", value: "de" },
            { label: "Chinese", value: "zh" },
          ]}
          onChange={(option) => {
            const current = campaignData.settings?.source_languages || [];
            const updated = current.includes(option.value)
              ? current.filter((lang) => lang !== option.value)
              : [...current, option.value];
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, source_languages: updated },
            });
          }}
          disabled={isLoading}
          isMultiSelect={true}
        />

        <CustomDropdown
          label="Countries"
          text={
            campaignData.settings?.countries?.length
              ? `${campaignData.settings.countries.length} selected`
              : "All countries"
          }
          options={[
            { label: "United States", value: "United States" },
            { label: "United Kingdom", value: "United Kingdom" },
            { label: "Canada", value: "Canada" },
            { label: "Australia", value: "Australia" },
            { label: "Germany", value: "Germany" },
          ]}
          onChange={(option) => {
            const current = campaignData.settings?.countries || [];
            const updated = current.includes(option.value)
              ? current.filter((country) => country !== option.value)
              : [...current, option.value];
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, countries: updated },
            });
          }}
          disabled={isLoading}
          isMultiSelect={true}
        />
      </div>
    </div>
  );
};

// Twitter/X News Form
const TwitterNewsForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Twitter/X News Configuration</h4>
      <p className="components-base-control__help">
        Generate articles from Twitter news sources and trending discussions.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Twitter Tech Trends"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Search Keywords"
          placeholder="e.g., #AI, #TechNews, startup"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <ToggleControl
          label="Verified accounts only"
          checked={campaignData.settings?.verified_only || false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, verified_only: value },
            })
          }
          disabled={isLoading}
          help="Only use tweets from verified Twitter accounts"
        />

        <ToggleControl
          label="Credible sources only"
          checked={campaignData.settings?.credible_sources_only || false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                credible_sources_only: value,
              },
            })
          }
          disabled={isLoading}
          help="Filter for established news organizations and journalists"
        />
      </div>

      <div className="atm-grid-2">
        <TextControl
          label="Minimum Followers"
          type="number"
          placeholder="10000"
          value={campaignData.settings?.min_followers || ""}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                min_followers: parseInt(value) || 0,
              },
            })
          }
          disabled={isLoading}
          help="Only use tweets from accounts with this many followers"
        />

        <TextControl
          label="Max Results per Search"
          type="number"
          placeholder="20"
          value={campaignData.settings?.max_results || ""}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                max_results: parseInt(value) || 20,
              },
            })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

// RSS Feeds Form
const RssFeedsForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>RSS Feeds Configuration</h4>
      <p className="components-base-control__help">
        Generate articles from RSS feed sources automatically using AI.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., RSS Tech Articles"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Keywords/Topic (Optional)"
          placeholder="e.g., artificial intelligence, climate change (leave empty for all feed content)"
          value={campaignData.keyword}
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
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                use_full_content: option.value,
              },
            })
          }
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
      </div>

      <div className="atm-grid-2">
        <ToggleControl
          label="Enable web search"
          checked={campaignData.settings?.enable_web_search !== false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, enable_web_search: value },
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

      <TextareaControl
        label="RSS Feed URLs"
        placeholder="Enter RSS feed URLs, one per line:
https://feeds.feedburner.com/TechCrunch
https://rss.cnn.com/rss/edition.rss
https://feeds.bbci.co.uk/news/rss.xml"
        value={campaignData.settings?.rss_urls || ""}
        onChange={(value) => {
          console.log("RSS URLs changing to:", value); // Add this debug line
          setCampaignData({
            ...campaignData,
            settings: {
              ...campaignData.settings,
              rss_urls: value,
            },
          });
        }}
        rows="8"
        disabled={isLoading}
        help="One RSS feed URL per line. The system will check these feeds and generate articles from new entries."
      />
    </div>
  );
};

// Live News Form
const LiveNewsForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Live News Configuration</h4>
      <p className="components-base-control__help">
        Search and categorize latest news with AI for intelligent article
        generation.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Live Breaking News"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Search Keywords"
          placeholder="e.g., breaking news, market update"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <ToggleControl
          label="Always get fresh results"
          checked={campaignData.settings?.force_fresh !== false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, force_fresh: value },
            })
          }
          disabled={isLoading}
          help="Bypass cache and search for the latest news every time"
        />

        <CustomDropdown
          label="News Urgency"
          text={campaignData.settings?.urgency_level || "Standard"}
          options={[
            { label: "Breaking News Only", value: "breaking" },
            { label: "Recent Updates", value: "recent" },
            { label: "Standard", value: "standard" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                urgency_level: option.value,
              },
            })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

function AutoNewsGenerator({
  setActiveView,
  editingCampaign,
  categories, // Receive categories prop
  authors, // Receive authors prop
}) {
  const [activeTab, setActiveTab] = useState("search");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  const [campaignData, setCampaignData] = useState({
    name: "",
    keyword: "",
    type: "news",
    sub_type: "search",
    settings: {
      ai_model: "",
      writing_style: "default_seo",
      creativity_level: "high",
      word_count: "",
      custom_prompt: "",
      generate_image: false,
      news_method: "google_news",
      category_ids: [],
      rss_urls: "", // Add this explicitly
      use_full_content: false, // Add this too
      enable_web_search: true, // Add this too
      skip_duplicates: true, // Add this too
    },
    schedule_value: 1,
    schedule_unit: "hour",
    content_mode: "publish",
    author_id: 1,
    is_active: true,
  });

  // News types configuration
  const newsTypes = [
    {
      id: "search",
      title: "News Search",
      description: "Search Google News and generate articles",
      icon: (
        <svg fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
          <path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.75 8.36,4.73 12.19,4.73C14.76,4.73 16.04,5.72 17.04,6.58L19.34,4.32C17.23,2.5 14.86,1.5 12.2,1.5C6.42,1.5 2.03,5.82 2.03,12C2.03,18.18 6.42,22.5 12.2,22.5C17.6,22.5 21.95,18.63 21.95,12.31C21.95,11.76 21.64,11.1 21.35,11.1Z" />
        </svg>
      ),
      gradient: "from-indigo-500 to-blue-600",
      news_method: "google_news",
    },
    {
      id: "apis",
      title: "Google News", // Changed from "APIs News"
      description: "Create articles from news APIs",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="20"
          height="20"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h14l2 2v12a2 2 0 01-2 2zM3 4h16M7 8h10M7 12h6"
          />
        </svg>
      ),
      gradient: "from-emerald-500 to-green-600",
      news_method: "api_news",
    },
    {
      id: "twitter",
      title: "Twitter/X News",
      description: "Generate articles from Twitter sources",
      icon: (
        <svg fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
          <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
        </svg>
      ),
      gradient: "from-slate-800 to-black",
      news_method: "twitter",
    },
    {
      id: "rss",
      title: "RSS Feeds",
      description: "Generate articles from RSS feeds",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="20"
          height="20"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"
          />
        </svg>
      ),
      gradient: "from-orange-500 to-red-600",
      news_method: "rss",
    },
    {
      id: "live",
      title: "Live News",
      description: "AI-categorized latest news",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="20"
          height="20"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
      news_method: "live_news",
    },
  ];

  // Update sub_type and news_method when tab changes
  useEffect(() => {
    const selectedType = newsTypes.find((type) => type.id === activeTab);
    setCampaignData((prev) => ({
      ...prev,
      sub_type: activeTab,
      settings: {
        ...prev.settings,
        news_method: selectedType?.news_method || "google_news",
      },
    }));
  }, [activeTab]);

  // Load editing campaign data
  useEffect(() => {
    if (editingCampaign) {
      setCampaignData({
        name: editingCampaign.name,
        keyword: editingCampaign.keyword,
        type: editingCampaign.type,
        sub_type: editingCampaign.sub_type || "search",
        settings: editingCampaign.settings || {},
        schedule_value: editingCampaign.schedule_value,
        schedule_unit: editingCampaign.schedule_unit,
        content_mode: editingCampaign.content_mode,
        category_ids: editingCampaign.category_ids || [],
        author_id: editingCampaign.author_id,
        is_active: editingCampaign.is_active == 1,
      });
      setActiveTab(editingCampaign.sub_type || "search");
    }
  }, [editingCampaign]);

  // Save campaign function
  const handleSaveCampaign = async () => {
    // Add debugging right here
    console.log("=== DEBUGGING RSS SAVE ===");
    console.log("Active tab:", activeTab);
    console.log("Campaign data:", campaignData);
    console.log("RSS URLs from settings:", campaignData.settings?.rss_urls);
    console.log("Settings object:", campaignData.settings);
    // Validation
    if (!campaignData.name.trim()) {
      setStatusMessage("Campaign name is required.");
      return;
    }

    // For RSS, keyword is optional
    if (activeTab !== "rss" && !campaignData.keyword.trim()) {
      setStatusMessage("Keywords/topic is required.");
      return;
    }

    // For RSS, check if RSS URLs are provided
    if (activeTab === "rss" && !campaignData.settings?.rss_urls?.trim()) {
      setStatusMessage("RSS feed URLs are required.");
      return;
    }

    setIsLoading(true);
    setStatusMessage("");

    try {
      const response = await fetch(atm_automation_data.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "atm_save_automation_campaign", // Make sure this matches your AJAX action
          nonce: atm_automation_data.nonce,
          campaign_data: JSON.stringify(campaignData),
          campaign_id: editingCampaign?.id || "",
        }),
      });

      const result = await response.json();

      if (result.success) {
        setStatusMessage(
          editingCampaign
            ? "Campaign updated successfully!"
            : "Campaign created successfully!"
        );
        setTimeout(() => setActiveView("campaigns"), 2000);
      } else {
        throw new Error(result.data || "Failed to save campaign");
      }
    } catch (error) {
      setStatusMessage("Error: " + error.message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-generator-view">
      {/* News Type Selector Cards */}
      <div className="atm-type-selector">
        <div className="atm-type-cards">
          {newsTypes.map((type) => (
            <div
              key={type.id}
              className={`atm-type-card ${
                activeTab === type.id ? "active" : ""
              }`}
              onClick={() => setActiveTab(type.id)}
            >
              <div
                className={`atm-type-icon bg-gradient-to-r ${type.gradient}`}
              >
                {type.icon}
              </div>
              <div className="atm-type-content">
                <h3>{type.title}</h3>
                <p>{type.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="atm-form-container">
        {/* Render the appropriate form based on active tab */}
        {activeTab === "search" && (
          <NewsSearchForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "twitter" && (
          <TwitterNewsForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "rss" && (
          <RssFeedsForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "apis" && (
          <AutoNewsForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "live" && (
          <LiveNewsForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}

        {/* Common settings section - UPDATE to pass props */}
        <AutomationSettingsForm
          campaignData={campaignData}
          setCampaignData={setCampaignData}
          isLoading={isLoading}
          categories={categories} // Pass categories down
          authors={authors} // Pass authors down
        />

        {/* Form actions */}
        <div className="atm-form-actions">
          <Button isPrimary onClick={handleSaveCampaign} disabled={isLoading}>
            {isLoading ? (
              <>
                <Spinner /> Saving...
              </>
            ) : editingCampaign ? (
              "Update Campaign"
            ) : (
              "Create Campaign"
            )}
          </Button>

          <Button
            isSecondary
            onClick={() => setActiveView("campaigns")}
            disabled={isLoading}
          >
            Cancel
          </Button>
        </div>

        {statusMessage && (
          <div className="atm-status-message">{statusMessage}</div>
        )}
      </div>
    </div>
  );
}

export default AutoNewsGenerator;
