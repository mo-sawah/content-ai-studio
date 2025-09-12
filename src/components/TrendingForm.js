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
    <div className="atm-dropdown-field">
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
const useDraggableScroll = () => {
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
  }, []);
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

  const [region, setRegion] = useState({ label: "United States", value: "US" });
  const [language, setLanguage] = useState({ label: "English", value: "en" });
  const [date, setDate] = useState({ label: "Past Day", value: "now 1-d" });

  const [articleSettings, setArticleSettings] = useState({
    writingStyle: {
      label: "News / Journalistic",
      value: "News / Journalistic",
    },
    wordCount: { label: "Medium (~800 words)", value: "800-1200" },
    includeImages: true,
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
  const writingStyles = Object.entries(atm_studio_data.writing_styles).map(
    ([value, { label }]) => ({ label, value })
  );
  const wordCounts = [
    { label: "Short (~500 words)", value: "500" },
    { label: "Medium (~800 words)", value: "800-1200" },
    { label: "Long (~1500 words)", value: "1500-2000" },
  ];
  const draggableRef = useDraggableScroll();

  const fetchTrendingTopics = async (searchKeyword = "") => {
    setIsLoadingTrends(true);
    setStatusMessage({ text: "Searching Google Trends...", type: "info" });
    setTrendingTopics([]);
    setSelectedTopics([]);
    try {
      const response = await callAjax("fetch_trending_topics", {
        keyword: searchKeyword,
        region: region.value,
        language: language.value,
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
        const response = await callAjax("generate_single_trending_article", {
          trending_topic: JSON.stringify(selectedTopics[0]),
          settings: JSON.stringify(settingsPayload),
          language: language.label,
        });
        if (!response.success) throw new Error(response.data);
        updateEditorContent(
          response.data.article_title,
          response.data.article_content,
          response.data.subtitle
        );
        setStatusMessage({
          text: "✅ Article inserted! Saving post to generate image...",
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
        const response = await callAjax("generate_trending_articles", {
          trending_topics: JSON.stringify(selectedTopics),
          settings: JSON.stringify(settingsPayload),
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
    fetchTrendingTopics("");
  }, []);

  return (
    <div className="atm-form-container">
      <div className="atm-form-section">
        <h3 className="atm-section-title">Find Trending Topics</h3>
        <div className="atm-keyword-search-wrapper">
          <TextControl
            label="Keyword"
            value={keyword}
            onChange={setKeyword}
            placeholder="Search for trends related to a keyword..."
            help="Search for a specific topic."
            className="atm-keyword-input-flex"
          />
          <Button
            isSecondary
            onClick={() => fetchTrendingTopics(keyword.trim())}
            disabled={isLoadingTrends || isGeneratingArticle || !keyword.trim()}
          >
            Search Keyword
          </Button>
        </div>
        <div className="atm-top-trends-wrapper">
          <Button
            isSecondary
            onClick={() => fetchTrendingTopics("")}
            disabled={isLoadingTrends || isGeneratingArticle}
          >
            Find Top Trends
          </Button>
          <p className="components-base-control__help">
            Find the top overall trending topics for the selected region.
          </p>
        </div>

        <div
          className="atm-form-grid"
          style={{ gridTemplateColumns: "repeat(3, 1fr)" }}
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
        <div className="atm-form-actions-column">
          <ToggleControl
            label="Force Fresh Results"
            help="Bypass the 1-hour cache to get new data."
            checked={forceFresh}
            onChange={setForceFresh}
          />
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
              <CustomDropdown
                label="Writing Style"
                text={articleSettings.writingStyle.label}
                options={writingStyles}
                onChange={(val) =>
                  setArticleSettings((p) => ({ ...p, writingStyle: val }))
                }
                disabled={isGeneratingArticle}
              />
              <CustomDropdown
                label="Word Count"
                text={articleSettings.wordCount.label}
                options={wordCounts}
                onChange={(val) =>
                  setArticleSettings((p) => ({ ...p, wordCount: val }))
                }
                disabled={isGeneratingArticle}
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
                disabled={isGeneratingArticle}
              />
              {selectedTopics.length > 1 && (
                <ToggleControl
                  label="Auto-publish articles"
                  help="Otherwise save as draft"
                  checked={articleSettings.autoPublish}
                  onChange={(val) =>
                    setArticleSettings((p) => ({ ...p, autoPublish: val }))
                  }
                  disabled={isGeneratingArticle}
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
