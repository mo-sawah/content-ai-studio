// src/components/automation/AutoArticleGenerator.js
import { useState, useEffect } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";
import CampaignSettingsForm from "./CampaignSettingsForm";

// Article type configurations
const articleTypes = [
  {
    id: "standard",
    title: "Standard Articles",
    description: "High-quality SEO content with intelligent angle diversity",
    icon: (
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
        />
      </svg>
    ),
  },
  {
    id: "trending",
    title: "Trending Articles",
    description: "Current hot topics and trending searches",
    icon: (
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth="2"
          d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
        />
      </svg>
    ),
  },
  {
    id: "listicle",
    title: "Listicle Articles",
    description: "Numbered lists and top 10 style content",
    icon: (
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h.01M9 12h.01m0 4h.01m3-6h4m-4 4h4m2-5h.01M21 12h.01"
        />
      </svg>
    ),
  },
  {
    id: "multipage",
    title: "Multipage Articles",
    description: "Multi-part comprehensive guides and series",
    icon: (
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2h10z"
        />
      </svg>
    ),
  },
];

// Individual form components for each type
const StandardArticlesForm = ({ campaignData, setCampaignData, isLoading }) => (
  <div className="atm-content-card">
    <h3>Standard Article Configuration</h3>
    <p className="description">
      Generate high-quality SEO articles with intelligent angle diversity to
      ensure unique content every time.
    </p>

    <div className="atm-form-row">
      <TextControl
        label="Campaign Name"
        placeholder="e.g., Daily Tech Articles"
        value={campaignData.name || ""}
        onChange={(value) => setCampaignData({ ...campaignData, name: value })}
        disabled={isLoading}
      />

      <TextControl
        label="Keyword/Topic"
        placeholder="e.g., artificial intelligence"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        disabled={isLoading}
      />
    </div>
  </div>
);

const TrendingArticlesForm = ({ campaignData, setCampaignData, isLoading }) => (
  <div className="atm-content-card">
    <h3>Trending Articles Configuration</h3>
    <p className="description">
      Automatically generate articles based on current trending topics and hot
      searches.
    </p>

    <div className="atm-form-row">
      <TextControl
        label="Campaign Name"
        placeholder="e.g., Daily Trending Topics"
        value={campaignData.name || ""}
        onChange={(value) => setCampaignData({ ...campaignData, name: value })}
        disabled={isLoading}
      />

      <TextControl
        label="Trending Keyword"
        placeholder="e.g., technology, business, health"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        disabled={isLoading}
        help="Base keyword to find trending topics around"
      />
    </div>
  </div>
);

const ListicleArticlesForm = ({ campaignData, setCampaignData, isLoading }) => (
  <div className="atm-content-card">
    <h3>Listicle Articles Configuration</h3>
    <p className="description">
      Generate numbered list articles and "Top 10" style content automatically.
    </p>

    <div className="atm-form-row">
      <TextControl
        label="Campaign Name"
        placeholder="e.g., Top 10 Tech Lists"
        value={campaignData.name || ""}
        onChange={(value) => setCampaignData({ ...campaignData, name: value })}
        disabled={isLoading}
      />

      <TextControl
        label="Listicle Topic"
        placeholder="e.g., productivity apps, marketing tools"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        disabled={isLoading}
      />
    </div>
  </div>
);

