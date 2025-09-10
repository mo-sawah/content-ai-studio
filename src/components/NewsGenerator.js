// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";
import LiveNewsForm from "./LiveNewsForm";
import NewsSearchForm from "./NewsSearchForm";
import TwitterNewsForm from "./TwitterNewsForm"; // NEW

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("search");

  const newsTypes = [
    {
      id: "search",
      title: "News Search",
      description: "Search Google News and generate articles from sources",
      icon: (
        <svg
          fill="currentColor"
          viewBox="0 0 24 24"
          width="20"
          height="20"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.75 8.36,4.73 12.19,4.73C14.76,4.73 16.04,5.72 17.04,6.58L19.34,4.32C17.23,2.5 14.86,1.5 12.2,1.5C6.42,1.5 2.03,5.82 2.03,12C2.03,18.18 6.42,22.5 12.2,22.5C17.6,22.5 21.95,18.63 21.95,12.31C21.95,11.76 21.64,11.1 21.35,11.1Z" />
        </svg>
      ),
      gradient: "from-indigo-500 to-blue-600",
    },
    {
      id: "twitter",
      title: "Twitter/X News",
      description: "Generate articles from Twitter news sources",
      icon: (
        <svg
          fill="currentColor"
          viewBox="0 0 24 24"
          width="20"
          height="20"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
        </svg>
      ),
      gradient: "from-slate-800 to-black",
    },
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
        {activeTab === "search" && <NewsSearchForm />}
        {activeTab === "twitter" && <TwitterNewsForm />}
        {activeTab === "rss" && <RssForm />}
        {activeTab === "apis" && <NewsForm />}
        {activeTab === "live" && <LiveNewsForm />}
      </div>
    </div>
  );
}

export default NewsGenerator;
