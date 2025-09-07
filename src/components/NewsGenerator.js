// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("rss");

  const newsTypes = [
    {
      id: "rss",
      title: "RSS Feeds",
      description: "Generate articles from RSS feed sources",
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
            d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"
          />
        </svg>
      ),
      gradient: "from-orange-500 to-red-600",
    },
    {
      id: "news",
      title: "Live News",
      description: "Create articles from live news sources",
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
      gradient: "from-emerald-500 to-green-600",
    },
  ];

  return (
    <div className="atm-generator-view">
      {/* News Type Selector Cards */}
      <div className="atm-type-selector">
        <div className="atm-type-cards">
          {newsTypes.map((type) => (
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
        {activeTab === "rss" && <RssForm />}
        {activeTab === "news" && <NewsForm />}
      </div>
    </div>
  );
}

export default NewsGenerator;
