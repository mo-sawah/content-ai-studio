// src/components/TrendingForm.js
import { useState, useEffect, useRef } from "@wordpress/element";
import {
  Button,
  TextControl,
  CheckboxControl,
  Spinner,
  DropdownMenu,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });
const updateEditorContent = (title, markdownContent, subtitle) => {
  if (window.ATM_BlockUtils) {
    window.ATM_BlockUtils.updateEditorContent(title, markdownContent, subtitle);
  } else {
    console.error("ATM: Block utilities not loaded");
    const htmlContent = window.marked
      ? window.marked.parse(markdownContent)
      : markdownContent;
    if (window.wp && window.wp.data) {
      wp.data.dispatch("core/editor").editPost({ title, content: htmlContent });
    }
  }
};
const CustomDropdown = ({ label, text, options, onChange, disabled }) => {
  const dropdownRef = useRef(null);
  useEffect(() => {
    if (dropdownRef.current) {
      const width = dropdownRef.current.offsetWidth;
      document.documentElement.style.setProperty(
        "--atm-dropdown-width",
        `${width}px`
      );
    }
  }, [text]);
  return (
    <div className="atm-dropdown-field" ref={dropdownRef}>
      {" "}
      <label className="atm-dropdown-label">{label}</label>{" "}
      <DropdownMenu
        className="atm-custom-dropdown"
        icon={chevronDown}
        text={text}
        controls={options.map((option) => ({
          title: option.label,
          onClick: () => onChange(option),
        }))}
        disabled={disabled}
      />{" "}
    </div>
  );
};

