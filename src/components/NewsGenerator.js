// src/components/NewsGenerator.js
import { useState } from "@wordpress/element";
import { SelectControl } from "@wordpress/components";
import RssForm from "./RssForm";
import NewsForm from "./NewsForm";

function NewsGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("rss");

  console.log("NewsGenerator - Current activeTab:", activeTab);

  const newsTypeOptions = [
    { value: "rss", label: "RSS Feeds" },
    { value: "news", label: "Live News" },
  ];

  const handleTypeChange = (newType) => {
    console.log("NewsGenerator - Changing to:", newType);
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

      {/* Debug Info */}
      <div
        style={{
          padding: "10px",
          background: "#f0f0f0",
          margin: "10px 0",
          fontSize: "12px",
        }}
      >
        Debug: Current tab = {activeTab}
      </div>

      {/* Content based on selected type */}
      <div className="atm-tab-content">
        {activeTab === "rss" && (
          <div>
            <h4>RSS Feeds Selected</h4>
            <RssForm />
          </div>
        )}
        {activeTab === "news" && (
          <div>
            <h4>Live News Selected</h4>
            <NewsForm />
          </div>
        )}
        {!activeTab && <div>No tab selected</div>}
      </div>
    </div>
  );
}

export default NewsGenerator;
