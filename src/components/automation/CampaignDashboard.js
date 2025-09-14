import { useState, useEffect } from "@wordpress/element";
import { Button, Modal, Spinner, DropdownMenu } from "@wordpress/components";
import { chevronDown, moreVertical } from "@wordpress/icons";

const CampaignCard = ({
  campaign,
  onEdit,
  onToggle,
  onDelete,
  onRunNow,
  onDuplicate,
  onViewLogs,
}) => {
  const [isRunning, setIsRunning] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  const getStatusConfig = (campaign) => {
    if (isRunning) {
      return {
        color: "border-blue-200 bg-blue-50",
        dot: "bg-blue-500 animate-pulse",
        text: "Running",
        textColor: "text-blue-700",
      };
    }

    const status = campaign.is_active == 1 ? "active" : "paused";
    const configs = {
      active: {
        color: "border-green-200 bg-green-50",
        dot: "bg-green-500",
        text: "Active",
        textColor: "text-green-700",
      },
      paused: {
        color: "border-yellow-200 bg-yellow-50",
        dot: "bg-yellow-500",
        text: "Paused",
        textColor: "text-yellow-700",
      },
      failed: {
        color: "border-red-200 bg-red-50",
        dot: "bg-red-500",
        text: "Failed",
        textColor: "text-red-700",
      },
    };
    return configs[status] || configs.paused;
  };

  const getTypeConfig = (type, subType) => {
    const configs = {
      articles: {
        standard: {
          icon: "📝",
          color: "bg-blue-100 text-blue-700",
          label: "Articles",
        },
        trending: {
          icon: "📈",
          color: "bg-purple-100 text-purple-700",
          label: "Trending",
        },
        listicle: {
          icon: "📋",
          color: "bg-green-100 text-green-700",
          label: "Listicles",
        },
        multipage: {
          icon: "📄",
          color: "bg-amber-100 text-amber-700",
          label: "Multipage",
        },
      },
      news: {
        icon: "📰",
        color: "bg-emerald-100 text-emerald-700",
        label: "News",
      },
      videos: { icon: "🎥", color: "bg-red-100 text-red-700", label: "Videos" },
      podcasts: {
        icon: "🎙️",
        color: "bg-orange-100 text-orange-700",
        label: "Podcasts",
      },
    };

    if (type === "articles" && subType && configs.articles[subType]) {
      return configs.articles[subType];
    }
    return (
      configs[type] || {
        icon: "⚙️",
        color: "bg-gray-100 text-gray-700",
        label: "Campaign",
      }
    );
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
      const days = Math.floor(diffHours / 24);
      return `${days}d ${diffHours % 24}h`;
    } else if (diffHours > 0) {
      return `${diffHours}h ${diffMins}m`;
    } else {
      return `${diffMins}m`;
    }
  };

  const handleRunNow = async () => {
    setIsRunning(true);
    try {
      await onRunNow(campaign.id);
    } finally {
      setIsRunning(false);
    }
  };

  const statusConfig = getStatusConfig(campaign);
  const typeConfig = getTypeConfig(campaign.type, campaign.sub_type);
  const successRate =
    campaign.total_executions > 0
      ? Math.round(
          (campaign.successful_executions / campaign.total_executions) * 100
        )
      : 0;

  const menuControls = [
    {
      title: "Run Now",
      onClick: handleRunNow,
      icon: isRunning ? "⟳" : "▶️",
    },
    {
      title: "Edit Campaign",
      onClick: () => onEdit(campaign),
      icon: "✏️",
    },
    {
      title: "Duplicate",
      onClick: () => onDuplicate(campaign),
      icon: "📋",
    },
    {
      title: "View Logs",
      onClick: () => onViewLogs(campaign.id),
      icon: "📊",
    },
    {
      title: "Delete",
      onClick: () => setShowDeleteModal(true),
      icon: "🗑️",
      className: "text-red-600",
    },
  ];

  return (
    <>
      <div className="atm-modern-campaign-card">
        {/* Header with Type and Status */}
        <div className="atm-card-header">
          <div className={`atm-type-badge ${typeConfig.color}`}>
            <span className="atm-type-icon">{typeConfig.icon}</span>
            <span className="atm-type-text">{typeConfig.label}</span>
          </div>

          <div className="atm-card-actions">
            <div className={`atm-status-indicator ${statusConfig.color}`}>
              <span className={`atm-status-dot ${statusConfig.dot}`}></span>
              <span className={`atm-status-text ${statusConfig.textColor}`}>
                {statusConfig.text}
              </span>
            </div>

            <DropdownMenu
              icon={moreVertical}
              controls={menuControls}
              popoverProps={{ position: "bottom left" }}
              className="atm-card-menu"
            />
          </div>
        </div>

        {/* Campaign Info */}
        <div className="atm-card-content">
          <h3 className="atm-campaign-title">{campaign.name}</h3>
          <p className="atm-campaign-keyword">{campaign.keyword}</p>

          {/* Quick Stats Grid */}
          <div className="atm-stats-grid">
            <div className="atm-stat-item">
              <span className="atm-stat-value">
                {campaign.successful_executions || 0}
              </span>
              <span className="atm-stat-label">Posts</span>
            </div>
            <div className="atm-stat-item">
              <span className="atm-stat-value">{successRate}%</span>
              <span className="atm-stat-label">Success</span>
            </div>
            <div className="atm-stat-item">
              <span className="atm-stat-value">
                {campaign.schedule_value}
                {campaign.schedule_unit.charAt(0)}
              </span>
              <span className="atm-stat-label">Frequency</span>
            </div>
          </div>

          {/* Next Run Info */}
          <div className="atm-next-run">
            <svg
              className="atm-clock-icon"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            <span>Next run: {formatNextRun(campaign.next_run)}</span>
          </div>
        </div>

        {/* Toggle Switch */}
        <div className="atm-card-toggle">
          <label className="atm-toggle-modern">
            <input
              type="checkbox"
              checked={campaign.is_active == 1}
              onChange={() => onToggle(campaign.id, campaign.is_active == 1)}
            />
            <span className="atm-toggle-slider-modern"></span>
          </label>
        </div>
      </div>

      {/* Delete Confirmation Modal */}
      {showDeleteModal && (
        <Modal
          title="Delete Campaign"
          onRequestClose={() => setShowDeleteModal(false)}
          className="atm-delete-modal-modern"
        >
          <div className="atm-modal-content">
            <div className="atm-modal-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                <circle
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="#ef4444"
                  strokeWidth="2"
                />
                <path d="M15 9l-6 6m0-6l6 6" stroke="#ef4444" strokeWidth="2" />
              </svg>
            </div>
            <h3>Delete "{campaign.name}"?</h3>
            <p>
              This will permanently delete the campaign and all its execution
              history. This action cannot be undone.
            </p>

            <div className="atm-modal-actions">
              <Button
                variant="tertiary"
                onClick={() => setShowDeleteModal(false)}
              >
                Cancel
              </Button>
              <Button
                variant="primary"
                className="atm-delete-button"
                onClick={() => {
                  onDelete(campaign.id, campaign.name);
                  setShowDeleteModal(false);
                }}
              >
                Delete Campaign
              </Button>
            </div>
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
  statusMessage,
}) {
  const [filteredCampaigns, setFilteredCampaigns] = useState(campaigns);
  const [filterType, setFilterType] = useState("all");
  const [filterStatus, setFilterStatus] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [viewMode, setViewMode] = useState("grid"); // grid or list

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

  const handleDuplicate = (campaign) => {
    console.log("Duplicate campaign:", campaign);
    // TODO: Implement duplicate functionality
  };

  const handleViewLogs = (campaignId) => {
    console.log("View logs for:", campaignId);
    // TODO: Implement logs view
  };

  // Calculate dashboard stats
  const stats = {
    total: campaigns.length,
    active: campaigns.filter((c) => c.is_active == 1).length,
    paused: campaigns.filter((c) => c.is_active == 0).length,
    totalPosts: campaigns.reduce(
      (sum, c) => sum + (c.successful_executions || 0),
      0
    ),
  };

  const filterOptions = [
    { id: "all", label: "All Types", count: campaigns.length },
    {
      id: "articles",
      label: "Articles",
      count: campaigns.filter((c) => c.type === "articles").length,
    },
    {
      id: "news",
      label: "News",
      count: campaigns.filter((c) => c.type === "news").length,
    },
    {
      id: "videos",
      label: "Videos",
      count: campaigns.filter((c) => c.type === "videos").length,
    },
    {
      id: "podcasts",
      label: "Podcasts",
      count: campaigns.filter((c) => c.type === "podcasts").length,
    },
  ];

  if (isLoading) {
    return (
      <div className="atm-loading-modern">
        <div className="atm-loading-content">
          <Spinner />
          <h3>Loading campaigns...</h3>
          <p>Fetching your automation campaigns</p>
        </div>
      </div>
    );
  }

  return (
    <div className="atm-dashboard-modern">
      {/* Dashboard Header */}
      <div className="atm-dashboard-header">
        <div className="atm-header-main">
          <div className="atm-header-text">
            <h1>Campaign Manager</h1>
            <p>Monitor and manage your automation campaigns</p>
          </div>

          <div className="atm-header-actions">
            <Button
              variant="tertiary"
              onClick={refreshCampaigns}
              className="atm-refresh-btn"
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z" />
              </svg>
              Refresh
            </Button>

            <Button variant="primary" className="atm-create-btn">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
              </svg>
              New Campaign
            </Button>
          </div>
        </div>

        {/* Stats Overview */}
        <div className="atm-stats-overview">
          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-blue-100 text-blue-600">
              📊
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.total}</span>
              <span className="atm-overview-label">Total Campaigns</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-green-100 text-green-600">
              ✅
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.active}</span>
              <span className="atm-overview-label">Active</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-yellow-100 text-yellow-600">
              ⏸️
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.paused}</span>
              <span className="atm-overview-label">Paused</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-purple-100 text-purple-600">
              📝
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.totalPosts}</span>
              <span className="atm-overview-label">Posts Created</span>
            </div>
          </div>
        </div>
      </div>

      {/* Filters and Search */}
      <div className="atm-filters-modern">
        <div className="atm-search-modern">
          <svg
            className="atm-search-icon"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
          </svg>
          <input
            type="text"
            placeholder="Search campaigns..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="atm-search-input-modern"
          />
        </div>

        <div className="atm-filter-chips">
          {filterOptions.map((filter) => (
            <button
              key={filter.id}
              className={`atm-filter-chip ${filterType === filter.id ? "active" : ""}`}
              onClick={() => setFilterType(filter.id)}
            >
              {filter.label}
              <span className="atm-chip-count">{filter.count}</span>
            </button>
          ))}
        </div>

        <div className="atm-view-controls">
          <div className="atm-status-filter">
            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="atm-status-select-modern"
            >
              <option value="all">All Status</option>
              <option value="active">Active Only</option>
              <option value="paused">Paused Only</option>
            </select>
          </div>

          <div className="atm-view-toggle">
            <button
              className={`atm-view-btn ${viewMode === "grid" ? "active" : ""}`}
              onClick={() => setViewMode("grid")}
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z" />
              </svg>
            </button>
            <button
              className={`atm-view-btn ${viewMode === "list" ? "active" : ""}`}
              onClick={() => setViewMode("list")}
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Status Message */}
      {statusMessage && (
        <div
          className={`atm-status-message-modern ${
            statusMessage.includes("successfully")
              ? "success"
              : statusMessage.includes("Error")
                ? "error"
                : "info"
          }`}
        >
          <div className="atm-status-content">
            {statusMessage.includes("successfully") && <span>✅</span>}
            {statusMessage.includes("Error") && <span>❌</span>}
            <span>{statusMessage}</span>
          </div>
          <button
            onClick={() => {
              /* clear message */
            }}
            className="atm-status-close"
          >
            ×
          </button>
        </div>
      )}

      {/* Campaigns Grid */}
      {filteredCampaigns.length === 0 ? (
        <div className="atm-empty-state-modern">
          <div className="atm-empty-content">
            <div className="atm-empty-icon">🤖</div>
            <h3>No campaigns found</h3>
            <p>
              {campaigns.length === 0
                ? "Create your first automation campaign to start generating content automatically."
                : "No campaigns match your current filters. Try adjusting your search criteria."}
            </p>
            {campaigns.length === 0 && (
              <Button variant="primary" className="atm-cta-button">
                Create First Campaign
              </Button>
            )}
          </div>
        </div>
      ) : (
        <div
          className={`atm-campaigns-container ${viewMode === "list" ? "list-view" : "grid-view"}`}
        >
          {filteredCampaigns.map((campaign) => (
            <CampaignCard
              key={campaign.id}
              campaign={campaign}
              onEdit={onEditCampaign}
              onToggle={onToggleCampaign}
              onDelete={onDeleteCampaign}
              onRunNow={onRunCampaign}
              onDuplicate={handleDuplicate}
              onViewLogs={handleViewLogs}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default CampaignDashboard;
