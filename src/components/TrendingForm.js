// src/components/TrendingForm.js
import { useState, useEffect, useRef } from "@wordpress/element";
import {
  Button,
  TextControl,
  CheckboxControl,
  Spinner,
  DropdownMenu,
  ToggleControl,
} from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";
import { useDispatch } from "@wordpress/data";

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
    const htmlContent = window.marked
      ? window.marked.parse(markdownContent)
      : markdownContent;
    if (window.wp?.data) {
      wp.data.dispatch("core/editor").editPost({ title, content: htmlContent });
    }
  }
};

// --- START: NEW DATA AND SEARCHABLE DROPDOWN COMPONENT ---

// Data for searchable dropdowns
const COUNTRIES_WITH_CODES = [
  { label: "Global", value: "" },
  { label: "United States", value: "US" },
  { label: "United Kingdom", value: "GB" },
  { label: "Canada", value: "CA" },
  { label: "Australia", value: "AU" },
  { label: "Germany", value: "DE" },
  { label: "France", value: "FR" },
  { label: "India", value: "IN" },
  { label: "Brazil", value: "BR" },
  { label: "Japan", value: "JP" },
  { label: "Mexico", value: "MX" },
  { label: "Spain", value: "ES" },
  { label: "Italy", value: "IT" },
  { label: "Russia", value: "RU" },
  { label: "South Korea", value: "KR" },
  { label: "Netherlands", value: "NL" },
  { label: "Sweden", value: "SE" },
  { label: "Switzerland", value: "CH" },
  { label: "Turkey", value: "TR" },
  { label: "Argentina", value: "AR" },
];
const LANGUAGES_WITH_CODES = [
  { label: "English", value: "en" },
  { label: "Spanish", value: "es" },
  { label: "French", value: "fr" },
  { label: "German", value: "de" },
  { label: "Italian", value: "it" },
  { label: "Portuguese", value: "pt" },
  { label: "Russian", value: "ru" },
  { label: "Chinese", value: "zh" },
  { label: "Japanese", value: "ja" },
  { label: "Korean", value: "ko" },
  { label: "Arabic", value: "ar" },
  { label: "Dutch", value: "nl" },
  { label: "Swedish", value: "sv" },
  { label: "Turkish", value: "tr" },
  { label: "Hindi", value: "hi" },
];

// Extract labels for the component options
const COUNTRY_OPTIONS = COUNTRIES_WITH_CODES.map((c) => c.label);
const LANGUAGE_OPTIONS = LANGUAGES_WITH_CODES.map((l) => l.label);

// Create maps for looking up codes from labels
const COUNTRY_MAP = Object.fromEntries(
  COUNTRIES_WITH_CODES.map((c) => [c.label, c.value])
);
const LANGUAGE_MAP = Object.fromEntries(
  LANGUAGES_WITH_CODES.map((l) => [l.label, l.value])
);

