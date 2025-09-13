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
        standard: {
          color: "blue",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.828-2.828z" />
            </svg>
          ),
          label: "Standard Articles",
        },
        trending: {
          color: "red",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path
                fillRule="evenodd"
                d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "Trending Articles",
        },
        listicle: {
          color: "green",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
              <path
                fillRule="evenodd"
                d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "Listicle Articles",
        },
        multipage: {
          color: "amber",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
              <path
                fillRule="evenodd"
                d="M3 8a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "Multipage Articles",
        },
      },
      news: {
        search: {
          color: "indigo",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path
                fillRule="evenodd"
                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "News Search",
        },
        twitter: {
          color: "slate",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path d="M18.258 3.266c-.926.414-1.925.694-2.973.82a5.18 5.18 0 002.269-2.855 10.36 10.36 0 01-3.287 1.256 5.173 5.173 0 00-8.806 4.717A14.68 14.68 0 011.392 2.25a5.168 5.168 0 001.601 6.896 5.142 5.142 0 01-2.341-.647v.065a5.177 5.177 0 004.148 5.073 5.19 5.19 0 01-2.336.089 5.179 5.179 0 004.832 3.596 10.368 10.368 0 01-6.421 2.213c-.417 0-.828-.024-1.233-.072a14.623 14.623 0 007.919 2.321c9.502 0 14.697-7.869 14.697-14.697 0-.224-.005-.447-.014-.67A10.498 10.498 0 0020 3.616a10.33 10.33 0 01-1.742.65z" />
            </svg>
          ),
          label: "Twitter/X News",
        },
        rss: {
          color: "orange",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path d="M3.429 2.776c7.913 0 14.324 6.411 14.324 14.324h-2.861c0-6.323-5.14-11.463-11.463-11.463V2.776zM3.429 8.649c3.036 0 5.502 2.466 5.502 5.502H6.069c0-1.457-1.184-2.641-2.641-2.641V8.649zM5.714 15.429c.79 0 1.429.639 1.429 1.429s-.639 1.429-1.429 1.429-1.429-.639-1.429-1.429.639-1.429 1.429-1.429z" />
            </svg>
          ),
          label: "RSS Feeds",
        },
        apis: {
          color: "emerald",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path
                fillRule="evenodd"
                d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "APIs News",
        },
        live: {
          color: "purple",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path
                fillRule="evenodd"
                d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: "Live News",
        },
      },
      videos: {
        color: "rose",
        icon: (
          <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
          </svg>
        ),
        label: "Video Content",
      },
      podcasts: {
        color: "cyan",
        icon: (
          <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
            <path
              fillRule="evenodd"
              d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z"
              clipRule="evenodd"
            />
          </svg>
        ),
        label: "Podcast Episodes",
      },
    };

    const typeConfigs = configs[type];
    if (typeConfigs && typeof typeConfigs === "object" && subType) {
      return (
        typeConfigs[subType] || {
          color: "gray",
          icon: (
            <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
              <path
                fillRule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                clipRule="evenodd"
              />
            </svg>
          ),
          label: type,
        }
      );
    }
    return (
      typeConfigs || {
        color: "gray",
        icon: (
          <svg fill="currentColor" viewBox="0 0 20 20" width="16" height="16">
            <path
              fillRule="evenodd"
              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
              clipRule="evenodd"
            />
          </svg>
        ),
        label: type,
      }
    );
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
