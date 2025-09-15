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
  // Add this debug logging
  console.log("Campaign debug:", {
    id: campaign.id,
    name: campaign.name,
    total_executions: campaign.total_executions,
    successful_executions: campaign.successful_executions,
    raw_campaign: campaign,
  });
  const [isRunning, setIsRunning] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  console.log("Campaign data:", campaign);
  console.log("Toggle function:", onToggle);

  const getStatusConfig = (campaign) => {
    if (isRunning) {
      return {
        color: "border-blue-200 bg-blue-50",
        dot: "bg-blue-500 animate-pulse",
        text: "Running",
        textColor: "text-blue-700",
      };
    }

    // Fixed status check - handle both string and number values
    const isActive =
      campaign.is_active == 1 ||
      campaign.is_active === true ||
      campaign.is_active === "1";
    const status = isActive ? "active" : "paused";

    const configs = {
      active: {
        color: "border-green-200 bg-green-50",
        dot: "bg-green-500",
        text: "Active",
        textColor: "text-green-700",
      },
      paused: {
        color: "border-gray-200 bg-gray-50",
        dot: "bg-gray-500",
        text: "Paused",
        textColor: "text-gray-700",
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
        creative: {
          icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.828-2.828z" />
            </svg>
          ),
          color: "bg-blue-100 text-blue-700",
          label: "Articles",
        },
        trending: {
          icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path
                fillRule="evenodd"
                d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
                clipRule="evenodd"
              />
            </svg>
          ),
          color: "bg-purple-100 text-purple-700",
          label: "Trending",
        },
        listicle: {
          icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
              <path
                fillRule="evenodd"
                d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                clipRule="evenodd"
              />
            </svg>
          ),
          color: "bg-green-100 text-green-700",
          label: "Listicles",
        },
        multipage: {
          icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
              <path
                fillRule="evenodd"
                d="M3 8a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                clipRule="evenodd"
              />
            </svg>
          ),
          color: "bg-amber-100 text-amber-700",
          label: "Multipage",
        },
        standard: {
          icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
            </svg>
          ),
          color: "bg-blue-100 text-blue-700",
          label: "Articles",
        },
      },
      news: {
        icon: (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20,11H4V8H20M20,15H13V13H20M20,19H13V17H20M11,19H4V13H11M20.33,4.67L18.67,3L17,4.67L15.33,3L13.67,4.67L12,3L10.33,4.67L8.67,3L7,4.67L5.33,3L3.67,4.67L2,3V19A2,2 0 0,0 4,21H20A2,2 0 0,0 22,19V3L20.33,4.67Z" />
          </svg>
        ),
        color: "bg-emerald-100 text-emerald-700",
        label: "News",
      },
      videos: {
        icon: (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z" />
          </svg>
        ),
        color: "bg-red-100 text-red-700",
        label: "Videos",
      },
      podcasts: {
        icon: (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z" />
          </svg>
        ),
        color: "bg-orange-100 text-orange-700",
        label: "Podcasts",
      },
    };

    // Better handling of article sub-types
    if (type === "articles") {
      const subTypeConfig =
        configs.articles[subType] || configs.articles.standard;
      return subTypeConfig;
    }

    return (
      configs[type] || {
        icon: (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
          </svg>
        ),
        color: "bg-gray-100 text-gray-700",
        label: type ? type.charAt(0).toUpperCase() + type.slice(1) : "Campaign",
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

  // Also ensure the values are properly converted to numbers
  const totalExecutions = parseInt(campaign.total_executions) || 0;
  const successfulExecutions = parseInt(campaign.successful_executions) || 0;
  const calculatedSuccessRate =
    totalExecutions > 0
      ? Math.round((successfulExecutions / totalExecutions) * 100)
      : 0;

  const menuControls = [
    {
      title: "Run Now",
      onClick: handleRunNow,
      icon: isRunning ? (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z" />
        </svg>
      ) : (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8,5.14V19.14L19,12.14L8,5.14Z" />
        </svg>
      ),
    },
    {
      title: "Edit Campaign",
      onClick: () => onEdit(campaign),
      icon: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z" />
        </svg>
      ),
    },
    {
      title: "Duplicate",
      onClick: () => onDuplicate(campaign),
      icon: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z" />
        </svg>
      ),
    },
    {
      title: "View Logs",
      onClick: () => onViewLogs(campaign.id),
      icon: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M9,5V9H21V5M9,19H21V15H9M9,14H21V10H9M4,9H8L6,7M4,19H8L6,17M4,14H8L6,12" />
        </svg>
      ),
    },
    {
      title: "Delete",
      onClick: () => setShowDeleteModal(true),
      icon: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z" />
        </svg>
      ),
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
                {parseInt(campaign.successful_executions) || 0}
              </span>
              <span className="atm-stat-label">Posts</span>
            </div>
            <div className="atm-stat-item">
              <span className="atm-stat-value">
                {campaign.total_executions > 0
                  ? Math.round(
                      (campaign.successful_executions /
                        campaign.total_executions) *
                        100
                    )
                  : 0}
                %
              </span>
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
              <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20M12.5,7V12.25L17,14.92L16.25,16.15L11,13V7H12.5Z" />
            </svg>
            <span>Next run: {formatNextRun(campaign.next_run)}</span>
          </div>
        </div>

        {/* Toggle Switch - Fixed */}
        <div className="atm-card-toggle">
          <label className="atm-toggle-modern">
            <input
              type="checkbox"
              checked={campaign.is_active == 1}
              onChange={(e) => {
                e.preventDefault(); // Add this to prevent form submission
                console.log(
                  "Toggle clicked:",
                  campaign.id,
                  "new state:",
                  e.target.checked
                );
                onToggle(campaign.id, e.target.checked);
              }}
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
  setActiveView, // Make sure this prop is passed
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
                <path d="M17.65,6.35C16.2,4.9 14.21,4 12,4C7.58,4 4.01,7.58 4.01,12C4.01,16.42 7.58,20 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18C8.69,18 6,15.31 6,12C6,8.69 8.69,6 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" />
              </svg>
              Refresh
            </Button>

            <Button
              variant="primary"
              className="atm-create-btn"
              onClick={() => setActiveView && setActiveView("generator")}
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z" />
              </svg>
              New Campaign
            </Button>
          </div>
        </div>

        {/* Stats Overview */}
        <div className="atm-stats-overview">
          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-blue-100 text-blue-600">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M9,17H7V10H9V17M13,17H11V7H13V17M17,17H15V13H17V17M19.5,19.1H4.5V5H19.5V19.1Z" />
              </svg>
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.total}</span>
              <span className="atm-overview-label">Total Campaigns</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-green-100 text-green-600">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" />
              </svg>
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.active}</span>
              <span className="atm-overview-label">Active</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-yellow-100 text-yellow-600">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M14,19H18V5H14M6,19H10V5H6V19Z" />
              </svg>
            </div>
            <div className="atm-overview-content">
              <span className="atm-overview-number">{stats.paused}</span>
              <span className="atm-overview-label">Paused</span>
            </div>
          </div>

          <div className="atm-overview-card">
            <div className="atm-overview-icon bg-purple-100 text-purple-600">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
              </svg>
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
            <path d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z" />
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
                <path d="M3,11H11V3H3M3,21H11V13H3M13,21H21V13H13M13,3V11H21V3" />
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
                <path d="M3,5H21V7H3V5M3,13V11H21V13H3M3,19V17H21V19H3Z" />
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
            {statusMessage.includes("successfully") && (
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" />
              </svg>
            )}
            {statusMessage.includes("Error") && (
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
              </svg>
            )}
            <span>{statusMessage}</span>
          </div>
          <button
            onClick={() => {
              /* clear message */
            }}
            className="atm-status-close"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z" />
            </svg>
          </button>
        </div>
      )}

      {/* Campaigns Grid */}
      {filteredCampaigns.length === 0 ? (
        <div className="atm-empty-state-modern">
          <div className="atm-empty-content">
            <div className="atm-empty-icon">
              <svg
                width="64"
                height="64"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M12,2A2,2 0 0,1 14,4C14,4.74 13.6,5.39 13,5.73V7A1,1 0 0,0 14,8H20A2,2 0 0,1 22,10V20A2,2 0 0,1 20,22H4A2,2 0 0,1 2,20V10C2,8.89 2.9,8 4,8H10A1,1 0 0,0 11,7V5.73C10.4,5.39 10,4.74 10,4A2,2 0 0,1 12,2M4,10V20H20V10H4M6,12H18V14H6V12M6,16H14V18H6V16Z" />
              </svg>
            </div>
            <h3>No campaigns found</h3>
            <p>
              {campaigns.length === 0
                ? "Create your first automation campaign to start generating content automatically."
                : "No campaigns match your current filters. Try adjusting your search criteria."}
            </p>
            {campaigns.length === 0 && (
              <Button
                variant="primary"
                className="atm-cta-button"
                onClick={() => setActiveView && setActiveView("generator")}
              >
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