const TrendingForm = () => {
  const [keyword, setKeyword] = useState("");
  const [trendingTopics, setTrendingTopics] = useState([]);
  const [selectedTopics, setSelectedTopics] = useState([]);
  const [isLoadingTrends, setIsLoadingTrends] = useState(false);
  const [isGeneratingArticle, setIsGeneratingArticle] = useState(false);
  const [statusMessage, setStatusMessage] = useState({ text: "", type: "" });
  const [region, setRegion] = useState({ label: "United States", value: "US" });
  const [language, setLanguage] = useState({ label: "English", value: "en" });
  const [date, setDate] = useState({ label: "Past Day", value: "now 1-d" });
  const [articleSettings, setArticleSettings] = useState({
    autoPublish: false,
  });

  const regions = [
    { label: "United States", value: "US" },
    { label: "United Kingdom", value: "GB" },
    { label: "Canada", value: "CA" },
    { label: "Australia", value: "AU" },
    { label: "Germany", value: "DE" },
    { label: "France", value: "FR" },
    { label: "India", value: "IN" },
    { label: "Global", value: "" },
  ];
  const languages = [
    { label: "English", value: "en" },
    { label: "Spanish", value: "es" },
    { label: "German", value: "de" },
    { label: "French", value: "fr" },
  ];
  const dates = [
    { label: "Past Day", value: "now 1-d" },
    { label: "Past Week", value: "now 7-d" },
    { label: "Past Month", value: "today 1-m" },
    { label: "Past Year", value: "today 12-m" },
  ];

  const fetchTrendingTopics = async () => {
    setIsLoadingTrends(true);
    setStatusMessage({ text: "Searching Google Trends...", type: "info" });
    setTrendingTopics([]);
    setSelectedTopics([]);
    try {
      const response = await callAjax("fetch_trending_topics", {
        keyword: keyword.trim(),
        region: region.value,
        language: language.value,
        date: date.value,
      });
      if (!response.success) throw new Error(response.data);
      setTrendingTopics(response.data.trends || []);
      setStatusMessage({
        text: response.data.trends?.length
          ? `Found ${response.data.trends.length} unique trending topics.`
          : "No trending topics found. Try a different keyword or timeframe.",
        type: "info",
      });
    } catch (err) {
      setStatusMessage({ text: `Error: ${err.message}`, type: "error" });
    } finally {
      setIsLoadingTrends(false);
    }
  };

  const handleTopicSelection = (topic, isSelected) => {
    if (isSelected) {
      setSelectedTopics((prev) => [...prev, topic]);
    } else {
      setSelectedTopics((prev) => prev.filter((t) => t.title !== topic.title));
    }
  };

  const generateArticlesFromTrends = async () => {
    if (selectedTopics.length === 0) {
      setStatusMessage({
        text: "Please select at least one topic.",
        type: "error",
      });
      return;
    }
    setIsGeneratingArticle(true);
    if (selectedTopics.length === 1) {
      setStatusMessage({
        text: "Generating article and inserting into editor...",
        type: "info",
      });
      try {
        const response = await callAjax("generate_single_trending_article", {
          trending_topic: JSON.stringify(selectedTopics[0]),
          language: language.label,
        });
        if (!response.success) throw new Error(response.data);
        updateEditorContent(
          response.data.article_title,
          response.data.article_content,
          ""
        );
        setStatusMessage({
          text: "✅ Article content inserted into the editor!",
          type: "success",
        });
        setSelectedTopics([]);
      } catch (err) {
        setStatusMessage({ text: `Error: ${err.message}`, type: "error" });
      } finally {
        setIsGeneratingArticle(false);
      }
    } else {
      setStatusMessage({
        text: `Generating ${selectedTopics.length} new draft posts...`,
        type: "info",
      });
      try {
        const response = await callAjax("generate_trending_articles", {
          trending_topics: JSON.stringify(selectedTopics),
          settings: JSON.stringify(articleSettings),
          language: language.label,
        });
        if (!response.success) throw new Error(response.data);
        setStatusMessage({
          text: `✅ Successfully created ${response.data.successful_count} new draft posts!`,
          type: "success",
        });
        setSelectedTopics([]);
      } catch (err) {
        setStatusMessage({ text: `Error: ${err.message}`, type: "error" });
      } finally {
        setIsGeneratingArticle(false);
      }
    }
  };

  useEffect(() => {
    fetchTrendingTopics();
  }, []);

  return (
    <div className="atm-form-container">
      <div className="atm-form-section">
        <h3 className="atm-section-title">Find Trending Topics</h3>
        <TextControl
          label="Keyword"
          value={keyword}
          onChange={setKeyword}
          placeholder="e.g., AI, renewable energy, summer movies..."
          help="Leave empty for general trends in the selected region."
        />
        <div
          className="atm-form-grid"
          style={{ gridTemplateColumns: "1fr 1fr 1fr" }}
        >
          <CustomDropdown
            label="Region"
            text={region.label}
            options={regions}
            onChange={setRegion}
            disabled={isLoadingTrends}
          />
          <CustomDropdown
            label="Language"
            text={language.label}
            options={languages}
            onChange={setLanguage}
            disabled={isLoadingTrends}
          />
          <CustomDropdown
            label="Date"
            text={date.label}
            options={dates}
            onChange={setDate}
            disabled={isLoadingTrends}
          />
        </div>
        <Button
          isPrimary
          onClick={fetchTrendingTopics}
          disabled={isLoadingTrends || isGeneratingArticle}
        >
          {isLoadingTrends ? (
            <>
              <Spinner />
              Searching...
            </>
          ) : (
            "Search For Trends"
          )}
        </Button>
      </div>

      {statusMessage.text && (
        <p className={`atm-status-message ${statusMessage.type}`}>
          {statusMessage.text}
        </p>
      )}

      {!isLoadingTrends && trendingTopics.length > 0 && (
        <>
          <div className="atm-trending-topics-grid">
            {trendingTopics.map((topic, index) => (
              <div
                key={index}
                className={`atm-topic-card ${selectedTopics.some((t) => t.title === topic.title) ? "selected" : ""}`}
              >
                <div className="atm-card-header">
                  <CheckboxControl
                    checked={selectedTopics.some(
                      (t) => t.title === topic.title
                    )}
                    onChange={(isSelected) =>
                      handleTopicSelection(topic, isSelected)
                    }
                    disabled={isGeneratingArticle}
                  />
                  <h4 className="atm-card-title">{topic.title}</h4>
                </div>
                <div className="atm-card-body">
                  <p className="atm-card-snippet">{topic.snippet}</p>
                  <div className="atm-card-meta">
                    <span className="atm-card-traffic">🔥 {topic.traffic}</span>
                    <a
                      href={topic.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="atm-card-link"
                    >
                      View on Google Trends ↗
                    </a>
                  </div>
                  {(topic.related_keywords?.length > 0 ||
                    topic.related_trends?.length > 0) && (
                    <div className="atm-related-keywords">
                      <strong>Related:</strong>
                      <ul>
                        {topic.related_keywords?.slice(0, 3).map((kw) => (
                          <li key={kw}>{kw}</li>
                        ))}
                      </ul>
                      <div className="atm-related-trends-scroll">
                        {topic.related_trends?.map((rt) => (
                          <div
                            key={rt.title}
                            className="atm-related-trend-item"
                          >
                            {rt.title}{" "}
                            <span className="traffic">({rt.traffic})</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
          <div className="atm-form-section atm-generation-actions">
            <h3 className="atm-section-title">{`Generate ${selectedTopics.length} Article(s)`}</h3>
            {selectedTopics.length > 1 && (
              <CheckboxControl
                label="Auto-publish articles (otherwise save as draft)"
                checked={articleSettings.autoPublish}
                onChange={(value) =>
                  setArticleSettings((prev) => ({
                    ...prev,
                    autoPublish: value,
                  }))
                }
                disabled={isGeneratingArticle}
              />
            )}
            <Button
              isPrimary
              onClick={generateArticlesFromTrends}
              disabled={isGeneratingArticle || selectedTopics.length === 0}
            >
              {isGeneratingArticle ? (
                <>
                  <Spinner />
                  Generating...
                </>
              ) : (
                `Generate ${selectedTopics.length > 0 ? selectedTopics.length : ""} Selected Article(s)`
              )}
            </Button>
            {selectedTopics.length > 1 && (
              <p className="components-base-control__help">
                When generating multiple articles, new draft posts will be
                created.
              </p>
            )}
          </div>
        </>
      )}
    </div>
  );
};

export default TrendingForm;
