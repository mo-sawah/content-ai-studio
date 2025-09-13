// src/components/automation/AutomationHub.js
import { Button } from "@wordpress/components";

function AutomationHub({ setActiveView }) {
  const automationTypes = [
    {
      id: "articles",
      title: "General Articles",
      description:
        "Create automated article generation campaigns with custom prompts and settings",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="24"
          height="24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
      onClick: () => setActiveView("articles"),
    },
    {
      id: "news",
      title: "Automated News",
      description:
        "Generate articles automatically from latest news sources and RSS feeds",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="24"
          height="24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h14l2 2v12a2 2 0 01-2 2zM3 4h16M7 8h10M7 12h6"
          />
        </svg>
      ),
      gradient: "from-emerald-500 to-green-600",
      onClick: () => setActiveView("news"),
    },
    {
      id: "videos",
      title: "Auto Videos",
      description:
        "Find and embed YouTube videos automatically with generated descriptions",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="24"
          height="24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      ),
      gradient: "from-red-500 to-pink-600",
      onClick: () => setActiveView("videos"),
    },
    {
      id: "podcasts",
      title: "Auto Podcast",
      description: "Create articles with automated podcast audio generation",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          width="24"
          height="24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />
        </svg>
      ),
      gradient: "from-orange-500 to-red-600",
      onClick: () => setActiveView("podcasts"),
    },
  ];

  return (
    <div className="atm-hub-welcome">
      <div className="atm-welcome-header">
        <div className="atm-welcome-icon">
          <svg
            width="48"
            height="48"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <defs>
              <linearGradient
                id="atm-automation-grad"
                x1="0"
                y1="0"
                x2="24"
                y2="24"
                gradientUnits="userSpaceOnUse"
              >
                <stop stopColor="#8E2DE2" />
                <stop offset="1" stopColor="#4A00E0" />
              </linearGradient>
            </defs>
            {/* Automation/Cog Icon */}
            <path
              d="M12 15a3 3 0 100-6 3 3 0 000 6z"
              fill="url(#atm-automation-grad)"
            />
            <path
              d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"
              fill="url(#atm-automation-grad)"
              opacity="0.3"
            />
          </svg>
        </div>
        <h2>Welcome to AI Automation</h2>
        <p>
          Set up automated content generation campaigns to keep your site
          updated with fresh content. Choose from our automation types below to
          create scheduled campaigns that run automatically.
        </p>
      </div>

      <div className="atm-quick-actions">
        {automationTypes.map((type) => (
          <div
            key={type.id}
            className="atm-quick-action-card"
            onClick={type.onClick}
          >
            <div
              className={`atm-action-icon bg-gradient-to-r ${type.gradient}`}
            >
              {type.icon}
            </div>
            <h3>{type.title}</h3>
            <p>{type.description}</p>
          </div>
        ))}
      </div>

      <div className="atm-welcome-stats">
        <div className="atm-stat-item">
          <div className="atm-stat-number">4</div>
          <div className="atm-stat-label">Automation Types</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">10min</div>
          <div className="atm-stat-label">Minimum Frequency</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">24/7</div>
          <div className="atm-stat-label">Automated</div>
        </div>
      </div>

      <div className="atm-welcome-footer">
        <p>
          New to automation? Start with{" "}
          <Button variant="link" onClick={() => setActiveView("articles")}>
            General Articles
          </Button>{" "}
          to create your first automated content campaign.
        </p>
        <p style={{ marginTop: "8px", fontSize: "14px", color: "#64748b" }}>
          Want to manage existing campaigns?{" "}
          <Button variant="link" onClick={() => setActiveView("campaigns")}>
            Go to Campaign Manager
          </Button>
        </p>
      </div>
    </div>
  );
}

export default AutomationHub;
