// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import { SelectControl } from "@wordpress/components";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("rss");

  const newsTypeOptions = [
    { value: "rss", label: "RSS Feeds" },
    { value: "news", label: "Live News" },
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
          <SelectControl
            value={activeTab}
            options={newsTypeOptions}
            onChange={handleTypeChange}
            __nextHasNoMarginBottom
          />
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
