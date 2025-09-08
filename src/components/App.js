// src/components/App.js
import { useState } from "@wordpress/element";
import WelcomeHub from "./WelcomeHub";
import ArticleGenerator from "./ArticleGenerator";
import ImageGenerator from "./ImageGenerator";
import PodcastGenerator from "./PodcastGenerator";
import KeyTakeawaysGenerator from "./KeyTakeawaysGenerator";
import SpeechToText from "./SpeechToText";
import Translator from "./Translator";
import VideoSearch from "./VideoSearch";
import ChartGenerator from "./ChartGenerator";
import MultipageArticlesForm from "./MultipageArticlesForm";
import CommentsGenerator from "./CommentsGenerator";
import NewsGenerator from "./NewsGenerator";
import LiveNewsForm from "./LiveNewsForm";

function App() {
  const [activeView, setActiveView] = useState("hub");

  const navigationItems = [
    {
      section: "CONTENT GENERATION",
      items: [
        {
          id: "articles",
          label: "Generate Articles",
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
        // REMOVED: multipage from sidebar
        {
          id: "news",
          label: "Generate News",
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
          id: "images",
          label: "Generate Images",
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
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          ),
          color: "#10b981",
        },
        {
          id: "podcast",
          label: "Generate Podcast",
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
        {
          id: "takeaways",
          label: "Key Takeaways",
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
          color: "#ec4899",
        },
      ],
    },
    {
      section: "TOOLS & UTILITIES",
      items: [
        {
          id: "comments",
          label: "Generate Comments",
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
                d="M7 8h10M7 12h6m6-2a8 8 0 11-16 0 8 8 0 0116 0zM7 16l-2 2 3-.5"
              />
            </svg>
          ),
          color: "#0ea5e9",
        },
        {
          id: "speech",
          label: "Speech to Text",
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
          color: "#6366f1",
        },
        {
          id: "translate",
          label: "Translate Text",
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
                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
              />
            </svg>
          ),
          color: "#06b6d4",
        },
        {
          id: "video",
          label: "Video Search",
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
          color: "#ef4444",
        },
        {
          id: "charts",
          label: "Generate Charts",
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
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          ),
          color: "#10b981",
        },
      ],
    },
  ];

  const renderActiveView = () => {
    switch (activeView) {
      case "hub":
        return <WelcomeHub setActiveView={setActiveView} />;
      case "articles":
        return <ArticleGenerator setActiveView={setActiveView} />;
      case "multipage":
        return <MultipageArticlesForm setActiveView={setActiveView} />;
      case "news":
        return <NewsGenerator setActiveView={setActiveView} />;
      case "images":
        return <ImageGenerator setActiveView={setActiveView} />;
      case "podcast":
        return <PodcastGenerator setActiveView={setActiveView} />;
      case "takeaways":
        return <KeyTakeawaysGenerator setActiveView={setActiveView} />;
      case "comments":
        return <CommentsGenerator setActiveView={setActiveView} />;
      case "speech":
        return <SpeechToText setActiveView={setActiveView} />;
      case "translate":
        return <Translator setActiveView={setActiveView} />;
      case "video":
        return <VideoSearch setActiveView={setActiveView} />;
      case "charts":
        return <ChartGenerator setActiveView={setActiveView} />;
      default:
        return <WelcomeHub setActiveView={setActiveView} />;
    }
  };

  const getPageTitle = () => {
    const titles = {
      hub: "AI Studio",
      articles: "Generate Articles",
      multipage: "Generate Multipage Article",
      news: "Generate News",
      images: "Generate Images",
      podcast: "Generate Podcast",
      takeaways: "Key Takeaways",
      comments: "Generate Comments",
      speech: "Speech to Text",
      translate: "Translate Text",
      video: "YouTube Video Search",
      charts: "Generate Charts",
    };
    return titles[activeView] || "AI Studio";
  };

  const getPageSubtitle = () => {
    const subtitles = {
      hub: "Content Creation Suite",
      articles: "Create high-quality content with AI assistance",
      multipage: "Create comprehensive, multi-part guides with AI",
      news: "Create News from RSS feeds and live news sources",
      images: "Generate stunning visuals powered by AI",
      podcast: "Turn content into engaging audio experiences",
      takeaways: "Extract key insights from your content",
      comments: "Create realistic discussion threads based on your post",
      speech: "Convert audio into accurate text",
      translate: "Translate content across languages",
      video: "Find and embed YouTube videos",
      charts: "Create beautiful data visualizations",
    };
    return subtitles[activeView] || "Content Creation Suite";
  };

  return (
    <div className="atm-studio-wrapper">
      <div className="atm-sidebar">
        <div className="atm-sidebar-header">
          <h2>AI Studio</h2>
          <p>Content Creation Suite</p>
        </div>

        {navigationItems.map((section, sectionIndex) => (
          <div key={sectionIndex} className="atm-nav-section">
            <div className="atm-nav-section-title">{section.section}</div>
            {section.items.map((item) => (
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
                onClick={() => setActiveView("hub")}
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

export default App;
