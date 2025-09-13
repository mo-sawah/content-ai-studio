// src/components/automation/AutoNewsGenerator.js
import { useState } from "@wordpress/element";
import { Button, TextControl } from "@wordpress/components";

function AutoNewsGenerator({ setActiveView, editingCampaign }) {
  const [campaignName, setCampaignName] = useState("");
  const [keyword, setKeyword] = useState("");

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <h4>News Automation Campaign</h4>
        <p className="components-base-control__help">
          Configure automated news article generation from Google News and RSS
          feeds.
        </p>

        <div className="atm-grid-2">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Daily Tech News"
            value={campaignName}
            onChange={setCampaignName}
            help="A descriptive name for this news automation campaign"
          />

          <TextControl
            label="News Keyword"
            placeholder="e.g., technology, politics, business"
            value={keyword}
            onChange={setKeyword}
            help="Keyword to search for in news sources"
          />
        </div>

        <div className="atm-under-construction">
          <div className="atm-construction-icon">
            <svg
              width="48"
              height="48"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1}
                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"
              />
            </svg>
          </div>
          <h3>News Automation Coming Soon</h3>
          <p>This automation type will include:</p>
          <ul>
            <li>Google News integration with language and country filters</li>
            <li>RSS feed monitoring and article generation</li>
            <li>Automated source verification and duplicate detection</li>
            <li>Custom news category targeting</li>
          </ul>
        </div>

        <div className="atm-form-actions">
          <Button isTertiary onClick={() => setActiveView("hub")}>
            Back to Hub
          </Button>
        </div>
      </div>
    </div>
  );
}

export default AutoNewsGenerator;
