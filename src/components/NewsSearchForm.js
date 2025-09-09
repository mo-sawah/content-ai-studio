// src/components/NewsSearchForm.js
import { useState } from "@wordpress/element";
import { Button, TextControl, Spinner } from "@wordpress/components";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const updateEditorContent = (title, markdownContent, subtitle) => {
  const isBlockEditor = document.body.classList.contains("block-editor-page");
  const htmlContent = window.marked
    ? window.marked.parse(markdownContent)
    : markdownContent;

  if (isBlockEditor) {
    wp.data.dispatch("core/editor").editPost({ title });
    const blocks = wp.blocks.parse(htmlContent);
    const currentBlocks = wp.data.select("core/block-editor").getBlocks();
    if (
      currentBlocks.length > 0 &&
      !(
        currentBlocks.length === 1 &&
        currentBlocks[0].name === "core/paragraph" &&
        currentBlocks[0].attributes.content === ""
      )
    ) {
      const clientIds = currentBlocks.map((block) => block.clientId);
      wp.data.dispatch("core/block-editor").removeBlocks(clientIds);
    }
    wp.data.dispatch("core/block-editor").insertBlocks(blocks);
  } else {
    jQuery("#title").val(title);
    jQuery("#title-prompt-text").hide();
    jQuery("#title").trigger("blur");
    if (window.tinymce && window.tinymce.get("content")) {
      window.tinymce.get("content").setContent(htmlContent);
    } else {
      jQuery("#content").val(htmlContent);
    }
  }

  // Handle subtitle
  if (subtitle && subtitle.trim()) {
    console.log("ATM: Attempting to populate subtitle:", subtitle);
    setTimeout(function () {
      const subtitleField = jQuery('input[name="_bunyad_sub_title"]');
      if (subtitleField.length > 0) {
        console.log("ATM: Found SmartMag subtitle field, populating...");
        subtitleField.val(subtitle);
        subtitleField.trigger("input").trigger("change").trigger("keyup");
        console.log("ATM: Subtitle populated successfully");
      } else {
        console.log("ATM: SmartMag subtitle field not found");
      }
    }, 1000);
  }
};