// Reusable Searchable Dropdown Component from NewsSearchForm.js
const AdvancedSearchableDropdown = ({
  label,
  placeholder,
  options,
  value,
  onChange,
  disabled = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");
  const dropdownRef = useRef(null);
  const inputRef = useRef(null);
  const filteredOptions = options.filter((option) =>
    option.toLowerCase().includes(searchTerm.toLowerCase())
  );
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
        setSearchTerm("");
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);
  const handleOptionSelect = (option) => {
    onChange(option);
    setIsOpen(false);
    setSearchTerm("");
  };
  return (
    <div className="atm-advanced-filter" ref={dropdownRef}>
      <label className="atm-filter-label">{label}</label>
      <div
        className={`atm-filter-input-container ${isOpen ? "open" : ""} ${disabled ? "disabled" : ""}`}
        onClick={() => !disabled && setIsOpen(true)}
      >
        <div className="atm-single-value">{value || placeholder}</div>
        <svg
          className={`atm-filter-chevron ${isOpen ? "open" : ""}`}
          width="20"
          height="20"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fillRule="evenodd"
            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
            clipRule="evenodd"
          />
        </svg>
      </div>
      {isOpen && (
        <div className="atm-filter-dropdown">
          <input
            ref={inputRef}
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search..."
            className="atm-filter-input-search"
            autoFocus
          />
          <div className="atm-filter-options-list">
            {filteredOptions.length > 0 ? (
              filteredOptions.map((option) => (
                <div
                  key={option}
                  className={`atm-filter-option ${value === option ? "selected" : ""}`}
                  onClick={() => handleOptionSelect(option)}
                >
                  {option}
                </div>
              ))
            ) : (
              <div className="atm-filter-no-results">No results found</div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
// --- END: NEW DATA AND SEARCHABLE DROPDOWN COMPONENT ---

const useDraggableScroll = (dependency) => {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let isDown = false;
    let startX;
    let scrollLeft;
    const mouseDown = (e) => {
      isDown = true;
      el.classList.add("active");
      startX = e.pageX - el.offsetLeft;
      scrollLeft = el.scrollLeft;
    };
    const mouseLeave = () => {
      isDown = false;
      el.classList.remove("active");
    };
    const mouseUp = () => {
      isDown = false;
      el.classList.remove("active");
    };
    const mouseMove = (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - el.offsetLeft;
      const walk = (x - startX) * 2;
      el.scrollLeft = scrollLeft - walk;
    };
    el.addEventListener("mousedown", mouseDown);
    el.addEventListener("mouseleave", mouseLeave);
    el.addEventListener("mouseup", mouseUp);
    el.addEventListener("mousemove", mouseMove);
    return () => {
      el.removeEventListener("mousedown", mouseDown);
      el.removeEventListener("mouseleave", mouseLeave);
      el.removeEventListener("mouseup", mouseUp);
      el.removeEventListener("mousemove", mouseMove);
    };
  }, [dependency]);
  return ref;
};

const TrendingForm = () => {
  const [keyword, setKeyword] = useState("");
  const [trendingTopics, setTrendingTopics] = useState([]);
  const [selectedTopics, setSelectedTopics] = useState([]);
  const [isLoadingTrends, setIsLoadingTrends] = useState(false);
  const [isGeneratingArticle, setIsGeneratingArticle] = useState(false);
  const [statusMessage, setStatusMessage] = useState({ text: "", type: "" });
  const [forceFresh, setForceFresh] = useState(false);
  const { savePost } = useDispatch("core/editor");

  // State now uses strings for labels
  const [region, setRegion] = useState("Global");
  const [language, setLanguage] = useState("English");
  const [date, setDate] = useState({ label: "Past Day", value: "now 1-d" }); // Date can remain simple

  const [articleSettings, setArticleSettings] = useState({
    writingStyle: {
      label: "News / Journalistic",
      value: "News / Journalistic",
    },
    wordCount: { label: "Medium (~800 words)", value: "800-1200" },
    includeImages: true,
    autoPublish: false,
  });

  const dates = [
    { label: "Past Day", value: "now 1-d" },
    { label: "Past Week", value: "now 7-d" },
    { label: "Past Month", value: "today 1-m" },
    { label: "Past Year", value: "today 12-m" },
  ];
  const writingStyles = Object.entries(atm_studio_data.writing_styles).map(
    ([value, { label }]) => ({ label, value })
  );
  const wordCounts = [
    { label: "Short (~500 words)", value: "500" },
    { label: "Medium (~800 words)", value: "800-1200" },
    { label: "Long (~1500 words)", value: "1500-2000" },
  ];
  const draggableRef = useDraggableScroll(trendingTopics);

  const fetchTrendingTopics = async (searchKeyword = "") => {
    setIsLoadingTrends(true);
    setStatusMessage({ text: "Searching Google Trends...", type: "info" });
    setTrendingTopics([]);
    setSelectedTopics([]);
    try {
      // Look up codes from labels before sending
      const regionCode = COUNTRY_MAP[region] ?? "";
      const langCode = LANGUAGE_MAP[language] ?? "en";

      const response = await callAjax("fetch_trending_topics", {
        keyword: searchKeyword,
        region: regionCode,
        language: langCode,
        date: date.value,
        force_fresh: forceFresh,
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
    if (isSelected) setSelectedTopics((prev) => [...prev, topic]);
    else
      setSelectedTopics((prev) => prev.filter((t) => t.title !== topic.title));
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
    const settingsPayload = {
      ...articleSettings,
      writingStyle: articleSettings.writingStyle.value,
      wordCount: articleSettings.wordCount.value,
    };
    if (selectedTopics.length === 1) {
      setStatusMessage({ text: "Generating article...", type: "info" });
      try {
        const langCode = LANGUAGE_MAP[language] ?? "en";
        const response = await callAjax("generate_single_trending_article", {
          trending_topic: JSON.stringify(selectedTopics[0]),
          settings: JSON.stringify(settingsPayload),
          language: language,
        });
        if (!response.success) throw new Error(response.data);
        updateEditorContent(
          response.data.article_title,
          response.data.article_content,
          response.data.subtitle
        );
        setStatusMessage({
          text: "✅ Article inserted! Saving post...",
          type: "success",
        });
        await savePost();
        if (articleSettings.includeImages) {
          setStatusMessage({
            text: "✅ Post saved. Generating featured image...",
            type: "info",
          });
          const postId = document
            .getElementById("atm-studio-root")
            .getAttribute("data-post-id");
          await callAjax("generate_featured_image", {
            post_id: postId,
            prompt: "",
          });
          setStatusMessage({
            text: "✅ All done! Article and featured image generated.",
            type: "success",
          });
        } else {
          setStatusMessage({
            text: "✅ Article inserted and saved!",
            type: "success",
          });
        }
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
        const langCode = LANGUAGE_MAP[language] ?? "en";
        const response = await callAjax("generate_trending_articles", {
          trending_topics: JSON.stringify(selectedTopics),
          settings: JSON.stringify(settingsPayload),
          language: language,
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

  return (
    <div className="atm-form-container">
      <div className="atm-form-section">
        <h3 className="atm-section-title">Find Trending Topics</h3>
        <TextControl
          label="Keyword"
          value={keyword}
          onChange={setKeyword}
          placeholder="e.g., AI, renewable energy, summer movies..."
          help="Search for trends related to a specific topic."
        />
        <div
          className="atm-form-grid"
          style={{ gridTemplateColumns: "repeat(3, 1fr)" }}
        >
          <AdvancedSearchableDropdown
            label="Region"
            placeholder="Select a region..."
            options={COUNTRY_OPTIONS}
            value={region}
            onChange={setRegion}
            disabled={isLoadingTrends || isGeneratingArticle}
          />
          <AdvancedSearchableDropdown
            label="Language"
            placeholder="Select a language..."
            options={LANGUAGE_OPTIONS}
            value={language}
            onChange={setLanguage}
            disabled={isLoadingTrends || isGeneratingArticle}
          />
          <div className="atm-simple-dropdown-wrapper">
            <label className="atm-filter-label">Date</label>
            <DropdownMenu
              icon={chevronDown}
              text={date.label}
              controls={dates.map((option) => ({
                title: option.label,
                onClick: () => setDate(option),
              }))}
              disabled={isLoadingTrends || isGeneratingArticle}
            />
          </div>
        </div>
        <div className="atm-form-actions-column">
          <ToggleControl
            label="Force Fresh Results"
            help="Bypass the 1-hour cache to get new data."
            checked={forceFresh}
            onChange={setForceFresh}
          />
          <div className="atm-search-buttons-wrapper">
            <Button
              isSecondary
              onClick={() => fetchTrendingTopics(keyword.trim())}
              disabled={
                isLoadingTrends || isGeneratingArticle || !keyword.trim()
              }
            >
              Search with Keyword
            </Button>
            <Button
              isPrimary
              onClick={() => fetchTrendingTopics("")}
              disabled={isLoadingTrends || isGeneratingArticle}
            >
              Fetch Top Trends
            </Button>
          </div>
        </div>
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
                      {topic.related_keywords?.length > 0 && (
                        <ul>
                          {topic.related_keywords.slice(0, 3).map((kw) => (
                            <li key={kw}>{kw}</li>
                          ))}
                        </ul>
                      )}
                      {topic.related_trends?.length > 0 && (
                        <div
                          className="atm-related-trends-scroll"
                          ref={draggableRef}
                        >
                          {topic.related_trends.map((rt) => (
                            <div
                              key={rt.title}
                              className="atm-related-trend-item"
                            >
                              {rt.title}{" "}
                              <span className="traffic">({rt.traffic})</span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
          <div className="atm-form-section atm-generation-actions">
            <h3 className="atm-section-title">{`Generate ${selectedTopics.length} Article(s)`}</h3>
            <div
              className="atm-form-grid"
              style={{ gridTemplateColumns: "1fr 1fr" }}
            >
              <DropdownMenu
                icon={chevronDown}
                text={articleSettings.writingStyle.label}
                label="Writing Style"
                controls={writingStyles.map((option) => ({
                  title: option.label,
                  onClick: () =>
                    setArticleSettings((p) => ({ ...p, writingStyle: option })),
                }))}
                disabled={isGeneratingArticle || selectedTopics.length === 0}
              />
              <DropdownMenu
                icon={chevronDown}
                text={articleSettings.wordCount.label}
                label="Word Count"
                controls={wordCounts.map((option) => ({
                  title: option.label,
                  onClick: () =>
                    setArticleSettings((p) => ({ ...p, wordCount: option })),
                }))}
                disabled={isGeneratingArticle || selectedTopics.length === 0}
              />
            </div>
            <div
              className="atm-form-grid"
              style={{ gridTemplateColumns: "1fr 1fr" }}
            >
              <ToggleControl
                label="Generate a featured image"
                help="A relevant image will be generated and set as the post's featured image."
                checked={articleSettings.includeImages}
                onChange={(val) =>
                  setArticleSettings((p) => ({ ...p, includeImages: val }))
                }
                disabled={isGeneratingArticle || selectedTopics.length === 0}
              />
              {selectedTopics.length > 1 && (
                <ToggleControl
                  label="Auto-publish articles"
                  help="Otherwise save as draft"
                  checked={articleSettings.autoPublish}
                  onChange={(val) =>
                    setArticleSettings((p) => ({ ...p, autoPublish: val }))
                  }
                  disabled={isGeneratingArticle || selectedTopics.length === 0}
                />
              )}
            </div>
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
