// src/components/automation/AutoPodcastGenerator.js
import { useState } from "@wordpress/element";
import { Button, TextControl } from "@wordpress/components";

function AutoPodcastGenerator({ setActiveView, editingCampaign }) {
  const [campaignName, setCampaignName] = useState("");
  const [keyword, setKeyword] = useState("");

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <h4>Podcast Automation Campaign</h4>
        <p className="components-base-control__help">
          Configure automated article generation with podcast audio creation.
        </p>

        <div className="atm-grid-2">
          <TextControl
            label="Campaign Name"
            placeholder="e.g., Daily Business Podcast"
            value={campaignName}
            onChange={setCampaignName}
            help="A descriptive name for this podcast automation campaign"
          />

          <TextControl
            label="Podcast Topic"
            placeholder="e.g., entrepreneurship, health, technology"
            value={keyword}
            onChange={setKeyword}
            help="Main topic for article and podcast generation"
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
                d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
              />
            </svg>
          </div>
          <h3>Podcast Automation Coming Soon</h3>
          <p>This automation type will include:</p>
          <ul>
            <li>Automated article generation with podcast-optimized content</li>
            <li>Two-host conversational script generation</li>
            <li>Voice selection and audio generation</li>
            <li>Automatic podcast player embedding</li>
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

export default AutoPodcastGenerator;
