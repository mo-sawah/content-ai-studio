// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";
import LiveNewsForm from "./LiveNewsForm";
import NewsSearchForm from "./NewsSearchForm";

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
      id: "apis",
      title: "APIs News",
      description: "Create articles from news API sources",
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
    {
      id: "live",
      title: "Live News",
      description: "Search and categorize latest news with AI",
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
            d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z"
          />
        </svg>
      ),
      gradient: "from-blue-500 to-purple-600",
    },
    {
      id: "search",
      title: "News Search",
      description: "Search Google News and generate articles from sources",
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
            d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
          />
        </svg>
      ),
      gradient: "from-indigo-500 to-blue-600",
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
        {activeTab === "apis" && <NewsForm />}
        {activeTab === "live" && <LiveNewsForm />}
        {activeTab === "search" && <NewsSearchForm />}
      </div>
    </div>
  );
}

export default NewsGenerator;
