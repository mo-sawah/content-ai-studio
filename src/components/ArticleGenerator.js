// src/components/ArticleGenerator.js
import { useState } from "@wordpress/element";
import { SelectControl } from "@wordpress/components";
import CreativeForm from "./CreativeForm";
import MultipageArticlesForm from "./MultipageArticlesForm";

function ArticleGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("creative");

  console.log("ArticleGenerator - Current activeTab:", activeTab);

  const articleTypeOptions = [
    { value: "creative", label: "Standard Articles" },
    { value: "multipage", label: "Multipage Articles" },
  ];

  const handleTypeChange = (newType) => {
    console.log("ArticleGenerator - Changing to:", newType);
    setActiveTab(newType);
  };

  return (
    <div className="atm-generator-view">
      {/* Article Type Selector */}
      <div className="atm-form-container">
        <div className="atm-dropdown-field">
          <label className="atm-dropdown-label">Article Type</label>
          <SelectControl
            value={activeTab}
            options={articleTypeOptions}
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
        {activeTab === "creative" && (
          <div>
            <h4>Standard Articles Selected</h4>
            <CreativeForm />
          </div>
        )}
        {activeTab === "multipage" && (
          <div>
            <h4>Multipage Articles Selected</h4>
            <MultipageArticlesForm />
          </div>
        )}
        {!activeTab && <div>No tab selected</div>}
      </div>
    </div>
  );
}

export default ArticleGenerator;
