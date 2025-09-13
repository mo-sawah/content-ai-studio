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

// Multi-Select Category Component (keep existing)
const CategoryMultiSelect = ({
  selectedCategories,
  onCategoriesChange,
  categories,
}) => {
  // ... keep existing implementation ...
};

// Standard Articles Form
const StandardArticlesForm = ({
  campaignData,
  setCampaignData,
  isLoading,
  editingCampaign,
}) => {
  return (
    <div className="atm-form-section">
      <h4>Standard Article Configuration</h4>
      <p className="components-base-control__help">
        Generate high-quality SEO articles with intelligent angle diversity to
        ensure unique content every time.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Daily Tech Articles"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Keyword/Topic"
          placeholder="e.g., artificial intelligence"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

// Trending Articles Form
const TrendingArticlesForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Trending Articles Configuration</h4>
      <p className="components-base-control__help">
        Automatically generate articles based on current trending topics and hot
        searches.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Daily Trending Topics"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Trending Keyword"
          placeholder="e.g., technology, business, health"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
          help="Base keyword to find trending topics around"
        />
      </div>

      <div className="atm-grid-2">
        <CustomDropdown
          label="Region"
          text={campaignData.settings?.trending_region || "United States"}
          options={[
            { label: "United States", value: "US" },
            { label: "United Kingdom", value: "GB" },
            { label: "Canada", value: "CA" },
            { label: "Australia", value: "AU" },
            { label: "Germany", value: "DE" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                trending_region: option.value,
              },
            })
          }
          disabled={isLoading}
        />

        <CustomDropdown
          label="Time Range"
          text={campaignData.settings?.trending_timerange || "Past 7 days"}
          options={[
            { label: "Past 24 hours", value: "now 1-d" },
            { label: "Past 7 days", value: "now 7-d" },
            { label: "Past 30 days", value: "now 30-d" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                trending_timerange: option.value,
              },
            })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

// Listicle Articles Form
const ListicleArticlesForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Listicle Articles Configuration</h4>
      <p className="components-base-control__help">
        Generate numbered list articles and "Top 10" style content
        automatically.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Top 10 Tech Lists"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Listicle Topic"
          placeholder="e.g., productivity apps, marketing tools"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <CustomDropdown
          label="List Size"
          text={
            campaignData.settings?.listicle_count
              ? `${campaignData.settings.listicle_count} items`
              : "10 items"
          }
          options={[
            { label: "5 items", value: 5 },
            { label: "7 items", value: 7 },
            { label: "10 items", value: 10 },
            { label: "15 items", value: 15 },
            { label: "20 items", value: 20 },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                listicle_count: option.value,
              },
            })
          }
          disabled={isLoading}
        />

        <CustomDropdown
          label="Listicle Style"
          text={campaignData.settings?.listicle_style || "Numbered List"}
          options={[
            { label: "Numbered List", value: "numbered" },
            { label: "Top X Format", value: "top" },
            { label: "Best Of Format", value: "best" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                listicle_style: option.value,
              },
            })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

// Multipage Articles Form
const MultipageArticlesForm = ({
  campaignData,
  setCampaignData,
  isLoading,
}) => {
  return (
    <div className="atm-form-section">
      <h4>Multipage Articles Configuration</h4>
      <p className="components-base-control__help">
        Create comprehensive, multi-part guides that are split across multiple
        pages.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., Complete Guide Series"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="Guide Topic"
          placeholder="e.g., digital marketing, web development"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <CustomDropdown
          label="Number of Pages"
          text={
            campaignData.settings?.page_count
              ? `${campaignData.settings.page_count} pages`
              : "5 pages"
          }
          options={[
            { label: "3 pages", value: 3 },
            { label: "5 pages", value: 5 },
            { label: "7 pages", value: 7 },
            { label: "10 pages", value: 10 },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, page_count: option.value },
            })
          }
          disabled={isLoading}
        />

        <CustomDropdown
          label="Words per Page"
          text={
            campaignData.settings?.words_per_page
              ? `${campaignData.settings.words_per_page} words`
              : "800 words"
          }
          options={[
            { label: "500 words", value: 500 },
            { label: "800 words", value: 800 },
            { label: "1200 words", value: 1200 },
            { label: "1500 words", value: 1500 },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: {
                ...campaignData.settings,
                words_per_page: option.value,
              },
            })
          }
          disabled={isLoading}
        />
      </div>
    </div>
  );
};

