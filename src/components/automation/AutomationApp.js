// src/components/automation/AutomationApp.js
import { useState, useEffect } from "@wordpress/element";
import AutomationHub from "./AutomationHub";
import AutoArticleGenerator from "./AutoArticleGenerator";
import AutoNewsGenerator from "./AutoNewsGenerator";
import AutoVideoGenerator from "./AutoVideoGenerator";
import AutoPodcastGenerator from "./AutoPodcastGenerator";

// Add this import for your dashboard
import CampaignDashboard from "./CampaignDashboard"; // <-- ADD THIS LINE

// You can probably remove CampaignManager if CampaignDashboard is replacing it
// import CampaignManager from "./CampaignManager";

function AutomationApp() {
  const [activeView, setActiveView] = useState("hub");
  const [editingCampaign, setEditingCampaign] = useState(null);

  // Get page type from root element
  const rootElement = document.getElementById("atm-automation-root");
  const pageType = rootElement?.getAttribute("data-page") || "main";

  // Set initial view based on page type
  useEffect(() => {
    if (pageType === "campaigns") {
      setActiveView("campaigns");
    } else {
      setActiveView("hub");
    }
  }, [pageType]);

  const navigationItems = [
    {
      section: "AUTOMATION TYPES",
      items: [
        {
          id: "articles",
          label: "General Articles",
          icon: (
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
          color: "#6366f1",
        },
        {
          id: "news",
          label: "Automated News",
          icon: (
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
          color: "#059669",
        },
        {
          id: "videos",
          label: "Auto Videos",
          icon: (
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
          color: "#dc2626",
        },
        {
          id: "podcasts",
          label: "Auto Podcast",
          icon: (
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
          color: "#f97316",
        },
      ],
    },
    {
      section: "MANAGEMENT",
      items: [
        {
          id: "campaigns",
          label: "Campaign Manager",
          icon: (
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
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
              />
            </svg>
          ),
          color: "#8b5cf6",
        },
      ],
    },
  ];

  const renderActiveView = () => {
    switch (activeView) {
      case "hub":
        return <AutomationHub setActiveView={setActiveView} />;
      case "articles":
        return (
          <AutoArticleGenerator
            setActiveView={setActiveView}
            editingCampaign={editingCampaign}
          />
        );
      case "news":
        return (
          <AutoNewsGenerator
            setActiveView={setActiveView}
            editingCampaign={editingCampaign}
          />
        );
      case "videos":
        return (
          <AutoVideoGenerator
            setActiveView={setActiveView}
            editingCampaign={editingCampaign}
          />
        );
      case "podcasts":
        return (
          <AutoPodcastGenerator
            setActiveView={setActiveView}
            editingCampaign={editingCampaign}
          />
        );
      case "campaigns":
        // This is the key change: use CampaignDashboard
        return (
          <CampaignDashboard
            // CampaignDashboard needs these props to function correctly
            campaigns={campaigns} // You'll need to fetch campaigns here
            refreshCampaigns={loadCampaigns} // Function to reload campaigns
            onEditCampaign={(campaign) => {
              setEditingCampaign(campaign);
              setActiveView(campaign.type);
            }}
            onDeleteCampaign={handleDeleteCampaign} // Pass delete function
            onToggleCampaign={handleToggleCampaign} // Pass toggle function
            onRunCampaign={handleRunCampaign} // Pass run now function
          />
        );
      default:
        return <AutomationHub setActiveView={setActiveView} />;
    }
  };

  const getPageTitle = () => {
    const titles = {
      hub: "AI Automation",
      articles: "General Articles Automation",
      news: "Automated News Generation",
      videos: "Auto Video Embedding",
      podcasts: "Automated Podcast Creation",
      campaigns: "Campaign Manager",
    };
    return titles[activeView] || "AI Automation";
  };

  const getPageSubtitle = () => {
    const subtitles = {
      hub: "Automate your content creation with AI-powered campaigns",
      articles:
        "Create automated article generation campaigns with custom prompts and settings",
      news: "Generate articles automatically from latest news sources and RSS feeds",
      videos:
        "Find and embed YouTube videos automatically with generated descriptions",
      podcasts: "Create articles with automated podcast audio generation",
      campaigns: "Manage, monitor, and edit your automation campaigns",
    };
    return subtitles[activeView] || "Automate your content creation";
  };

  return (
    <div className="atm-studio-wrapper">
      <div className="atm-sidebar">
        <div className="atm-sidebar-header">
          <h2>AI Automation</h2>
          <p>Content Creation Suite</p>
        </div>

        {navigationItems.map((section, sectionIndex) => (
          <div key={sectionIndex} className="atm-nav-section">
            <div className="atm-nav-section-title">{section.section}</div>
            {section.items.map((item) => (
              <button
                key={item.id}
                className={`atm-nav-item ${activeView === item.id ? "active" : ""}`}
                onClick={() => {
                  setActiveView(item.id);
                  setEditingCampaign(null);
                }}
              >
                <span
                  className="atm-nav-icon"
                  style={{
                    color:
                      activeView === item.id
                        ? item.color
                        : "rgba(255, 255, 255, 0.6)",
                    transition: "color 0.2s ease",
                  }}
                >
                  {item.icon}
                </span>
                {item.label}
              </button>
            ))}
          </div>
        ))}
      </div>

      <div className="atm-main-content">
        {activeView !== "hub" && (
          <div className="atm-content-header">
            <div className="atm-content-header-inner">
              <button
                className="atm-header-back-btn"
                onClick={() => {
                  setActiveView("hub");
                  setEditingCampaign(null);
                }}
                aria-label="Go back to hub"
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M15 18L9 12L15 6"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </button>
              <div className="atm-content-header-text">
                <h1 className="atm-content-title">{getPageTitle()}</h1>
                <p className="atm-content-subtitle">{getPageSubtitle()}</p>
              </div>
            </div>
          </div>
        )}
        <div className="atm-content-body">{renderActiveView()}</div>
      </div>
    </div>
  );
}

export default AutomationApp;
