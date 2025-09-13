// src/components/automation/AutoVideoGenerator.js
import { useState } from "@wordpress/element";
import { Button, TextControl } from "@wordpress/components";

function AutoVideoGenerator({ setActiveView, editingCampaign }) {
  const [campaignName, setCampaignName] = useState("");
  const [keyword, setKeyword] = useState("");

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <h4>Video Automation Campaign</h4>
        <p className="components-base-control__help">
          Configure automated video embedding from YouTube with generated
          descriptions.
        </p>

        <div className="atm-grid-2">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Weekly Tutorial Videos"
            value={campaignName}
            onChange={setCampaignName}
            help="A descriptive name for this video automation campaign"
          />

          <TextControl
            label="Video Search Keyword"
            placeholder="e.g., programming tutorials, cooking tips"
            value={keyword}
            onChange={setKeyword}
            help="Keyword to search for relevant videos on YouTube"
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
                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
              />
            </svg>
          </div>
          <h3>Video Automation Coming Soon</h3>
          <p>This automation type will include:</p>
          <ul>
            <li>YouTube video search with advanced filtering</li>
            <li>Automatic video embedding with responsive design</li>
            <li>AI-generated descriptions and summaries</li>
            <li>Duration and quality preferences</li>
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

export default AutoVideoGenerator;
