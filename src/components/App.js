import { useState, useEffect } from "@wordpress/element";
import ArticleGenerator from "./ArticleGenerator";
import ImageGenerator from "./ImageGenerator";
import PodcastGenerator from "./PodcastGenerator";
import SpeechToText from "./SpeechToText";
import Translator from "./Translator";
import VideoSearch from "./VideoSearch";
import ChartGenerator from "./ChartGenerator";
import KeyTakeawaysGenerator from "./KeyTakeawaysGenerator";

function App() {
  const [activeView, setActiveView] = useState(
    sessionStorage.getItem("atm-active-view") || "articles"
  );

  useEffect(() => {
    sessionStorage.setItem("atm-active-view", activeView);
  }, [activeView]);

  const navigationItems = [
    {
      section: "Content Generation",
      items: [
        {
          id: "articles",
          label: "Generate Articles",
          icon: (
            <svg
              width="20"
              height="20"
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
          description: "Create high-quality content with AI assistance",
        },
        {
          id: "images",
          label: "Generate Images",
          icon: (
            <svg
              width="20"
              height="20"
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
          description: "Generate stunning featured and inline images",
        },
        {
          id: "podcast",
          label: "Generate Podcast",
          icon: (
            <svg
              width="20"
              height="20"
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
          description: "Convert your articles into engaging audio content",
        },
        {
          id: "takeaways",
          label: "Key Takeaways",
          icon: (
            <svg
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
              />
            </svg>
          ),
          description: "Extract key points from your content",
        },
      ],
    },
    {
      section: "Tools & Utilities",
      items: [
        {
          id: "speech_to_text",
          label: "Speech to Text",
          icon: (
            <svg
              width="20"
              height="20"
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
          description: "Convert speech to text with AI transcription",
        },
        {
          id: "translate",
          label: "Translate Text",
          icon: (
            <svg
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
              />
            </svg>
          ),
          description: "Translate content to multiple languages",
        },
        {
          id: "video",
          label: "Video Search",
          icon: (
            <svg
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
              />
            </svg>
          ),
          description: "Find and embed YouTube videos",
        },
        {
          id: "graphs",
          label: "Generate Charts",
          icon: (
            <svg
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          ),
          description: "Create dynamic charts and visualizations",
        },
      ],
    },
  ];

  const getCurrentNavItem = () => {
    for (const section of navigationItems) {
      const item = section.items.find((item) => item.id === activeView);
      if (item) return item;
    }
    return { label: "AI Studio", description: "Content creation tools" };
  };

  const renderContent = () => {
    switch (activeView) {
      case "articles":
        return <ArticleGenerator setActiveView={setActiveView} />;
      case "images":
        return <ImageGenerator setActiveView={setActiveView} />;
      case "podcast":
        return <PodcastGenerator setActiveView={setActiveView} />;
      case "speech_to_text":
        return <SpeechToText setActiveView={setActiveView} />;
      case "translate":
        return <Translator setActiveView={setActiveView} />;
      case "video":
        return <VideoSearch setActiveView={setActiveView} />;
      case "graphs":
        return <ChartGenerator setActiveView={setActiveView} />;
      case "takeaways":
        return <KeyTakeawaysGenerator setActiveView={setActiveView} />;
      default:
        return <ArticleGenerator setActiveView={setActiveView} />;
    }
  };

  const currentItem = getCurrentNavItem();

  return (
    <div className="atm-studio-wrapper">
      {/* Sidebar Navigation */}
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
                <span className="atm-nav-icon">{item.icon}</span>
                {item.label}
              </button>
            ))}
          </div>
        ))}
      </div>

      {/* Main Content Area */}
      <div className="atm-main-content">
        <div className="atm-content-header">
          <h1 className="atm-content-title">{currentItem.label}</h1>
          <p className="atm-content-subtitle">{currentItem.description}</p>
        </div>

        <div className="atm-content-body">{renderContent()}</div>
      </div>
    </div>
  );
}

export default App;