function NewsSearchForm() {
  const [isSearching, setIsSearching] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState([]);
  const [generatingIndex, setGeneratingIndex] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalResults, setTotalResults] = useState(0);
  const [imageCheckboxes, setImageCheckboxes] = useState({});

  const resultsPerPage = 10;
  const totalPages = Math.ceil(totalResults / resultsPerPage);

  const handleSearch = async (page = 1) => {
    if (!searchQuery.trim()) {
      alert("Please enter a search term.");
      return;
    }

    setIsSearching(true);
    setStatusMessage("Searching Google News...");
    if (page === 1) {
      setSearchResults([]);
      setImageCheckboxes({});
    }

    try {
      const response = await callAjax("search_google_news", {
        query: searchQuery,
        page: page,
        per_page: resultsPerPage,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      setSearchResults(response.data.articles || []);
      setTotalResults(
        response.data.total || response.data.articles?.length || 0
      );
      setCurrentPage(page);
      setStatusMessage(
        `Found ${response.data.total || response.data.articles?.length || 0} news articles`
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setSearchResults([]);
      setTotalResults(0);
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

    const shouldGenerateImage = imageCheckboxes[index] || false;

    try {
      const response = await callAjax("generate_article_from_news_source", {
        post_id: postId,
        source_url: article.link,
        source_title: article.title,
        source_snippet: article.snippet,
        source_date: article.date,
        source_domain: article.source,
        generate_image: shouldGenerateImage,
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

      // Clear the checkbox state for this item
      setImageCheckboxes((prev) => {
        const newState = { ...prev };
        delete newState[index];
        return newState;
      });
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsGenerating(false);
      setGeneratingIndex(null);
    }
  };

  const handleImageCheckboxChange = (index, checked) => {
    setImageCheckboxes((prev) => ({
      ...prev,
      [index]: checked,
    }));
  };

  const handlePageChange = (page) => {
    if (page >= 1 && page <= totalPages && !isSearching) {
      handleSearch(page);
    }
  };

  const renderPagination = () => {
    if (totalPages <= 1) return null;

    const pages = [];
    const showPages = 5; // Number of page buttons to show
    let startPage = Math.max(1, currentPage - Math.floor(showPages / 2));
    let endPage = Math.min(totalPages, startPage + showPages - 1);

    // Adjust start page if we're near the end
    if (endPage - startPage + 1 < showPages) {
      startPage = Math.max(1, endPage - showPages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }

    return (
      <div className="atm-news-pagination">
        <button
          className="atm-pagination-btn"
          onClick={() => handlePageChange(currentPage - 1)}
          disabled={currentPage === 1 || isSearching}
        >
          ← Previous
        </button>

        {startPage > 1 && (
          <>
            <button
              className="atm-pagination-btn"
              onClick={() => handlePageChange(1)}
              disabled={isSearching}
            >
              1
            </button>
            {startPage > 2 && <span className="atm-pagination-info">...</span>}
          </>
        )}

        {pages.map((page) => (
          <button
            key={page}
            className={`atm-pagination-btn ${page === currentPage ? "active" : ""}`}
            onClick={() => handlePageChange(page)}
            disabled={isSearching}
          >
            {page}
          </button>
        ))}

        {endPage < totalPages && (
          <>
            {endPage < totalPages - 1 && (
              <span className="atm-pagination-info">...</span>
            )}
            <button
              className="atm-pagination-btn"
              onClick={() => handlePageChange(totalPages)}
              disabled={isSearching}
            >
              {totalPages}
            </button>
          </>
        )}

        <button
          className="atm-pagination-btn"
          onClick={() => handlePageChange(currentPage + 1)}
          disabled={currentPage === totalPages || isSearching}
        >
          Next →
        </button>

        <div className="atm-pagination-info">
          Page {currentPage} of {totalPages} ({totalResults} results)
        </div>
      </div>
    );
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
          onClick={() => handleSearch(1)}
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
            <p>Found {totalResults} recent articles</p>
          </div>

          <div className="atm-news-results-grid">
            {searchResults.map((article, index) => (
              <div
                key={index}
                className={`atm-news-item-card ${generatingIndex === index ? "atm-news-item-generating" : ""}`}
              >
                <div className="atm-news-content-wrapper">
                  <div className="atm-news-thumbnail">
                    {article.image ? (
                      <img src={article.image} alt={article.title} />
                    ) : (
                      <div className="atm-news-placeholder">
                        <svg
                          width="32"
                          height="32"
                          viewBox="0 0 24 24"
                          fill="currentColor"
                        >
                          <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z" />
                        </svg>
                      </div>
                    )}
                  </div>

                  <div className="atm-news-text-content">
                    <h4 className="atm-news-title">{article.title}</h4>

                    <div className="atm-news-meta">
                      <span className="atm-news-source">{article.source}</span>
                      <span className="atm-news-date">{article.date}</span>
                      <a
                        href={article.link}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="atm-view-source-link"
                      >
                        View Source
                      </a>
                    </div>

                    <p className="atm-news-description">{article.snippet}</p>
                  </div>
                </div>

                <div className="atm-news-actions">
                  <button
                    className="atm-generate-article-btn"
                    onClick={() => handleGenerateFromSource(article, index)}
                    disabled={isGenerating}
                  >
                    {generatingIndex === index ? (
                      <>
                        <Spinner />
                        Generating...
                      </>
                    ) : (
                      "Generate Article"
                    )}
                  </button>

                  <div className="atm-image-checkbox-wrapper">
                    <input
                      type="checkbox"
                      id={`generate-image-${index}`}
                      checked={imageCheckboxes[index] || false}
                      onChange={(e) =>
                        handleImageCheckboxChange(index, e.target.checked)
                      }
                      disabled={isGenerating}
                    />
                    <label htmlFor={`generate-image-${index}`}>
                      Also generate featured image
                    </label>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {renderPagination()}
        </div>
      )}

      {searchResults.length === 0 && searchQuery && !isSearching && (
        <div className="atm-news-empty-state">
          <svg
            width="64"
            height="64"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <path d="M21 21l-4.35-4.35"></path>
          </svg>
          <h3>No results found</h3>
          <p>Try searching with different keywords or check your spelling.</p>
        </div>
      )}
    </div>
  );
}

export default NewsSearchForm;
