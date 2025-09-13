import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  Spinner,
  Modal,
  TextControl,
  SelectControl,
} from "@wordpress/components";

function CampaignManager({ setActiveView, setEditingCampaign }) {
  const [campaigns, setCampaigns] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [statusMessage, setStatusMessage] = useState("");
  const [filteredCampaigns, setFilteredCampaigns] = useState([]);
  const [filterType, setFilterType] = useState("all");
  const [filterStatus, setFilterStatus] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [showDeleteModal, setShowDeleteModal] = useState(null);
  const [bulkSelected, setBulkSelected] = useState([]);
  const [showBulkActions, setShowBulkActions] = useState(false);

  useEffect(() => {
    loadCampaigns();
  }, []);

  useEffect(() => {
    let filtered = campaigns;

    if (filterType !== "all") {
      filtered = filtered.filter((campaign) => campaign.type === filterType);
    }

    if (filterStatus !== "all") {
      filtered = filtered.filter((campaign) => {
        if (filterStatus === "active") return campaign.is_active == 1;
        if (filterStatus === "paused") return campaign.is_active == 0;
        return campaign.status === filterStatus;
      });
    }

    if (searchTerm) {
      filtered = filtered.filter(
        (campaign) =>
          campaign.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          campaign.keyword.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    setFilteredCampaigns(filtered);
  }, [campaigns, filterType, filterStatus, searchTerm]);

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
        setCampaigns(response.data.campaigns || []);
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
        loadCampaigns();
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    }
  };

  const handleRunCampaign = async (campaignId, campaignName) => {
    setStatusMessage(`Running "${campaignName}"...`);
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
        setStatusMessage(`"${campaignName}" executed successfully!`);
        if (response.data.post_url) {
          setTimeout(() => {
            window.open(response.data.post_url, "_blank");
          }, 1000);
        }
        loadCampaigns();
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    }
  };

  const handleDeleteCampaign = async (campaignId, campaignName) => {
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
        setStatusMessage(`"${campaignName}" deleted successfully!`);
        loadCampaigns();
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    }
  };

  const handleEditCampaign = (campaign) => {
    setEditingCampaign(campaign);
    setActiveView(campaign.type);
  };

  const formatNextRun = (dateString) => {
    if (!dateString || dateString === "0000-00-00 00:00:00")
      return "Not scheduled";
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = date - now;

    if (diffMs <= 0) return "Due now";

    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (diffHours > 24) {
      return `${Math.floor(diffHours / 24)}d ${diffHours % 24}h`;
    } else if (diffHours > 0) {
      return `${diffHours}h ${diffMins}m`;
    } else {
      return `${diffMins}m`;
    }
  };

  const getStatusConfig = (campaign) => {
    const status =
      campaign.status || (campaign.is_active == 1 ? "idle" : "paused");

    const configs = {
      idle: {
        color: "text-blue-600 bg-blue-50 border-blue-200",
        dot: "bg-blue-500",
        text: "Active",
      },
      running: {
        color: "text-green-600 bg-green-50 border-green-200",
        dot: "bg-green-500 animate-pulse",
        text: "Running",
      },
      paused: {
        color: "text-yellow-600 bg-yellow-50 border-yellow-200",
        dot: "bg-yellow-500",
        text: "Paused",
      },
      failed: {
        color: "text-red-600 bg-red-50 border-red-200",
        dot: "bg-red-500",
        text: "Failed",
      },
    };

    return configs[status] || configs.idle;
  };

  const getTypeIcon = (type, subType) => {
    const iconMap = {
      articles: {
        standard: (
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.828-2.828z" />
          </svg>
        ),
        trending: (
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
              clipRule="evenodd"
            />
          </svg>
        ),
        listicle: (
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
            <path
              fillRule="evenodd"
              d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
              clipRule="evenodd"
            />
          </svg>
        ),
      },
      news: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z"
            clipRule="evenodd"
          />
          <path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z" />
        </svg>
      ),
      videos: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
        </svg>
      ),
      podcasts: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z"
            clipRule="evenodd"
          />
        </svg>
      ),
    };

    if (type === "articles" && subType && iconMap.articles[subType]) {
      return iconMap.articles[subType];
    }

    return iconMap[type] || iconMap.articles.standard;
  };

  const getTypeLabel = (type, subType) => {
    const labelMap = {
      articles: {
        standard: "Standard Articles",
        trending: "Trending Articles",
        listicle: "Listicle Articles",
        multipage: "Multipage Articles",
      },
      news: "News Articles",
      videos: "Video Content",
      podcasts: "Podcast Episodes",
    };

    if (type === "articles" && subType && labelMap.articles[subType]) {
      return labelMap.articles[subType];
    }

    return labelMap[type] || "Content Campaign";
  };

  if (isLoading) {
    return (
      <div className="atm-loading-state">
        <Spinner />
        <p>Loading campaigns...</p>
      </div>
    );
  }

  return (
    <div className="atm-campaign-manager">
      {/* Header */}
      <div className="atm-manager-header">
        <div className="atm-header-content">
          <div className="atm-header-main">
            <h1>Campaign Manager</h1>
            <p>Monitor and manage your automation campaigns</p>
          </div>
          <div className="atm-header-actions">
            <Button
              isPrimary
              onClick={() => setActiveView("hub")}
              className="atm-create-btn"
            >
              <svg
                className="w-5 h-5 mr-2"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                  clipRule="evenodd"
                />
              </svg>
              New Campaign
            </Button>
            <Button isSecondary onClick={loadCampaigns} disabled={isLoading}>
              <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                  clipRule="evenodd"
                />
              </svg>
              Refresh
            </Button>
          </div>
        </div>

        {/* Filters */}
        <div className="atm-filters-bar">
          <div className="atm-search-section">
            <div className="atm-search-input-wrapper">
              <svg
                className="atm-search-icon"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                  clipRule="evenodd"
                />
              </svg>
              <input
                type="text"
                placeholder="Search campaigns..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="atm-search-input"
              />
            </div>
          </div>

          <div className="atm-filter-tabs">
            {[
              { id: "all", label: "All Types" },
              { id: "articles", label: "Articles" },
              { id: "news", label: "News" },
              { id: "videos", label: "Videos" },
              { id: "podcasts", label: "Podcasts" },
            ].map((filter) => (
              <button
                key={filter.id}
                className={`atm-filter-tab ${filterType === filter.id ? "active" : ""}`}
                onClick={() => setFilterType(filter.id)}
              >
                {filter.label}
              </button>
            ))}
          </div>

          <SelectControl
            value={filterStatus}
            onChange={setFilterStatus}
            options={[
              { label: "All Status", value: "all" },
              { label: "Active", value: "active" },
              { label: "Paused", value: "paused" },
              { label: "Running", value: "running" },
              { label: "Failed", value: "failed" },
            ]}
            className="atm-status-filter"
          />
        </div>
      </div>

      {/* Status Message */}
      {statusMessage && (
        <div
          className={`atm-status-alert ${
            statusMessage.includes("successfully")
              ? "success"
              : statusMessage.includes("Error")
                ? "error"
                : "info"
          }`}
        >
          <div className="atm-alert-content">
            {statusMessage.includes("successfully") && (
              <svg
                className="atm-alert-icon"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clipRule="evenodd"
                />
              </svg>
            )}
            {statusMessage.includes("Error") && (
              <svg
                className="atm-alert-icon"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clipRule="evenodd"
                />
              </svg>
            )}
            <span>{statusMessage}</span>
          </div>
          <button
            onClick={() => setStatusMessage("")}
            className="atm-alert-close"
          >
            <svg fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        </div>
      )}

      {/* Campaign List */}
      {filteredCampaigns.length === 0 ? (
        <div className="atm-empty-campaigns">
          <div className="atm-empty-content">
            <div className="atm-empty-icon">
              <svg fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
            <h3>No campaigns found</h3>
            <p>
              {campaigns.length === 0
                ? "Create your first automation campaign to get started."
                : "No campaigns match your current filters."}
            </p>
            {campaigns.length === 0 && (
              <Button isPrimary onClick={() => setActiveView("hub")}>
                Create First Campaign
              </Button>
            )}
          </div>
        </div>
      ) : (
        <div className="atm-campaigns-list">
          {filteredCampaigns.map((campaign) => {
            const statusConfig = getStatusConfig(campaign);
            return (
              <div key={campaign.id} className="atm-campaign-row">
                <div className="atm-campaign-main">
                  <div className="atm-campaign-info">
                    <div className="atm-campaign-type-badge">
                      {getTypeIcon(campaign.type, campaign.sub_type)}
                      <span className="atm-type-text">
                        {getTypeLabel(campaign.type, campaign.sub_type)}
                      </span>
                    </div>

                    <h3 className="atm-campaign-name">{campaign.name}</h3>
                    <p className="atm-campaign-keyword">{campaign.keyword}</p>
                  </div>

                  <div className="atm-campaign-status">
                    <div
                      className={`atm-status-indicator ${statusConfig.color}`}
                    >
                      <span
                        className={`atm-status-dot ${statusConfig.dot}`}
                      ></span>
                      <span className="atm-status-text">
                        {statusConfig.text}
                      </span>
                    </div>
                  </div>

                  <div className="atm-campaign-schedule">
                    <div className="atm-schedule-info">
                      <span className="atm-schedule-frequency">
                        Every {campaign.schedule_value} {campaign.schedule_unit}
                        {campaign.schedule_value > 1 ? "s" : ""}
                      </span>
                      <span className="atm-next-run">
                        Next: {formatNextRun(campaign.next_run)}
                      </span>
                    </div>
                  </div>

                  <div className="atm-campaign-stats">
                    <div className="atm-stat">
                      <span className="atm-stat-number">
                        {campaign.total_executions || 0}
                      </span>
                      <span className="atm-stat-label">Total</span>
                    </div>
                    <div className="atm-stat">
                      <span className="atm-stat-number">
                        {campaign.successful_executions || 0}
                      </span>
                      <span className="atm-stat-label">Success</span>
                    </div>
                    <div className="atm-stat">
                      <span className="atm-stat-number">
                        {campaign.total_executions > 0
                          ? Math.round(
                              ((campaign.successful_executions || 0) /
                                campaign.total_executions) *
                                100
                            )
                          : 0}
                        %
                      </span>
                      <span className="atm-stat-label">Rate</span>
                    </div>
                  </div>
                </div>

                <div className="atm-campaign-actions">
                  <div className="atm-toggle-section">
                    <label className="atm-toggle-switch">
                      <input
                        type="checkbox"
                        checked={campaign.is_active == 1}
                        onChange={() =>
                          handleToggleCampaign(
                            campaign.id,
                            campaign.is_active == 1
                          )
                        }
                        disabled={isLoading}
                      />
                      <span className="atm-toggle-slider"></span>
                    </label>
                    <span className="atm-toggle-label">
                      {campaign.is_active == 1 ? "Active" : "Paused"}
                    </span>
                  </div>

                  <div className="atm-action-buttons">
                    <Button
                      isSmall
                      isSecondary
                      onClick={() =>
                        handleRunCampaign(campaign.id, campaign.name)
                      }
                      disabled={isLoading}
                    >
                      Run Now
                    </Button>

                    <Button
                      isSmall
                      isSecondary
                      onClick={() => handleEditCampaign(campaign)}
                    >
                      Edit
                    </Button>

                    <Button
                      isSmall
                      isDestructive
                      onClick={() => setShowDeleteModal(campaign)}
                    >
                      Delete
                    </Button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Delete Modal */}
      {showDeleteModal && (
        <Modal
          title="Delete Campaign"
          onRequestClose={() => setShowDeleteModal(null)}
          className="atm-delete-modal"
        >
          <p>
            Are you sure you want to delete{" "}
            <strong>"{showDeleteModal.name}"</strong>?
          </p>
          <p className="atm-warning-text">
            This action cannot be undone and will delete all execution history.
          </p>

          <div className="atm-modal-actions">
            <Button isSecondary onClick={() => setShowDeleteModal(null)}>
              Cancel
            </Button>
            <Button
              isDestructive
              onClick={() => {
                handleDeleteCampaign(showDeleteModal.id, showDeleteModal.name);
                setShowDeleteModal(null);
              }}
            >
              Delete Campaign
            </Button>
          </div>
        </Modal>
      )}
    </div>
  );
}

export default CampaignManager;
