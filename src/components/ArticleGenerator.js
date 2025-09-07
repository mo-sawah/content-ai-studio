// src/components/ArticleGenerator.js
import { useState } from "@wordpress/element";
import { Button } from "@wordpress/components";
import CreativeForm from "./CreativeForm";
import MultipageArticlesForm from "./MultipageArticlesForm";

function ArticleGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("creative");

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
        <h3>Generate Articles</h3>
      </div>

      {/* Tab Navigation */}
      <div className="atm-article-tabs">
        <Button
          className={`atm-tab-button ${activeTab === "creative" ? "active" : ""}`}
          onClick={() => setActiveTab("creative")}
          variant={activeTab === "creative" ? "primary" : "secondary"}
        >
          Standard Articles
        </Button>
        <Button
          className={`atm-tab-button ${activeTab === "multipage" ? "active" : ""}`}
          onClick={() => setActiveTab("multipage")}
          variant={activeTab === "multipage" ? "primary" : "secondary"}
        >
          Multipage Articles
        </Button>
      </div>

      {/* Tab Content */}
      <div className="atm-tab-content">
        {activeTab === "creative" && <CreativeForm />}
        {activeTab === "multipage" && <MultipageArticlesForm />}
      </div>
    </div>
  );
}

export default ArticleGenerator;
