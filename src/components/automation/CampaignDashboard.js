import { useState, useEffect } from "@wordpress/element";
import { Button, ToggleControl, Modal, Spinner } from "@wordpress/components";

const CampaignCard = ({
  campaign,
  onEdit,
  onToggle,
  onDelete,
  onRunNow,
  onViewStats,
}) => {
  const [isRunning, setIsRunning] = useState(campaign.status === "running");
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  const getStatusConfig = (status) => {
    const configs = {
      idle: {
        color: "bg-gray-100 text-gray-700",
        dot: "bg-gray-400",
        text: "Idle",
      },
      running: {
        color: "bg-green-100 text-green-700",
        dot: "bg-green-500 animate-pulse",
        text: "Running",
      },
      paused: {
        color: "bg-yellow-100 text-yellow-700",
        dot: "bg-yellow-500",
        text: "Paused",
      },
      failed: {
        color: "bg-red-100 text-red-700",
        dot: "bg-red-500",
        text: "Failed",
      },
    };
    return configs[status] || configs.idle;
  };

  const getTypeConfig = (type, subType) => {
    const configs = {
      articles: {
        standard: { color: "blue", icon: "📝", label: "Standard Articles" },
        trending: { color: "red", icon: "🔥", label: "Trending Articles" },
        listicle: { color: "green", icon: "📋", label: "Listicle Articles" },
        multipage: { color: "amber", icon: "📚", label: "Multipage Articles" },
      },
      news: {
        search: { color: "indigo", icon: "🔍", label: "News Search" },
        twitter: { color: "slate", icon: "𝕏", label: "Twitter/X News" },
        rss: { color: "orange", icon: "📡", label: "RSS Feeds" },
        apis: { color: "emerald", icon: "🔌", label: "APIs News" },
        live: { color: "purple", icon: "📺", label: "Live News" },
      },
      videos: { color: "rose", icon: "🎥", label: "Video Content" },
      podcasts: { color: "cyan", icon: "🎙️", label: "Podcast Episodes" },
    };

    const typeConfigs = configs[type];
    if (typeConfigs && typeof typeConfigs === "object" && subType) {
      return typeConfigs[subType] || { color: "gray", icon: "❓", label: type };
    }
    return typeConfigs || { color: "gray", icon: "❓", label: type };
  };

  const statusConfig = getStatusConfig(campaign.status || "idle");
  const typeConfig = getTypeConfig(campaign.type, campaign.sub_type);

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

  return (
    <>
      <div className="atm-campaign-card">
        <div className="atm-campaign-header">
          <div className="atm-campaign-type">
            <span className="atm-type-icon">{typeConfig.icon}</span>
            <span className="atm-type-label">{typeConfig.label}</span>
          </div>

          <div className={`atm-status-badge ${statusConfig.color}`}>
            <span className={`atm-status-dot ${statusConfig.dot}`}></span>
            {statusConfig.text}
          </div>
        </div>

        <div className="atm-campaign-info">
          <h3 className="atm-campaign-name">{campaign.name}</h3>
          <p className="atm-campaign-keyword">{campaign.keyword}</p>
        </div>

        <div className="atm-campaign-stats">
          <div className="atm-stat-item">
            <span className="atm-stat-label">Schedule</span>
            <span className="atm-stat-value">
              Every {campaign.schedule_value} {campaign.schedule_unit}
              {campaign.schedule_value > 1 ? "s" : ""}
            </span>
          </div>

          <div className="atm-stat-item">
            <span className="atm-stat-label">Next Run</span>
            <span className="atm-stat-value">
              {formatNextRun(campaign.next_run)}
            </span>
          </div>

          <div className="atm-stat-item">
            <span className="atm-stat-label">Success Rate</span>
            <span className="atm-stat-value">
              {campaign.total_executions > 0
                ? `${Math.round((campaign.successful_executions / campaign.total_executions) * 100)}%`
                : "—"}
            </span>
          </div>

          <div className="atm-stat-item">
            <span className="atm-stat-label">Total Posts</span>
            <span className="atm-stat-value">
              {campaign.successful_executions || 0}
            </span>
          </div>
        </div>

        <div className="atm-campaign-controls">
          <div className="atm-campaign-toggle">
            <ToggleControl
              checked={campaign.is_active == 1}
              onChange={() => onToggle(campaign.id, campaign.is_active == 1)}
              __nextHasNoMarginBottom
            />
            <span className="atm-toggle-label">
              {campaign.is_active == 1 ? "Active" : "Paused"}
            </span>
          </div>

          <div className="atm-campaign-actions">
            <Button
              variant="secondary"
              size="small"
              onClick={() => onRunNow(campaign.id)}
              disabled={isRunning}
              icon={isRunning ? <Spinner /> : null}
            >
              {isRunning ? "Running..." : "Run Now"}
            </Button>

            <Button
              variant="secondary"
              size="small"
              onClick={() => onViewStats(campaign.id)}
            >
              Stats
            </Button>

            <Button
              variant="secondary"
              size="small"
              onClick={() => onEdit(campaign)}
            >
              Edit
            </Button>

            <Button
              variant="secondary"
              size="small"
              onClick={() => setShowDeleteModal(true)}
              className="atm-delete-button"
            >
              Delete
            </Button>
          </div>
        </div>
      </div>

      {showDeleteModal && (
        <Modal
          title="Delete Campaign"
          onRequestClose={() => setShowDeleteModal(false)}
          className="atm-delete-modal"
        >
          <p>Are you sure you want to delete the campaign "{campaign.name}"?</p>
          <p className="atm-warning-text">
            This action cannot be undone and will delete all execution history.
          </p>

          <div className="atm-modal-actions">
            <Button variant="primary" onClick={() => setShowDeleteModal(false)}>
              Cancel
            </Button>
            <Button
              variant="primary"
              onClick={() => {
                onDelete(campaign.id, campaign.name);
                setShowDeleteModal(false);
              }}
              className="atm-delete-confirm"
            >
              Delete Campaign
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
};

function CampaignDashboard({
  campaigns,
  isLoading,
  onEditCampaign,
  onDeleteCampaign,
  onToggleCampaign,
  onRunCampaign,
  refreshCampaigns,
}) {
  const [filteredCampaigns, setFilteredCampaigns] = useState(campaigns);
  const [filterType, setFilterType] = useState("all");
  const [filterStatus, setFilterStatus] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");

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

  const handleViewStats = (campaignId) => {
    console.log("View stats for campaign:", campaignId);
  };

  if (isLoading) {
    return (
      <div style={{ textAlign: "center", padding: "40px" }}>
        <Spinner />
        <p>Loading campaigns...</p>
      </div>
    );
  }

  if (!campaigns || !campaigns.length) {
    return (
      <div className="atm-empty-state">
        <div className="atm-empty-icon">🤖</div>
        <h3>No campaigns yet</h3>
        <p>
          Create your first automation campaign to start generating content
          automatically.
        </p>
      </div>
    );
  }

  return (
    <div className="atm-campaign-dashboard">
      <div className="atm-dashboard-filters">
        <div className="atm-search-box">
          <input
            type="text"
            placeholder="Search campaigns..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="atm-search-input"
          />
        </div>

        <div className="atm-filter-tabs">
          <button
            className={`atm-filter-tab ${filterType === "all" ? "active" : ""}`}
            onClick={() => setFilterType("all")}
          >
            All Types
          </button>
          <button
            className={`atm-filter-tab ${filterType === "articles" ? "active" : ""}`}
            onClick={() => setFilterType("articles")}
          >
            Articles
          </button>
          <button
            className={`atm-filter-tab ${filterType === "news" ? "active" : ""}`}
            onClick={() => setFilterType("news")}
          >
            News
          </button>
          <button
            className={`atm-filter-tab ${filterType === "videos" ? "active" : ""}`}
            onClick={() => setFilterType("videos")}
          >
            Videos
          </button>
          <button
            className={`atm-filter-tab ${filterType === "podcasts" ? "active" : ""}`}
            onClick={() => setFilterType("podcasts")}
          >
            Podcasts
          </button>
        </div>

        <div className="atm-status-filter">
          <select
            value={filterStatus}
            onChange={(e) => setFilterStatus(e.target.value)}
            className="atm-status-select"
          >
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="running">Running</option>
            <option value="failed">Failed</option>
          </select>
        </div>
      </div>

      <div className="atm-campaigns-grid">
        {filteredCampaigns.map((campaign) => (
          <CampaignCard
            key={campaign.id}
            campaign={campaign}
            onEdit={onEditCampaign}
            onToggle={onToggleCampaign}
            onDelete={onDeleteCampaign}
            onRunNow={onRunCampaign}
            onViewStats={handleViewStats}
          />
        ))}
      </div>
    </div>
  );
}

export default CampaignDashboard;
