// src/components/ArticleGenerator.js
import { useState } from "@wordpress/element";
import { SelectControl } from "@wordpress/components";
import CreativeForm from "./CreativeForm";
import MultipageArticlesForm from "./MultipageArticlesForm";

function ArticleGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("creative");

  const articleTypeOptions = [
    { value: "creative", label: "Standard Articles" },
    { value: "multipage", label: "Multipage Articles" },
  ];

  const handleTypeChange = (newType) => {
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

      {/* Content based on selected type */}
      <div className="atm-tab-content">
        {activeTab === "creative" && <CreativeForm />}
        {activeTab === "multipage" && <MultipageArticlesForm />}
      </div>
    </div>
  );
}

export default ArticleGenerator;
