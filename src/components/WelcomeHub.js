// src/components/WelcomeHub.js
import { Button } from "@wordpress/components";

function WelcomeHub({ setActiveView }) {
  const quickActions = [
    {
      id: "articles",
      title: "Generate Articles",
      description: "Create high-quality content with AI assistance",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
      onClick: () => setActiveView("articles"),
    },
    // --- NEW: Multipage Articles card added here ---
    {
      id: "multipage",
      title: "Multipage Articles",
      description: "Create comprehensive, multi-part guides",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2h10z"
          ></path>
        </svg>
      ),
      gradient: "from-yellow-500 to-amber-600",
      onClick: () => setActiveView("multipage"),
    },
    {
      id: "images",
      title: "Generate Images",
      description: "Create stunning visuals powered by Top AI",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      ),
      gradient: "from-green-500 to-teal-600",
      onClick: () => setActiveView("images"),
    },
    {
      id: "podcast",
      title: "Generate Podcast",
      description: "Turn content into engaging audio experiences",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />
        </svg>
      ),
      gradient: "from-orange-500 to-red-600",
      onClick: () => setActiveView("podcast"),
    },
    {
      id: "takeaways",
      title: "Key Takeaways",
      description: "Extract insights from your content",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
          />
        </svg>
      ),
      gradient: "from-pink-500 to-purple-600",
      onClick: () => setActiveView("takeaways"),
    },
    // --- NEW: Generate Comments (right after Key Takeaways) ---
    {
      id: "comments",
      title: "Generate Comments",
      description: "Create realistic discussion threads based on your post",
      icon: (
        <svg
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M7 8h10M7 12h6M4 17v-8a3 3 0 013-3h10a3 3 0 013 3v5a3 3 0 01-3 3H9l-3 3v-3"
          />
        </svg>
      ),
      // Fixed to match existing gradient palette (prevents white fade)
      gradient: "from-cyan-500 to-purple-600",
      onClick: () => setActiveView("comments"),
    },
    {
      id: "speech",
      title: "Speech to Text",
      description: "Convert audio into accurate text in the editor",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />
        </svg>
      ),
      gradient: "from-indigo-500 to-blue-600",
      onClick: () => setActiveView("speech"),
    },
    {
      id: "translate",
      title: "Translate Text",
      description: "Translate content across all popular languages",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
          />
        </svg>
      ),
      gradient: "from-cyan-500 to-teal-600",
      onClick: () => setActiveView("translate"),
    },
    {
      id: "video",
      title: "Video Search",
      description: "Find and embed YouTube videos",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      ),
      gradient: "from-red-500 to-pink-600",
      onClick: () => setActiveView("video"),
    },
    {
      id: "charts",
      title: "Generate Charts",
      description: "Create beautiful data visualizations",
      icon: (
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
          />
        </svg>
      ),
      gradient: "from-emerald-500 to-green-600",
      onClick: () => setActiveView("charts"),
    },
  ];

  return (
    <div className="atm-hub-welcome">
      <div className="atm-welcome-header">
        <h2>Welcome to AI Studio</h2>
        <p>
          Your all-in-one content creation suite powered by artificial
          intelligence. Choose from our powerful tools below to get started
          creating amazing content.
        </p>
      </div>

      <div className="atm-quick-actions">
        {quickActions.map((action) => (
          <div
            key={action.id}
            className="atm-quick-action-card"
            onClick={action.onClick}
          >
            <div
              className={`atm-action-icon bg-gradient-to-r ${action.gradient}`}
            >
              {action.icon}
            </div>
            <h3>{action.title}</h3>
            <p>{action.description}</p>
          </div>
        ))}
      </div>

      <div className="atm-welcome-stats">
        <div className="atm-stat-item">
          <div className="atm-stat-number">9</div>
          <div className="atm-stat-label">AI Tools</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">∞</div>
          <div className="atm-stat-label">Possibilities</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">100%</div>
          <div className="atm-stat-label">AI Powered</div>
        </div>
      </div>

      <div className="atm-welcome-footer">
        <p>
          Need help getting started? Check out our{" "}
          <Button
            isLink
            onClick={() => window.open("https://docs.example.com", "_blank")}
          >
            documentation
          </Button>{" "}
          or{" "}
          <Button
            isLink
            onClick={() => window.open("https://support.example.com", "_blank")}
          >
            contact support
          </Button>
          .
        </p>
      </div>
    </div>
  );
}

export default WelcomeHub;
