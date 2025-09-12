// src/components/TrendingForm.js
import { useState, useEffect } from "@wordpress/element";
import {
  Button,
  TextControl,
  CheckboxControl,
  SelectControl,
} from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const TrendingForm = () => {
  const [keyword, setKeyword] = useState("");
  const [trendingTopics, setTrendingTopics] = useState([]);
  const [selectedTopics, setSelectedTopics] = useState([]);
  const [isLoadingTrends, setIsLoadingTrends] = useState(false);
  const [isGeneratingArticle, setIsGeneratingArticle] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [region, setRegion] = useState("US");
  const [searchSource, setSearchSource] = useState("combined");

  // Article generation settings
  const [articleSettings, setArticleSettings] = useState({
    wordCount: "800-1200",
    tone: "professional",
    includeImages: true,
    autoPublish: false,
  });

  const regions = [
    { label: "United States", value: "US" },
    { label: "Global", value: "" },
    { label: "United Kingdom", value: "GB" },
    { label: "Canada", value: "CA" },
    { label: "Australia", value: "AU" },
    { label: "Germany", value: "DE" },
    { label: "France", value: "FR" },
    { label: "India", value: "IN" },
  ];

  const searchSources = [
    {
      label: "Combined Search",
      value: "combined",
      icon: "⚡",
      description: "Use all sources for maximum coverage",
    },
    {
      label: "OpenRouter Web Search",
      value: "openrouter_web",
      icon: "🌐",
      description: "AI-powered web search for trending topics",
    },
    {
      label: "Google Trends (SerpApi)",
      value: "google_trends",
      icon: "📈",
      description: "Real search trends from Google",
    },
    {
      label: "Google Custom Search",
      value: "google_custom",
      icon: "🔍",
      description: "Search recent popular articles",
    },
  ];

  const wordCountOptions = [
    { label: "Short (400-600 words)", value: "400-600" },
    { label: "Medium (800-1200 words)", value: "800-1200" },
    { label: "Long (1500-2000 words)", value: "1500-2000" },
  ];

  const toneOptions = [
    { label: "Professional", value: "professional" },
    { label: "Casual", value: "casual" },
    { label: "News / Journalistic", value: "journalistic" },
  ];

  const fetchTrendingTopics = async () => {
    setIsLoadingTrends(true);
    setStatusMessage("Searching for trending topics...");
    setTrendingTopics([]);
    setSelectedTopics([]);

    try {
      const response = await callAjax("fetch_trending_topics_multi_source", {
        keyword: keyword.trim(),
        region: region,
        search_source: searchSource,
      });

      if (!response.success) {
        throw new Error(response.data || "Failed to fetch trending topics");
      }

      setTrendingTopics(response.data.trends || []);

      if (!response.data.trends || response.data.trends.length === 0) {
        setStatusMessage(
          "No trending topics found. Try a different keyword, region, or search source."
        );
      } else {
        setStatusMessage(
          `Found ${response.data.trends.length} trending topics.`
        );
      }
    } catch (err) {
      setStatusMessage(`Error: ${err.message}`);
      console.error("Error fetching trending topics:", err);
    } finally {
      setIsLoadingTrends(false);
    }
  };

  const handleTopicSelection = (topicIndex, isSelected) => {
    if (isSelected) {
      setSelectedTopics([...selectedTopics, topicIndex]);
    } else {
      setSelectedTopics(selectedTopics.filter((index) => index !== topicIndex));
    }
  };

  const generateArticlesFromTrends = async () => {
    if (selectedTopics.length === 0) {
      setStatusMessage("Please select at least one topic.");
      return;
    }

    setIsGeneratingArticle(true);
    setStatusMessage(`Generating ${selectedTopics.length} article(s)...`);

    try {
      const selectedTrendingTopics = selectedTopics.map(
        (index) => trendingTopics[index]
      );

      const response = await callAjax("generate_trending_articles", {
        trending_topics: JSON.stringify(selectedTrendingTopics),
        settings: JSON.stringify(articleSettings),
      });

      if (!response.success) {
        throw new Error(
          response.data || "Failed to generate articles from trends"
        );
      }

      setSelectedTopics([]);
      setStatusMessage(
        `✅ Successfully generated ${response.data.successful_count} trending article(s)!`
      );
    } catch (err) {
      setStatusMessage(`Error: ${err.message}`);
      console.error("Error generating trending articles:", err);
    } finally {
      setIsGeneratingArticle(false);
    }
  };

  // Load initial trends on component mount
  useEffect(() => {
    fetchTrendingTopics();
  }, []);

  const getSourceBadge = (source) => {
    const sourceMap = {
      google_trends: { icon: "📈", color: "#4285f4", name: "Google Trends" },
      google_custom: { icon: "🔍", color: "#34a853", name: "Google Search" },
      openrouter_web: { icon: "🌐", color: "#ea4335", name: "Web Search" },
      combined: { icon: "⚡", color: "#fbbc04", name: "Combined" },
      fallback: { icon: "💡", color: "#9aa0a6", name: "Generated" },
    };
    const sourceInfo = sourceMap[source] || sourceMap.fallback;
    return (
      <span
        className="atm-topic-source-badge"
        style={{ background: sourceInfo.color }}
      >
        {sourceInfo.icon} {sourceInfo.name}
      </span>
    );
  };

  return (
    <div className="atm-form-container">
      {/* Search Source Selection */}
      <div className="atm-form-section">
        <h3 className="atm-section-title">Search Source</h3>
        <div className="atm-search-sources">
          {searchSources.map((source) => (
            <label
              key={source.value}
              className={`atm-source-option ${
                searchSource === source.value ? "active" : ""
              }`}
            >
              <input
                type="radio"
                name="search_source"
                value={source.value}
                checked={searchSource === source.value}
                onChange={(e) => setSearchSource(e.target.value)}
              />
              <div className="atm-source-content">
                <div className="atm-source-header">
                  <span className="atm-source-icon">{source.icon}</span>
                  <span className="atm-source-label">{source.label}</span>
                </div>
                <p className="atm-source-description">{source.description}</p>
              </div>
            </label>
          ))}
        </div>
      </div>

      {/* Search Parameters */}
      <div className="atm-form-section">
        <h3 className="atm-section-title">Search Parameters</h3>
        <div className="atm-form-grid">
          <TextControl
            label="Keyword (Optional)"
            value={keyword}
            onChange={setKeyword}
            placeholder="e.g., technology, elections..."
            help="Leave empty for general trends"
          />
          <SelectControl
            label="Region"
            value={region}
            options={regions}
            onChange={setRegion}
            help="For Google Trends & Search"
          />
        </div>
        <Button
          isSecondary
          onClick={fetchTrendingTopics}
          disabled={isLoadingTrends || isGeneratingArticle}
        >
          {isLoadingTrends ? "Searching..." : "Search For Trends"}
        </Button>
      </div>

      {/* Status & Loading */}
      {(isLoadingTrends || statusMessage) && (
        <div className="atm-status-container">
          {isLoadingTrends && <CustomSpinner />}
          {statusMessage && (
            <p className="atm-status-message info">{statusMessage}</p>
          )}
        </div>
      )}

      {/* Trending Topics List */}
      {!isLoadingTrends && trendingTopics.length > 0 && (
        <div className="atm-trending-topics">
          <h3 className="atm-section-title">Select Topics to Write About</h3>
          <div className="atm-topics-list">
            {trendingTopics.map((topic, index) => (
              <div
                key={index}
                className={`atm-topic-item ${
                  selectedTopics.includes(index) ? "selected" : ""
                }`}
              >
                <CheckboxControl
                  checked={selectedTopics.includes(index)}
                  onChange={(isSelected) =>
                    handleTopicSelection(index, isSelected)
                  }
                  disabled={isGeneratingArticle}
                />
                <div className="atm-topic-content">
                  <div className="atm-topic-header">
                    <strong>{topic.title}</strong>
                    {topic.source && getSourceBadge(topic.source)}
                  </div>
                  {topic.snippet && (
                    <p className="atm-topic-snippet">{topic.snippet}</p>
                  )}
                  <div className="atm-topic-meta">
                    {topic.traffic && (
                      <span className="atm-topic-traffic">
                        🔥 {topic.traffic}
                      </span>
                    )}
                    {topic.url && (
                      <a
                        href={topic.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="atm-topic-link"
                      >
                        🔗 View Source
                      </a>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Article Generation Settings */}
      {selectedTopics.length > 0 && (
        <div className="atm-form-section">
          <h3 className="atm-section-title">Article Generation Settings</h3>
          <div className="atm-form-grid">
            <SelectControl
              label="Article Length"
              value={articleSettings.wordCount}
              options={wordCountOptions}
              onChange={(value) =>
                setArticleSettings((prev) => ({ ...prev, wordCount: value }))
              }
              disabled={isGeneratingArticle}
            />
            <SelectControl
              label="Writing Tone"
              value={articleSettings.tone}
              options={toneOptions}
              onChange={(value) =>
                setArticleSettings((prev) => ({ ...prev, tone: value }))
              }
              disabled={isGeneratingArticle}
            />
          </div>
          <div className="atm-form-grid">
            <CheckboxControl
              label="Generate a featured image for each article"
              checked={articleSettings.includeImages}
              onChange={(value) =>
                setArticleSettings((prev) => ({
                  ...prev,
                  includeImages: value,
                }))
              }
              disabled={isGeneratingArticle}
            />
            <CheckboxControl
              label="Auto-publish articles (otherwise save as draft)"
              checked={articleSettings.autoPublish}
              onChange={(value) =>
                setArticleSettings((prev) => ({ ...prev, autoPublish: value }))
              }
              disabled={isGeneratingArticle}
            />
          </div>
          <Button
            isPrimary
            onClick={generateArticlesFromTrends}
            disabled={isGeneratingArticle}
          >
            {isGeneratingArticle
              ? `Generating ${selectedTopics.length} Article(s)...`
              : `Generate ${selectedTopics.length} Article(s)`}
          </Button>
        </div>
      )}
    </div>
  );
};

export default TrendingForm;
