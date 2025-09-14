import { useState, useEffect } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";
import AutoCreativeForm from "./AutoCreativeForm";
import AutoTrendingForm from "./AutoTrendingForm";
import AutoListicleForm from "./AutoListicleForm";
import AutoMultipageArticlesForm from "./AutoMultipageArticlesForm";
import AutomationSettingsForm from "./AutomationSettingsForm";

function AutoArticleGenerator({
  setActiveView,
  editingCampaign,
  categories,
  authors,
}) {
  const [activeTab, setActiveTab] = useState("creative");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState({
    text: "",
    type: "info",
  });

  const [campaignData, setCampaignData] = useState({
    name: "",
    type: "articles",
    sub_type: "creative",
    keyword: "",
    article_title: "",
    schedule_value: 1,
    schedule_unit: "hour",
    content_mode: "draft",
    author_id: 1,
    is_active: true,
    settings: {
      writing_style: "default_seo",
      creativity_level: "high",
      word_count: 0,
      custom_prompt: "",
      generate_image: true,
      skip_weekends: false,
      quality_check: false,
      category_ids: [],
    },
  });

  useEffect(() => {
    if (editingCampaign) {
      const mergedSettings = {
        ...campaignData.settings,
        ...editingCampaign.settings,
      };
      setCampaignData({
        ...campaignData,
        ...editingCampaign,
        settings: mergedSettings,
      });
      setActiveTab(editingCampaign.sub_type || "creative");
    }
  }, [editingCampaign]);

  useEffect(() => {
    setCampaignData((prev) => ({ ...prev, sub_type: activeTab }));
  }, [activeTab]);

  const handleSaveCampaign = async () => {
    if (!campaignData.name.trim()) {
      setStatusMessage({ text: "Campaign name is required.", type: "error" });
      return;
    }
    if (!campaignData.keyword.trim() && activeTab !== "trending") {
      setStatusMessage({
        text: "Keyword is required for this article type.",
        type: "error",
      });
      return;
    }

    setIsLoading(true);
    setStatusMessage({ text: "Saving campaign...", type: "info" });

    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_save_automation_campaign",
          nonce: atm_automation_data.nonce,
          campaign_data: JSON.stringify(campaignData),
          campaign_id: editingCampaign?.id || "",
        },
      });

      if (response.success) {
        setStatusMessage({
          text: editingCampaign
            ? "Campaign updated successfully!"
            : "Campaign created successfully!",
          type: "success",
        });
        setTimeout(() => setActiveView("campaigns"), 1500);
      } else {
        throw new Error(response.data || "Failed to save campaign");
      }
    } catch (error) {
      setStatusMessage({ text: `Error: ${error.message}`, type: "error" });
      console.error("Campaign save error:", error);
    } finally {
      setIsLoading(false);
    }
  };

  const articleTypes = [
    {
      id: "creative",
      title: "Standard Articles",
      description: "Create high-quality SEO content with AI",
      icon: (
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
          <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.828-2.828z" />
        </svg>
      ),
    },
    {
      id: "trending",
      title: "Trending Articles",
      description: "Generate content on current hot topics",
      icon: (
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
            clipRule="evenodd"
          />
        </svg>
      ),
    },
    {
      id: "listicle",
      title: "Listicle Articles",
      description: "Create numbered lists and top 10 style content",
      icon: (
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
          <path
            fillRule="evenodd"
            d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
            clipRule="evenodd"
          />
        </svg>
      ),
    },
    {
      id: "multipage",
      title: "Multipage Articles",
      description: "Create comprehensive, multi-part guides",
      icon: (
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
          <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
          <path
            fillRule="evenodd"
            d="M3 8a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
            clipRule="evenodd"
          />
        </svg>
      ),
    },
  ];

  const renderActiveForm = () => {
    const props = {
      isAutomation: true,
      campaignData: campaignData,
      setCampaignData: setCampaignData,
    };

    switch (activeTab) {
      case "creative":
        return <AutoCreativeForm {...props} />;
      case "trending":
        return <AutoTrendingForm {...props} />;
      case "listicle":
        return <AutoListicleForm {...props} />;
      case "multipage":
        return <AutoMultipageArticlesForm {...props} />;
      default:
        return <AutoCreativeForm {...props} />;
    }
  };

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <div className="atm-form-section">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Daily SEO Blog Posts"
            value={campaignData.name}
            onChange={(value) =>
              setCampaignData({ ...campaignData, name: value })
            }
            help="Give your automation campaign a unique name."
            disabled={isLoading}
          />
        </div>

        <div className="atm-type-selector">
          <div className="atm-type-cards">
            {articleTypes.map((type) => (
              <div
                key={type.id}
                className={`atm-type-card ${
                  activeTab === type.id ? "active" : ""
                }`}
                onClick={() => setActiveTab(type.id)}
              >
                <div className={`atm-type-icon ${getIconColorClass(type.id)}`}>
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

        {renderActiveForm()}

        <AutomationSettingsForm
          campaignData={campaignData}
          setCampaignData={setCampaignData}
          isLoading={isLoading}
          categories={categories}
          authors={authors}
        />

        <div className="atm-form-actions">
          <Button
            isPrimary
            onClick={handleSaveCampaign}
            disabled={
              isLoading ||
              !campaignData.name.trim() ||
              (activeTab !== "trending" && !campaignData.keyword.trim())
            }
          >
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

        {statusMessage.text && (
          <p className={`atm-status-message ${statusMessage.type}`}>
            {statusMessage.text}
          </p>
        )}
      </div>
    </div>
  );
}

function getIconColorClass(typeId) {
  const colorMap = {
    creative: "atm-icon-purple",
    trending: "atm-icon-red",
    listicle: "atm-icon-green",
    multipage: "atm-icon-orange",
  };
  return colorMap[typeId] || "atm-icon-purple";
}

export default AutoArticleGenerator;
