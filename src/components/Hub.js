import { Button } from "@wordpress/components";

function Hub({ setActiveView }) {
  const quickActions = [
    {
      id: "articles",
      title: "Generate Articles",
      description: "Create SEO-optimized content with AI",
      icon: (
        <svg
          width="24"
          height="24"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
    },
    {
      id: "images",
      title: "Generate Images",
      description: "AI-powered featured and inline images",
      icon: (
        <svg
          width="24"
          height="24"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      ),
      gradient: "from-green-500 to-teal-600",
    },
    {
      id: "podcast",
      title: "Generate Podcast",
      description: "Convert articles to audio content",
      icon: (
        <svg
          width="24"
          height="24"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
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
    },
    {
      id: "speech_to_text",
      title: "Speech to Text",
      description: "Transcribe audio with AI precision",
      icon: (
        <svg
          width="24"
          height="24"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
          />
        </svg>
      ),
      gradient: "from-pink-500 to-purple-600",
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
                id="atm-grad"
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
            <rect
              x="2"
              y="13"
              width="20"
              height="9"
              rx="2"
              fill="url(#atm-grad)"
              opacity="0.6"
            />
            <path
              d="M6 16H18 M6 18H15"
              stroke="white"
              strokeWidth="1.2"
              strokeLinecap="round"
            />
            <rect
              x="2"
              y="8"
              width="20"
              height="9"
              rx="2"
              fill="url(#atm-grad)"
              opacity="0.8"
            />
            <path
              d="M6 12.5H8L10 11L12 14L14 11L16 12.5H18"
              stroke="white"
              strokeWidth="1.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
            <rect
              x="2"
              y="3"
              width="20"
              height="9"
              rx="2"
              fill="url(#atm-grad)"
            />
            <circle cx="8" cy="7" r="1" fill="white" />
            <path
              d="M6 10L9 8L13 9.5L18 7"
              stroke="white"
              strokeWidth="1.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>
        <h2>Welcome to AI Studio</h2>
        <p>
          Your complete AI-powered content creation toolkit. Choose a tool from
          the sidebar or get started with these popular features:
        </p>
      </div>

      <div className="atm-quick-actions">
        {quickActions.map((action) => (
          <div
            key={action.id}
            className="atm-quick-action-card"
            onClick={() => setActiveView(action.id)}
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
          <div className="atm-stat-number">8</div>
          <div className="atm-stat-label">AI Tools Available</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">15+</div>
          <div className="atm-stat-label">AI Models Supported</div>
        </div>
        <div className="atm-stat-item">
          <div className="atm-stat-number">∞</div>
          <div className="atm-stat-label">Content Possibilities</div>
        </div>
      </div>

      <div className="atm-welcome-footer">
        <p>
          New to AI Studio? Start with{" "}
          <Button variant="link" onClick={() => setActiveView("articles")}>
            Generate Articles
          </Button>{" "}
          to create your first AI-powered content.
        </p>
      </div>
    </div>
  );
}

export default Hub;
