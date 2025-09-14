import { useState, useEffect } from "react";
import { Button, TextControl, Spinner } from "@wordpress/components";
import AutomationSettingsForm from "./AutomationSettingsForm";
import CustomDropdown from "../common/CustomDropdown";

// This is the new form section for video-specific settings
function AutoVideoForm({ campaignData, setCampaignData, isLoading }) {
  const updateSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      settings: { ...prev.settings, [key]: value },
    }));
  };

  return (
    <div className="atm-form-section">
      <h3>Video Source Configuration</h3>
      <p className="description">
        Configure the source and type of videos to search for on YouTube.
      </p>

      <TextControl
        label="Video Search Keyword"
        placeholder="e.g., WordPress tutorials, cooking tips"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        help="The topic to search for on YouTube."
        disabled={isLoading}
      />

      <div className="atm-grid-2">
        <CustomDropdown
          label="Search Order"
          text={campaignData.settings?.video_order || "Relevance"}
          options={[
            { label: "Relevance", value: "relevance" },
            { label: "Most Recent", value: "date" },
            { label: "Most Viewed", value: "viewCount" },
            { label: "Highest Rated", value: "rating" },
          ]}
          onChange={(option) => updateSetting("video_order", option.value)}
        />
        <CustomDropdown
          label="Video Duration"
          text={campaignData.settings?.video_duration || "Any"}
          options={[
            { label: "Any", value: "any" },
            { label: "Short (under 4 mins)", value: "short" },
            { label: "Medium (4-20 mins)", value: "medium" },
            { label: "Long (over 20 mins)", value: "long" },
          ]}
          onChange={(option) => updateSetting("video_duration", option.value)}
        />
      </div>
    </div>
  );
}

// Main component, now fully implemented
function AutoVideoGenerator({
  setActiveView,
  editingCampaign,
  categories,
  authors,
}) {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState({
    text: "",
    type: "info",
  });

  const [campaignData, setCampaignData] = useState({
    name: "",
    type: "videos",
    sub_type: "youtube", // Default sub_type for videos
    keyword: "",
    schedule_value: 1,
    schedule_unit: "day",
    content_mode: "draft",
    author_id: 1,
    is_active: true,
    settings: {
      generate_image: true, // Auto-set featured image from video thumbnail
      video_order: "relevance",
      video_duration: "any",
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
    }
  }, [editingCampaign]);

  const handleSaveCampaign = async () => {
    if (!campaignData.name.trim() || !campaignData.keyword.trim()) {
      setStatusMessage({
        text: "Campaign Name and Keyword are required.",
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
          text: "Campaign saved successfully!",
          type: "success",
        });
        setTimeout(() => setActiveView("campaigns"), 1500);
      } else {
        throw new Error(response.data || "Failed to save campaign");
      }
    } catch (error) {
      setStatusMessage({ text: `Error: ${error.message}`, type: "error" });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <div className="atm-form-section">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Weekly WordPress Videos"
            value={campaignData.name}
            onChange={(value) =>
              setCampaignData({ ...campaignData, name: value })
            }
            help="Give your video campaign a unique name."
            disabled={isLoading}
          />
        </div>

        <AutoVideoForm
          campaignData={campaignData}
          setCampaignData={setCampaignData}
          isLoading={isLoading}
        />

        <AutomationSettingsForm
          campaignData={campaignData}
          setCampaignData={setCampaignData}
          isLoading={isLoading}
          categories={categories}
          authors={authors}
        />

        <div className="atm-form-actions">
          <Button isPrimary onClick={handleSaveCampaign} disabled={isLoading}>
            {isLoading ? (
              <Spinner />
            ) : editingCampaign ? (
              "Update Campaign"
            ) : (
              "Create Campaign"
            )}
          </Button>
          <Button
            isSecondary
            onClick={() => setActiveView("hub")}
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

export default AutoVideoGenerator;
