// src/components/automation/AutoArticleForm.js
import { useState } from "@wordpress/element";
import {
  Button,
  TextControl,
  SelectControl,
  ToggleControl,
} from "@wordpress/components";
import ArticleGenerator from "../ArticleGenerator"; // Reusing the existing component!

// Mock data for dropdowns - this will be replaced with data from WordPress in Phase 3
const mockAuthors = [{ label: "Admin User", value: "1" }];
const mockCategories = [
  { label: "Uncategorized", value: "1" },
  { label: "News", value: "2" },
];

function AutoArticleForm() {
  const [campaignName, setCampaignName] = useState("");
  const [frequencyValue, setFrequencyValue] = useState(1);
  const [frequencyUnit, setFrequencyUnit] = useState("hour");
  const [maxPosts, setMaxPosts] = useState(0); // 0 for unlimited

  const [postStatus, setPostStatus] = useState("publish");
  const [authorId, setAuthorId] = useState("1");
  const [categoryId, setCategoryId] = useState("1");
  const [generateImage, setGenerateImage] = useState(true);

  // We will need a way to get the settings from the child ArticleGenerator.
  // For now, we'll build the UI. The logic will come in Phase 3.

  const handleSaveCampaign = () => {
    // In Phase 3, this function will collect all the settings from this
    // form AND from the child ArticleGenerator component and send them
    // to the backend via a new AJAX call.
    alert("Campaign saving logic will be implemented in Phase 3!");

    const campaignSettings = {
      name: campaignName,
      type: "article",
      schedule: {
        value: frequencyValue,
        unit: frequencyUnit,
        maxPosts: maxPosts,
      },
      postSettings: {
        status: postStatus,
        author: authorId,
        category: categoryId,
        image: generateImage,
      },
      // contentSettings will be gathered from the child component
    };
    console.log("Campaign Settings to Save:", campaignSettings);
  };

  return (
    <div className="atm-form-container">
      {/* Section 1: Campaign Details */}
      <div className="atm-form-section">
        <h4 className="atm-section-title">Campaign Settings</h4>
        <TextControl
          label="Campaign Name"
          value={campaignName}
          onChange={setCampaignName}
          placeholder="e.g., Daily AI Tech Articles"
          help="Give your campaign a memorable name."
          required
        />
      </div>

      {/* Section 2: Content Source Configuration */}
      <div className="atm-form-section">
        <h4 className="atm-section-title">Content Source</h4>
        <p
          className="components-base-control__help"
          style={{ marginTop: "-1rem", marginBottom: "1.5rem" }}
        >
          Configure the content settings below. The automation will use these
          settings to generate each new article.
        </p>
        {/* HERE WE REUSE THE ENTIRE EXISTING ARTICLE GENERATOR UI */}
        <ArticleGenerator setActiveView={() => {}} />
      </div>

      {/* Section 3: Scheduling */}
      <div className="atm-form-section">
        <h4 className="atm-section-title">Scheduling</h4>
        <div className="atm-grid-2">
          <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
            <TextControl
              label="Frequency"
              type="number"
              value={frequencyValue}
              onChange={(v) => setFrequencyValue(parseInt(v, 10))}
              min="1"
              step="1"
            />
            <SelectControl
              label="Unit"
              value={frequencyUnit}
              onChange={setFrequencyUnit}
              options={[
                { label: "Minutes", value: "minute" },
                { label: "Hours", value: "hour" },
                { label: "Days", value: "day" },
                { label: "Weeks", value: "week" },
              ]}
              help="Minimum 10 minutes."
            />
          </div>
          <TextControl
            label="Max Posts (Optional)"
            type="number"
            value={maxPosts}
            onChange={(v) => setMaxPosts(parseInt(v, 10))}
            min="0"
            help="Set to 0 for unlimited posts. The campaign will pause after reaching this number."
          />
        </div>
      </div>

      {/* Section 4: Post Settings */}
      <div className="atm-form-section">
        <h4 className="atm-section-title">Post Settings</h4>
        <div className="atm-grid-2">
          <SelectControl
            label="Author"
            value={authorId}
            options={mockAuthors}
            onChange={setAuthorId}
          />
          <SelectControl
            label="Category"
            value={categoryId}
            options={mockCategories}
            onChange={setCategoryId}
          />
        </div>
        <div className="atm-grid-2" style={{ marginTop: "1rem" }}>
          <SelectControl
            label="Post Status"
            value={postStatus}
            onChange={setPostStatus}
            options={[
              { label: "Publish Immediately", value: "publish" },
              { label: "Save as Draft", value: "draft" },
              { label: "Schedule for Later", value: "scheduled" },
            ]}
            help="Choose the status for newly created posts."
          />
          <ToggleControl
            label="Generate Featured Image"
            checked={generateImage}
            onChange={setGenerateImage}
            help="Automatically generate and set a featured image for each post."
          />
        </div>
      </div>

      <div className="atm-form-actions">
        <Button isPrimary onClick={handleSaveCampaign} disabled={!campaignName}>
          Save Campaign
        </Button>
      </div>
    </div>
  );
}

export default AutoArticleForm;
