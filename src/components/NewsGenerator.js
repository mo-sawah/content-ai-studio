// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import { Button } from "@wordpress/components";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("rss");

  return (
    <div className="atm-generator-view">
      {/* Header with back button */}
      <div className="atm-view-header">
        <Button
          className="atm-back-btn"
          onClick={() => setActiveView("hub")}
          icon={
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
          }
        />
        <h3>Generate News</h3>
      </div>

      {/* Tab Navigation */}
      <div className="atm-news-tabs">
        <Button
          className={`atm-tab-button ${activeTab === "rss" ? "active" : ""}`}
          onClick={() => setActiveTab("rss")}
          variant={activeTab === "rss" ? "primary" : "secondary"}
        >
          RSS Feeds
        </Button>
        <Button
          className={`atm-tab-button ${activeTab === "news" ? "active" : ""}`}
          onClick={() => setActiveTab("news")}
          variant={activeTab === "news" ? "primary" : "secondary"}
        >
          Live News
        </Button>
      </div>

      {/* Tab Content */}
      <div className="atm-tab-content">
        {activeTab === "rss" && <RssForm />}
        {activeTab === "news" && <NewsForm />}
      </div>
    </div>
  );
}

export default NewsGenerator;