function AutoArticleGenerator({ setActiveView, editingCampaign }) {
  const [activeTab, setActiveTab] = useState("standard");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  // Campaign data state
  const [campaignData, setCampaignData] = useState({
    name: "",
    keyword: "",
    type: "articles",
    sub_type: "standard",
    settings: {
      ai_model: "",
      writing_style: "default_seo",
      creativity_level: "high",
      word_count: "",
      custom_prompt: "",
      generate_image: false,
    },
    schedule_value: 1,
    schedule_unit: "hour",
    content_mode: "publish",
    category_ids: [],
    author_id: 1,
    is_active: true,
  });

  // Article types configuration
  const articleTypes = [
    {
      id: "standard",
      title: "Standard Articles",
      description: "High-quality SEO content with AI",
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
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
    },
    {
      id: "trending",
      title: "Trending Articles",
      description: "Current hot topics and trends",
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
            strokeWidth="2"
            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
          />
        </svg>
      ),
      gradient: "from-red-500 to-pink-600",
    },
    {
      id: "listicle",
      title: "Listicle Articles",
      description: "Numbered lists and top 10 style",
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
            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h.01M9 12h.01m0 4h.01m3-6h4m-4 4h4m2-5h.01M21 12h.01"
          />
        </svg>
      ),
      gradient: "from-green-500 to-emerald-600",
    },
    {
      id: "multipage",
      title: "Multipage Articles",
      description: "Multi-part comprehensive guides",
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
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2h10z"
          />
        </svg>
      ),
      gradient: "from-yellow-500 to-amber-600",
    },
  ];

  // Update sub_type when tab changes
  useEffect(() => {
    setCampaignData((prev) => ({ ...prev, sub_type: activeTab }));
  }, [activeTab]);

  // Load editing campaign data
  useEffect(() => {
    if (editingCampaign) {
      setCampaignData({
        name: editingCampaign.name,
        keyword: editingCampaign.keyword,
        type: editingCampaign.type,
        sub_type: editingCampaign.sub_type || "standard",
        settings: editingCampaign.settings || {},
        schedule_value: editingCampaign.schedule_value,
        schedule_unit: editingCampaign.schedule_unit,
        content_mode: editingCampaign.content_mode,
        category_ids: editingCampaign.category_ids || [],
        author_id: editingCampaign.author_id,
        is_active: editingCampaign.is_active == 1,
      });
      setActiveTab(editingCampaign.sub_type || "standard");
    }
  }, [editingCampaign]);

  // Save campaign function (implement save logic here)
  const handleSaveCampaign = async () => {
    // Validation and save logic...
    // This should call the updated AJAX endpoint that handles sub_type
  };

  return (
    <div className="atm-generator-view">
      {/* Article Type Selector Cards */}
      <div className="atm-type-selector">
        <div className="atm-type-cards">
          {articleTypes.map((type) => (
            <div
              key={type.id}
              className={`atm-type-card ${activeTab === type.id ? "active" : ""}`}
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
        {activeTab === "standard" && (
          <StandardArticlesForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
            editingCampaign={editingCampaign}
          />
        )}
        {activeTab === "trending" && (
          <TrendingArticlesForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "listicle" && (
          <ListicleArticlesForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}
        {activeTab === "multipage" && (
          <MultipageArticlesForm
            campaignData={campaignData}
            setCampaignData={setCampaignData}
            isLoading={isLoading}
          />
        )}

        {/* Common settings section - shown for all types */}
        <div className="atm-common-settings">
          {/* Add common settings like AI model, writing style, etc. */}
          {/* This section should be similar to your current implementation */}
        </div>

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

export default AutoArticleGenerator;
