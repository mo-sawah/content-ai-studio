// src/components/ArticleGenerator.js
import { useState } from "@wordpress/element";
import CreativeForm from "./CreativeForm";
import MultipageArticlesForm from "./MultipageArticlesForm";

function ArticleGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("creative");

  const articleTypes = [
    {
      id: "creative",
      title: "Standard Articles",
      description: "Create high-quality SEO content with AI",
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
      gradient: "from-blue-500 to-purple-600",
    },
    {
      id: "multipage",
      title: "Multipage Articles",
      description: "Create comprehensive, multi-part guides",
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
            strokeWidth="2"
            d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2h10z"
          />
        </svg>
      ),
      gradient: "from-yellow-500 to-amber-600",
    },
  ];

  return (
    <div className="atm-generator-view">
      {/* Article Type Selector Cards */}
      <div className="atm-type-selector">
        <div className="atm-type-cards">
          {articleTypes.map((type) => (
            <div
              key={type.id}
              className={`atm-type-card ${activeTab === type.id ? "active" : ""}`}
              onClick={() => setActiveTab(type.id)}
            >
              <div
                className={`atm-type-icon bg-gradient-to-r ${type.gradient}`}
              >
                {type.icon}
              </div>
              <div className="atm-type-content">
                <h3>{type.title}</h3>
                <p>{type.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Content based on selected type */}
      <div className="atm-tab-content">
        {activeTab === "creative" && <CreativeForm />}
        {activeTab === "multipage" && <MultipageArticlesForm />}
      </div>
    </div>
  );
}

export default ArticleGenerator;
