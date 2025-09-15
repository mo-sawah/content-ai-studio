import { useState } from "@wordpress/element";
import { ToggleControl, TextControl } from "@wordpress/components";
import CustomDropdown from "../common/CustomDropdown";

const AutoNewsForm = ({ campaignData, setCampaignData, isLoading }) => {
  return (
    <div className="atm-form-section">
      <h4>Google News Configuration</h4>
      <p className="components-base-control__help">
        Create articles from news API sources like NewsAPI, MediaStack, GNews,
        etc.
      </p>

      <div className="atm-grid-2">
        <TextControl
          label="Campaign Name"
          placeholder="e.g., API News Articles"
          value={campaignData.name}
          onChange={(value) =>
            setCampaignData({ ...campaignData, name: value })
          }
          disabled={isLoading}
        />

        <TextControl
          label="News Topic"
          placeholder="e.g., technology, business, health"
          value={campaignData.keyword}
          onChange={(value) =>
            setCampaignData({ ...campaignData, keyword: value })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-3">
        {" "}
        {/* Changed from atm-grid-2 to atm-grid-3 */}
        <CustomDropdown
          label="News Source"
          text={campaignData.settings?.news_source || "MediaStack"}
          options={[
            { label: "MediaStack", value: "mediastack" },
            { label: "NewsAPI", value: "newsapi" },
            { label: "GNews", value: "gnews" },
            { label: "NewsData", value: "newsdata" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, news_source: option.value },
            })
          }
          disabled={isLoading}
        />
        <CustomDropdown
          label="Word Count"
          text={campaignData.settings?.word_count || "800-1000 words"}
          options={[
            { label: "400-600 words", value: "400-600" },
            { label: "600-800 words", value: "600-800" },
            { label: "800-1000 words", value: "800-1000" },
            { label: "1000-1200 words", value: "1000-1200" },
            { label: "1200-1500 words", value: "1200-1500" },
            { label: "1500-2000 words", value: "1500-2000" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, word_count: option.value },
            })
          }
          disabled={isLoading}
        />
        <CustomDropdown
          label="Country"
          text={campaignData.settings?.country || "United Kingdom"}
          options={[
            { label: "United Kingdom", value: "gb" },
            { label: "United States", value: "us" },
            { label: "Canada", value: "ca" },
            { label: "Australia", value: "au" },
            { label: "Germany", value: "de" },
            { label: "France", value: "fr" },
            { label: "Italy", value: "it" },
            { label: "Spain", value: "es" },
            { label: "Netherlands", value: "nl" },
            { label: "Japan", value: "jp" },
            { label: "South Korea", value: "kr" },
            { label: "India", value: "in" },
            { label: "Brazil", value: "br" },
            { label: "Mexico", value: "mx" },
            { label: "Argentina", value: "ar" },
          ]}
          onChange={(option) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, country: option.value },
            })
          }
          disabled={isLoading}
        />
      </div>

      <div className="atm-grid-2">
        <ToggleControl
          label="Enable web search"
          checked={campaignData.settings?.enable_web_search !== false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, enable_web_search: value },
            })
          }
          disabled={isLoading}
          help="Use web search to verify information and add context"
        />

        <ToggleControl
          label="Force fresh news"
          checked={campaignData.settings?.force_fresh || false}
          onChange={(value) =>
            setCampaignData({
              ...campaignData,
              settings: { ...campaignData.settings, force_fresh: value },
            })
          }
          disabled={isLoading}
          help="Always fetch latest news instead of using cached results"
        />
      </div>
    </div>
  );
};

export default AutoNewsForm;