const MultipageArticlesForm = ({
  campaignData,
  setCampaignData,
  isLoading,
}) => (
  <div className="atm-content-card">
    <h3>Multipage Articles Configuration</h3>
    <p className="description">
      Create comprehensive, multi-part guides that are split across multiple
      pages.
    </p>

    <div className="atm-form-row">
      <TextControl
        label="Campaign Name"
        placeholder="e.g., Complete Guide Series"
        value={campaignData.name || ""}
        onChange={(value) => setCampaignData({ ...campaignData, name: value })}
        disabled={isLoading}
      />

      <TextControl
        label="Guide Topic"
        placeholder="e.g., digital marketing, web development"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        disabled={isLoading}
      />
    </div>
  </div>
);

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

  // Update sub_type when tab changes
  useEffect(() => {
    setCampaignData((prev) => ({ ...prev, sub_type: activeTab }));
  }, [activeTab]);

  // Load editing campaign data
  useEffect(() => {
    if (editingCampaign) {
      setCampaignData({
        name: editingCampaign.name || "",
        keyword: editingCampaign.keyword || "",
        type: editingCampaign.type || "articles",
        sub_type: editingCampaign.sub_type || "standard",
        settings: editingCampaign.settings || {},
        schedule_value: editingCampaign.schedule_value || 1,
        schedule_unit: editingCampaign.schedule_unit || "hour",
        content_mode: editingCampaign.content_mode || "publish",
        category_ids: editingCampaign.category_ids || [],
        author_id: editingCampaign.author_id || 1,
        is_active: editingCampaign.is_active == 1,
      });
      setActiveTab(editingCampaign.sub_type || "standard");
    }
  }, [editingCampaign]);

  // Save campaign function
  const handleSaveCampaign = async () => {
    if (!campaignData.name.trim()) {
      setStatusMessage("Campaign name is required.");
      return;
    }

    if (!campaignData.keyword.trim()) {
      setStatusMessage("Keywords/topic is required.");
      return;
    }

    setIsLoading(true);
    setStatusMessage("");

    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_save_automation_campaign",
          nonce: atm_automation_data.nonce,
          campaign_data: JSON.stringify(campaignData),
          campaign_id: editingCampaign?.id || 0,
        },
      });

      if (response.success) {
        setStatusMessage(
          editingCampaign
            ? "Campaign updated successfully!"
            : "Campaign created successfully!"
        );
        setTimeout(() => setActiveView("campaigns"), 2000);
      } else {
        throw new Error(response.data || "Failed to save campaign");
      }
    } catch (error) {
      setStatusMessage("Error: " + error.message);
    } finally {
      setIsLoading(false);
    }
  };

  const renderConfigurationForm = () => {
    const props = { campaignData, setCampaignData, isLoading };

    switch (activeTab) {
      case "standard":
        return <StandardArticlesForm {...props} />;
      case "trending":
        return <TrendingArticlesForm {...props} />;
      case "listicle":
        return <ListicleArticlesForm {...props} />;
      case "multipage":
        return <MultipageArticlesForm {...props} />;
      default:
        return <StandardArticlesForm {...props} />;
    }
  };

  return (
    <div className="atm-automation-root">
      {/* Article Type Selector */}
      <div className="atm-content-card">
        <h3>Article Type</h3>
        <p className="description">
          Choose the type of articles you want to generate automatically.
        </p>

        <div className="atm-type-cards">
          {articleTypes.map((type) => (
            <div
              key={type.id}
              className={`atm-type-card ${activeTab === type.id ? "active" : ""}`}
              onClick={() => setActiveTab(type.id)}
            >
              <div className="atm-type-icon">{type.icon}</div>
              <div className="atm-type-content">
                <h4>{type.title}</h4>
                <p>{type.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Configuration Form */}
      {renderConfigurationForm()}

      {/* Campaign Settings */}
      <CampaignSettingsForm
        campaignData={campaignData}
        setCampaignData={setCampaignData}
        isLoading={isLoading}
      />

      {/* Form Actions */}
      <div className="atm-content-card">
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
          <div
            className="atm-status-message"
            style={{
              marginTop: "12px",
              padding: "8px 12px",
              background: statusMessage.includes("Error")
                ? "#fef2f2"
                : "#f0fdf4",
              borderRadius: "6px",
              fontSize: "14px",
            }}
          >
            {statusMessage}
          </div>
        )}
      </div>
    </div>
  );
}

export default AutoArticleGenerator;
