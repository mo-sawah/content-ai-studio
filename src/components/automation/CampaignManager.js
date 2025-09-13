// src/components/automation/CampaignManager.js
import { useState, useEffect } from "@wordpress/element";
import { Button, Spinner } from "@wordpress/components";

function CampaignManager({ setActiveView, setEditingCampaign }) {
  const [campaigns, setCampaigns] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [statusMessage, setStatusMessage] = useState("");

  useEffect(() => {
    loadCampaigns();
  }, []);

  const loadCampaigns = async () => {
    setIsLoading(true);
    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_get_automation_campaigns",
          nonce: atm_automation_data.nonce,
        },
      });

      if (response.success) {
        setCampaigns(response.data.campaigns);
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error loading campaigns: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleToggleCampaign = async (campaignId, currentStatus) => {
    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_toggle_automation_campaign",
          nonce: atm_automation_data.nonce,
          campaign_id: campaignId,
          is_active: !currentStatus,
        },
      });

      if (response.success) {
        setStatusMessage(response.data.message);
        loadCampaigns(); // Reload to get updated data
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    }
  };

  const handleRunCampaign = async (campaignId) => {
    setStatusMessage("Running campaign...");
    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_run_automation_campaign_now",
          nonce: atm_automation_data.nonce,
          campaign_id: campaignId,
        },
      });

      if (response.success) {
        setStatusMessage("Campaign executed successfully!");
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
    }
  };

  const handleDeleteCampaign = async (campaignId, campaignName) => {
    if (
      !confirm(
        `Are you sure you want to delete "${campaignName}"? This action cannot be undone.`
      )
    ) {
      return;
    }

    try {
      const response = await jQuery.ajax({
        url: atm_automation_data.ajax_url,
        type: "POST",
        data: {
          action: "atm_delete_automation_campaign",
          nonce: atm_automation_data.nonce,
          campaign_id: campaignId,
        },
      });

      if (response.success) {
        setStatusMessage("Campaign deleted successfully!");
        loadCampaigns(); // Reload campaigns
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    }
  };

  const handleEditCampaign = (campaign) => {
    setEditingCampaign(campaign);
    setActiveView(campaign.type); // Navigate to the appropriate automation type
  };

  const getTypeIcon = (type) => {
    const icons = {
      articles: (
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
      news: (
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
      videos: (
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
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      ),
      podcasts: (
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
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />
        </svg>
      ),
    };
    return icons[type] || icons.articles;
  };

  const getTypeColor = (type) => {
    const colors = {
      articles: "#6366f1",
      news: "#059669",
      videos: "#dc2626",
      podcasts: "#f97316",
    };
    return colors[type] || colors.articles;
  };

  const getStatusBadge = (isActive, lastStatus) => {
    if (!isActive) {
      return <span className="atm-status-badge paused">Paused</span>;
    }

    if (lastStatus === "completed") {
      return <span className="atm-status-badge active">Active</span>;
    } else if (lastStatus === "failed") {
      return <span className="atm-status-badge error">Error</span>;
    } else if (lastStatus === "started") {
      return <span className="atm-status-badge running">Running</span>;
    }

    return <span className="atm-status-badge active">Active</span>;
  };

  if (isLoading) {
    return (
      <div className="atm-generator-view">
        <div style={{ textAlign: "center", padding: "40px" }}>
          <Spinner />
          <p>Loading campaigns...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="atm-generator-view">
      <div className="atm-campaigns-header">
        <div className="atm-campaigns-header-content">
          <h3>Automation Campaigns</h3>
          <p>Manage your automated content generation campaigns</p>
        </div>
        <Button isPrimary onClick={() => setActiveView("hub")}>
          Create New Campaign
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

      {campaigns.length === 0 ? (
        <div className="atm-empty-state">
          <div className="atm-empty-state-icon">
            <svg
              width="64"
              height="64"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1}
                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"
              />
            </svg>
          </div>
          <h3>No campaigns yet</h3>
          <p>
            Create your first automation campaign to start generating content
            automatically.
          </p>
          <Button isPrimary onClick={() => setActiveView("hub")}>
            Create First Campaign
          </Button>
        </div>
      ) : (
        <div className="atm-campaigns-grid">
          {campaigns.map((campaign) => (
            <div key={campaign.id} className="atm-campaign-card">
              <div className="atm-campaign-header">
                <div
                  className="atm-campaign-type"
                  style={{ color: getTypeColor(campaign.type) }}
                >
                  {getTypeIcon(campaign.type)}
                  <span>
                    {campaign.type.charAt(0).toUpperCase() +
                      campaign.type.slice(1)}
                  </span>
                </div>
                {getStatusBadge(campaign.is_active == 1, campaign.last_status)}
              </div>

              <div className="atm-campaign-content">
                <h4>{campaign.name}</h4>
                <p className="atm-campaign-keyword">{campaign.keyword}</p>

                <div className="atm-campaign-meta">
                  <div className="atm-campaign-meta-item">
                    <span className="label">Schedule:</span>
                    <span className="value">
                      Every {campaign.schedule_value} {campaign.schedule_unit}
                      (s)
                    </span>
                  </div>
                  <div className="atm-campaign-meta-item">
                    <span className="label">Next Run:</span>
                    <span className="value">{campaign.next_run_formatted}</span>
                  </div>
                  <div className="atm-campaign-meta-item">
                    <span className="label">Executions:</span>
                    <span className="value">
                      {campaign.total_executions || 0}
                    </span>
                  </div>
                  {campaign.last_execution && (
                    <div className="atm-campaign-meta-item">
                      <span className="label">Last Run:</span>
                      <span className="value">
                        {campaign.last_execution_formatted}
                      </span>
                    </div>
                  )}
                </div>
              </div>

              <div className="atm-campaign-actions">
                <Button isSmall onClick={() => handleEditCampaign(campaign)}>
                  Edit
                </Button>

                <Button
                  isSmall
                  isSecondary
                  onClick={() => handleRunCampaign(campaign.id)}
                >
                  Run Now
                </Button>

                <Button
                  isSmall
                  variant={campaign.is_active == 1 ? "secondary" : "primary"}
                  onClick={() =>
                    handleToggleCampaign(campaign.id, campaign.is_active == 1)
                  }
                >
                  {campaign.is_active == 1 ? "Pause" : "Resume"}
                </Button>

                <Button
                  isSmall
                  isDestructive
                  onClick={() =>
                    handleDeleteCampaign(campaign.id, campaign.name)
                  }
                >
                  Delete
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default CampaignManager;
