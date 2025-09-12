// src/components/automation/AutomationApp.js
import { useState } from "@wordpress/element";
import AutoArticleForm from "./AutoArticleForm";
// Placeholders for components we will create later
// import AutoNewsForm from "./AutoNewsForm";
// import AutoVideoForm from "./AutoVideoForm";
// import AutoPodcastForm from "./AutoPodcastForm";

function AutomationApp() {
  const [activeView, setActiveView] = useState("articles");

  const navigationItems = [
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
          {" "}
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
          />{" "}
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
          {" "}
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h14l2 2v12a2 2 0 01-2 2zM3 4h16M7 8h10M7 12h6"
          />{" "}
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
          {" "}
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />{" "}
        </svg>
      ),
      color: "#ef4444",
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
          {" "}
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />{" "}
        </svg>
      ),
      color: "#f97316",
    },
  ];

  const renderActiveView = () => {
    switch (activeView) {
      case "articles":
        return <AutoArticleForm />;
      // Add cases for news, videos, podcasts here later
      default:
        return <AutoArticleForm />;
    }
  };

  const getPageTitle = () => {
    return (
      navigationItems.find((item) => item.id === activeView)?.label ||
      "AI Automation"
    );
  };

  return (
    <div className="atm-studio-wrapper">
      <div className="atm-sidebar">
        <div className="atm-sidebar-header">
          <h2>AI Automation</h2>
          <p>Campaign Manager</p>
        </div>
        <div className="atm-nav-section">
          <div className="atm-nav-section-title">CAMPAIGN TYPES</div>
          {navigationItems.map((item) => (
            <button
              key={item.id}
              className={`atm-nav-item ${activeView === item.id ? "active" : ""}`}
              onClick={() => setActiveView(item.id)}
            >
              <span
                className="atm-nav-icon"
                style={{
                  color:
                    activeView === item.id
                      ? item.color
                      : "rgba(255, 255, 255, 0.6)",
                }}
              >
                {item.icon}
              </span>
              {item.label}
            </button>
          ))}
        </div>
      </div>
      <div className="atm-main-content">
        <div className="atm-content-header">
          <h1 className="atm-content-title">{getPageTitle()}</h1>
          <p className="atm-content-subtitle">
            Configure and schedule your automated content campaign.
          </p>
        </div>
        <div className="atm-content-body">{renderActiveView()}</div>
      </div>
    </div>
  );
}

export default AutomationApp;
