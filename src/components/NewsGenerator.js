// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import CustomDropdown from "./common/CustomDropdown";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("rss");

  const newsTypeOptions = [
    { value: "rss", label: "RSS Feeds" },
    { value: "news", label: "Live News" },
    // Ready for future expansion:
    // { value: "social", label: "Social Media Trends" },
    // { value: "breaking", label: "Breaking News Alerts" },
    // { value: "financial", label: "Financial News" },
    // { value: "sports", label: "Sports News" },
    // { value: "tech", label: "Technology News" },
    // { value: "business", label: "Business News" },
  ];

  const handleTypeChange = (newType) => {
    setActiveTab(newType);
  };

  return (
    <div className="atm-generator-view">
      {/* News Type Selector */}
      <div className="atm-form-container">
        <div className="atm-dropdown-field">
          <label className="atm-dropdown-label">News Source Type</label>
          <CustomDropdown
            options={newsTypeOptions}
            value={activeTab}
            onChange={handleTypeChange}
            placeholder="Select news source..."
          />
        </div>
      </div>

      {/* Content based on selected type */}
      <div className="atm-tab-content">
        {activeTab === "rss" && <RssForm />}
        {activeTab === "news" && <NewsForm />}
        {/* Future news types can be added here:
        {activeTab === "social" && <SocialTrendsForm />}
        {activeTab === "breaking" && <BreakingNewsForm />}
        {activeTab === "financial" && <FinancialNewsForm />}
        */}
      </div>
    </div>
  );
}

export default NewsGenerator;
