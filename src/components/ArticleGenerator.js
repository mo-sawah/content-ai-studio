// src/components/ArticleGenerator.js
import { useState } from "@wordpress/element";
import CustomDropdown from "./common/CustomDropdown";
import CreativeForm from "./CreativeForm";
import MultipageArticlesForm from "./MultipageArticlesForm";

function ArticleGenerator({ setActiveView }) {
  const [activeTab, setActiveTab] = useState("creative");

  const articleTypeOptions = [
    { value: "creative", label: "Standard Articles" },
    { value: "multipage", label: "Multipage Articles" },
    // Ready for future expansion:
    // { value: "listicle", label: "Listicle Articles" },
    // { value: "howto", label: "How-To Guides" },
    // { value: "review", label: "Product Reviews" },
    // { value: "comparison", label: "Comparison Articles" },
    // { value: "tutorial", label: "Step-by-Step Tutorials" },
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
          <CustomDropdown
            options={articleTypeOptions}
            value={activeTab}
            onChange={handleTypeChange}
            placeholder="Select article type..."
          />
        </div>
      </div>

      {/* Content based on selected type */}
      <div className="atm-tab-content">
        {activeTab === "creative" && <CreativeForm />}
        {activeTab === "multipage" && <MultipageArticlesForm />}
        {/* Future article types can be added here:
        {activeTab === "listicle" && <ListicleForm />}
        {activeTab === "howto" && <HowToForm />}
        {activeTab === "review" && <ReviewForm />}
        */}
      </div>
    </div>
  );
}

export default ArticleGenerator;
