// src/components/NewsSearchForm.js
import { useState } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

function NewsSearchForm() {
  const [isSearching, setIsSearching] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState([]);
  const [generatingIndex, setGeneratingIndex] = useState(null);

  const handleSearch = async () => {
    if (!searchQuery.trim()) {
      alert("Please enter a search term.");
      return;
    }

    setIsSearching(true);
    setStatusMessage("Searching Google News...");
    setSearchResults([]);

    try {
      const response = await callAjax("search_google_news", {
        query: searchQuery,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      setSearchResults(response.data.articles || []);
      setStatusMessage(
        `Found ${response.data.articles?.length || 0} news articles`
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setSearchResults([]);
    } finally {
      setIsSearching(false);
    }
  };

  const handleGenerateFromSource = async (article, index) => {
    setGeneratingIndex(index);
    setIsGenerating(true);
    setStatusMessage(`Generating article from "${article.title}"...`);

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      const response = await callAjax("generate_article_from_news_source", {
        post_id: postId,
        source_url: article.link,
        source_title: article.title,
        source_snippet: article.snippet,
        source_date: article.date,
        source_domain: article.source,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      // Update editor with generated content
      updateEditorContent(
        response.data.article_title,
        response.data.article_content,
        response.data.subtitle || ""
      );

      setStatusMessage(`✅ Article generated from "${article.title}"!`);

      // Remove this result from the list
      setSearchResults((prev) => prev.filter((_, i) => i !== index));
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsGenerating(false);
      setGeneratingIndex(null);
    }
  };

  return (
    <div className="atm-form-container">
      <div className="atm-news-search-form">
        <TextControl
          label="Search Google News"
          value={searchQuery}
          onChange={setSearchQuery}
          placeholder="e.g., artificial intelligence, climate change, elections"
          disabled={isSearching || isGenerating}
          help="Enter keywords to search for recent news articles"
        />

        <Button
          isPrimary
          onClick={handleSearch}
          disabled={isSearching || isGenerating || !searchQuery.trim()}
          className="atm-search-news-btn"
        >
          {isSearching ? (
            <>
              <Spinner />
              Searching...
            </>
          ) : (
            <>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path
                  d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
              Search Google News
            </>
          )}
        </Button>
      </div>

      {statusMessage && (
        <p
          className={`atm-status-message ${
            statusMessage.includes("✅")
              ? "success"
              : statusMessage.includes("Error")
                ? "error"
                : "info"
          }`}
        >
          {statusMessage}
        </p>
      )}

      {searchResults.length > 0 && (
        <div className="atm-news-search-results">
          <div className="atm-results-header">
            <h3>News Search Results for "{searchQuery}"</h3>
            <p>Found {searchResults.length} recent articles</p>
          </div>

          <div className="atm-news-results-list">
            {searchResults.map((article, index) => (
              <div key={index} className="atm-news-result-item">
                <div className="atm-news-thumbnail">
                  {article.image ? (
                    <img src={article.image} alt={article.title} />
                  ) : (
                    <div className="atm-news-placeholder">
                      <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                      >
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z" />
                      </svg>
                    </div>
                  )}
                </div>

                <div className="atm-news-content">
                  <h4 className="atm-news-title">{article.title}</h4>
                  <div className="atm-news-meta">
                    <span className="atm-news-source">{article.source}</span>
                    <span className="atm-news-date">{article.date}</span>
                  </div>
                  <p className="atm-news-snippet">{article.snippet}</p>

                  <div className="atm-news-actions">
                    <a
                      href={article.link}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="atm-view-source-btn"
                    >
                      View Source
                    </a>

                    <Button
                      isPrimary
                      onClick={() => handleGenerateFromSource(article, index)}
                      disabled={isGenerating}
                      className="atm-generate-btn"
                    >
                      {generatingIndex === index ? (
                        <>
                          <Spinner />
                          Generating...
                        </>
                      ) : (
                        <>
                          <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                          >
                            <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z" />
                          </svg>
                          Generate Article
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default NewsSearchForm;
