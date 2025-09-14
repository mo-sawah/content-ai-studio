import { useState, useEffect } from "react";
import { Button, TextControl, Spinner } from "@wordpress/components";
import AutomationSettingsForm from "./AutomationSettingsForm";
import CustomDropdown from "../common/CustomDropdown";

// This is the new form section for podcast-specific settings
function AutoPodcastForm({ campaignData, setCampaignData, isLoading }) {
  const updateSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      settings: { ...prev.settings, [key]: value },
    }));
  };

  return (
    <div className="atm-form-section">
      <h3>Podcast Content & Audio Configuration</h3>
      <p className="description">
        The system will first generate a full article on your topic, then
        convert it into a podcast script and generate the audio.
      </p>

      <TextControl
        label="Podcast Topic / Article Keyword"
        placeholder="e.g., The Future of AI"
        value={campaignData.keyword || ""}
        onChange={(value) =>
          setCampaignData({ ...campaignData, keyword: value })
        }
        help="The main topic for the article and podcast."
        disabled={isLoading}
      />

      <div className="atm-grid-3">
        <CustomDropdown
          label="Podcast Language"
          text={campaignData.settings?.podcast_language || "English"}
          options={[
            { label: "English", value: "English" },
            { label: "Spanish", value: "Spanish" },
            { label: "French", value: "French" },
            { label: "German", value: "German" },
          ]}
          onChange={(option) => updateSetting("podcast_language", option.value)}
        />
        <CustomDropdown
          label="Podcast Duration"
          text={campaignData.settings?.podcast_duration || "Medium"}
          options={[
            { label: "Short (3-5 mins)", value: "short" },
            { label: "Medium (8-10 mins)", value: "medium" },
            { label: "Long (15-20 mins)", value: "long" },
          ]}
          onChange={(option) => updateSetting("podcast_duration", option.value)}
        />
        <CustomDropdown
          label="Audio Provider"
          text={campaignData.settings?.audio_provider || "OpenAI"}
          options={[
            { label: "OpenAI", value: "openai" },
            { label: "ElevenLabs", value: "elevenlabs" },
          ]}
          onChange={(option) => updateSetting("audio_provider", option.value)}
        />
      </div>

      <div className="atm-grid-2">
        <CustomDropdown
          label="Host A Voice"
          text={campaignData.settings?.host_a_voice || "Alloy"}
          options={[
            { label: "Alloy", value: "alloy" },
            { label: "Echo", value: "echo" },
            { label: "Fable", value: "fable" },
            { label: "Onyx", value: "onyx" },
            { label: "Nova", value: "nova" },
            { label: "Shimmer", value: "shimmer" },
          ]}
          onChange={(option) => updateSetting("host_a_voice", option.value)}
        />
        <CustomDropdown
          label="Host B Voice"
          text={campaignData.settings?.host_b_voice || "Nova"}
          options={[
            { label: "Alloy", value: "alloy" },
            { label: "Echo", value: "echo" },
            { label: "Fable", value: "fable" },
            { label: "Onyx", value: "onyx" },
            { label: "Nova", value: "nova" },
            { label: "Shimmer", value: "shimmer" },
          ]}
          onChange={(option) => updateSetting("host_b_voice", option.value)}
        />
      </div>
    </div>
  );
}

// Main component, now fully implemented
function AutoPodcastGenerator({
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
    type: "podcasts",
    sub_type: "standard",
    keyword: "",
    schedule_value: 1,
    schedule_unit: "day",
    content_mode: "draft",
    author_id: 1,
    is_active: true,
    settings: {
      generate_image: true,
      podcast_language: "English",
      podcast_duration: "medium",
      audio_provider: "openai",
      host_a_voice: "alloy",
      host_b_voice: "nova",
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
        text: "Campaign Name and Topic are required.",
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
            placeholder="e.g., Daily Tech Insights Podcast"
            value={campaignData.name}
            onChange={(value) =>
              setCampaignData({ ...campaignData, name: value })
            }
            help="Give your podcast campaign a unique name."
            disabled={isLoading}
          />
        </div>

        <AutoPodcastForm
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

export default AutoPodcastGenerator;
