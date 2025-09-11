// src/components/RssForm.js
import { useState } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import { Button, TextControl, CheckboxControl } from "@wordpress/components";
import { external } from "@wordpress/icons";
import CustomSpinner from "./common/CustomSpinner";

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
    // Fallback to basic HTML insertion
    const htmlContent = window.marked
      ? window.marked.parse(markdownContent)
      : markdownContent;
    if (window.wp && window.wp.data) {
      wp.data.dispatch("core/editor").editPost({ title, content: htmlContent });
    }
  }
};

function RssForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [keyword, setKeyword] = useState("");
  const [deepSearch, setDeepSearch] = useState(false);
  const [useFullContent, setUseFullContent] = useState(true);
  const [results, setResults] = useState([]);
  const { savePost } = useDispatch("core/editor");
  const isSaving = useSelect((select) => select("core/editor").isSavingPost());

  const handleFetch = async (searchKeyword = "", useScraping = false) => {
    setIsLoading(true);
    setStatusMessage(
      searchKeyword ? "Searching feeds..." : "Fetching latest..."
    );
    setResults([]);
    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      const response = await callAjax("fetch_rss_articles", {
        post_id: postId,
        keyword: searchKeyword,
        use_scraping: useScraping,
      });
      if (!response.success) throw new Error(response.data);
      setResults(response.data);
      setStatusMessage(
        response.data.length > 0
          ? `Found ${response.data.length} articles.`
          : "No articles found."
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleGenerateFromRss = async (article, index) => {
    setStatusMessage(`Generating from "${article.title}"...`);
    setIsLoading(true);

    const newResults = [...results];
    newResults[index].isGenerating = true;
    setResults(newResults);

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    // ADD TIMEOUT WRAPPER
    const timeoutPromise = new Promise((_, reject) =>
      setTimeout(
        () => reject(new Error("Request timed out after 2 minutes")),
        120000
      )
    );

    const ajaxPromise = callAjax("generate_article_from_rss", {
      post_id: postId,
      article_url: article.link,
      article_guid: article.guid,
      rss_content: article.content,
      use_full_content: useFullContent,
    });

    try {
      const response = await Promise.race([ajaxPromise, timeoutPromise]);

      if (!response.success) throw new Error(response.data);

      console.log("ATM Debug - Response data:", response.data);
      console.log("ATM Debug - Subtitle received:", response.data.subtitle);

      updateEditorContent(
        response.data.article_title,
        response.data.article_content,
        response.data.subtitle || ""
      );
      setStatusMessage(`✅ Article generated from "${article.title}"!`);

      setResults(results.filter((_, i) => i !== index));

      if (response.data.subtitle) {
        setStatusMessage(
          `✅ Article generated! Saving post to apply subtitle...`
        );
        await savePost();
        setStatusMessage(
          `✅ Article and subtitle saved from "${article.title}"!`
        );
      }
    } catch (error) {
      console.error("RSS Generation Error:", error);
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      const finalResults = [...results];
      if (finalResults[index]) {
        finalResults[index].isGenerating = false;
        setResults(finalResults);
      }
    }
  };

  return (
    <div className="atm-form-container">
      <TextControl
        label="Search by Keyword (Optional)"
        value={keyword}
        onChange={setKeyword}
        placeholder="e.g., Trump, AI, Healthcare"
        disabled={isLoading}
      />
      <CheckboxControl
        label="Deep Content Search"
        help="Scrapes full article content for more accurate keyword matching (slower but more precise)."
        checked={deepSearch}
        onChange={setDeepSearch}
        disabled={isLoading}
      />
      <CheckboxControl
        label="Use Full Article Content for Generation"
        help="Scrapes the complete article for better AI rewriting (recommended)."
        checked={useFullContent}
        onChange={setUseFullContent}
        disabled={isLoading}
      />
      <div className="atm-grid-2">
        <Button
          isSecondary
          onClick={() => handleFetch(keyword, deepSearch)}
          disabled={isLoading || !keyword}
        >
          {isLoading ? (
            <>
              <CustomSpinner /> Searching...
            </>
          ) : (
            "Search Feeds"
          )}
        </Button>
        <Button isPrimary onClick={() => handleFetch()} disabled={isLoading}>
          {isLoading ? (
            <>
              <CustomSpinner /> Fetching...
            </>
          ) : (
            "Fetch Latest Articles"
          )}
        </Button>
      </div>
      {statusMessage && <p className="atm-status-message">{statusMessage}</p>}

      {results.length > 0 && (
        <div className="atm-video-results-list">
          {results.map((article, index) => (
            <div key={article.guid} className="atm-video-result-item">
              <div className="atm-video-thumbnail">
                {article.image ? (
                  <img src={article.image} alt={article.title} />
                ) : (
                  <div className="atm-rss-thumbnail-placeholder">
                    <span className="dashicons dashicons-rss"></span>
                  </div>
                )}
              </div>
              <div className="atm-video-details">
                <h4 className="atm-video-title">{article.title}</h4>
                <div className="atm-video-meta">
                  <span>{article.source}</span> • <span>{article.date}</span>
                </div>
                <p className="atm-video-description">
                  {article.description.substring(0, 150)}...
                </p>

                <div className="atm-video-actions">
                  <Button
                    isSecondary
                    icon={external}
                    href={article.link}
                    target="_blank"
                  >
                    Visit Website
                  </Button>
                  <Button
                    isPrimary
                    onClick={() => handleGenerateFromRss(article, index)}
                    disabled={isLoading || article.isGenerating}
                    className="is-embed"
                  >
                    {article.isGenerating ? (
                      <>
                        <CustomSpinner /> Generating...
                      </>
                    ) : (
                      "Generate Article"
                    )}
                  </Button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default RssForm;
