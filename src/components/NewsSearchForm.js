// src/components/NewsSearchForm.js
import { useState, useRef, useEffect } from "@wordpress/element";
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

// Data sources for filters
const LANGUAGES = [
  "English",
  "Spanish",
  "French",
  "German",
  "Italian",
  "Portuguese",
  "Russian",
  "Chinese",
  "Japanese",
  "Korean",
  "Arabic",
  "Dutch",
  "Swedish",
  "Norwegian",
  "Danish",
  "Finnish",
  "Polish",
  "Czech",
  "Hungarian",
  "Romanian",
  "Bulgarian",
  "Croatian",
  "Slovak",
  "Slovenian",
  "Estonian",
  "Latvian",
  "Lithuanian",
  "Greek",
  "Turkish",
  "Hindi",
  "Bengali",
  "Urdu",
  "Tamil",
  "Telugu",
  "Marathi",
  "Thai",
  "Vietnamese",
  "Indonesian",
  "Malay",
  "Filipino",
  "Hebrew",
  "Persian",
];

const COUNTRIES = [
  // Major countries with news presence
  "United States",
  "United Kingdom",
  "Canada",
  "Australia",
  "Germany",
  "France",
  "Spain",
  "Italy",
  "Netherlands",
  "Belgium",
  "Sweden",
  "Norway",
  "Denmark",
  "Finland",
  "Poland",
  "Czech Republic",
  "Austria",
  "Switzerland",
  "Portugal",
  "Ireland",
  "Greece",
  "Turkey",
  "Russia",
  "Ukraine",
  "Romania",
  "Bulgaria",
  "Hungary",
  "Croatia",
  "Slovakia",
  "Slovenia",
  "Estonia",
  "Latvia",
  "Lithuania",
  "Japan",
  "South Korea",
  "China",
  "India",
  "Singapore",
  "Malaysia",
  "Thailand",
  "Indonesia",
  "Philippines",
  "Vietnam",
  "Taiwan",
  "Hong Kong",
  "Israel",
  "Saudi Arabia",
  "United Arab Emirates",
  "Egypt",
  "South Africa",
  "Nigeria",
  "Kenya",
  "Brazil",
  "Argentina",
  "Chile",
  "Colombia",
  "Mexico",
  "Peru",
  "Venezuela",
  "Ecuador",
  "Uruguay",
  "Paraguay",
  "Bolivia",
];

// Advanced Filter Component
const AdvancedSearchableDropdown = ({
  label,
  placeholder,
  options,
  value,
  onChange,
  multiSelect = false,
  disabled = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");
  const [filteredOptions, setFilteredOptions] = useState(options);
  const dropdownRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    setFilteredOptions(
      options.filter((option) =>
        option.toLowerCase().includes(searchTerm.toLowerCase())
      )
    );
  }, [searchTerm, options]);

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
    if (multiSelect) {
      const newValue = Array.isArray(value) ? [...value] : [];
      if (newValue.includes(option)) {
        onChange(newValue.filter((v) => v !== option));
      } else {
        onChange([...newValue, option]);
      }
      setSearchTerm("");
      inputRef.current?.focus();
    } else {
      onChange(option);
      setIsOpen(false);
      setSearchTerm("");
    }
  };

  const removeTag = (optionToRemove) => {
    if (multiSelect && Array.isArray(value)) {
      onChange(value.filter((v) => v !== optionToRemove));
    }
  };

  const renderSingleValue = () => {
    if (!multiSelect && value) {
      return <div className="atm-single-value">{value}</div>;
    }
    return null;
  };

  const renderTags = () => {
    if (multiSelect && Array.isArray(value) && value.length > 0) {
      return (
        <div className="atm-tags-container">
          {value.map((tag) => (
            <div key={tag} className="atm-filter-tag">
              <span>{tag}</span>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  removeTag(tag);
                }}
                className="atm-tag-remove"
              >
                ×
              </button>
            </div>
          ))}
        </div>
      );
    }
    return null;
  };

  return (
    <div className="atm-advanced-filter" ref={dropdownRef}>
      <label className="atm-filter-label">{label}</label>
      <div
        className={`atm-filter-input-container ${isOpen ? "open" : ""} ${disabled ? "disabled" : ""}`}
        onClick={() => !disabled && setIsOpen(true)}
      >
        {renderTags()}
        {renderSingleValue()}
        <input
          ref={inputRef}
          type="text"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          placeholder={placeholder}
          className="atm-filter-input"
          onFocus={() => !disabled && setIsOpen(true)}
          disabled={disabled}
        />
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
          {filteredOptions.length > 0 ? (
            filteredOptions.map((option) => (
              <div
                key={option}
                className={`atm-filter-option ${
                  multiSelect && Array.isArray(value) && value.includes(option)
                    ? "selected"
                    : ""
                } ${!multiSelect && value === option ? "selected" : ""}`}
                onClick={() => handleOptionSelect(option)}
              >
                {option}
                {multiSelect &&
                  Array.isArray(value) &&
                  value.includes(option) && (
                    <svg
                      className="atm-option-check"
                      width="16"
                      height="16"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        fillRule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clipRule="evenodd"
                      />
                    </svg>
                  )}
              </div>
            ))
          ) : (
            <div className="atm-filter-no-results">No results found</div>
          )}
        </div>
      )}
    </div>
  );
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

  // New advanced filter states
  const [articleLanguage, setArticleLanguage] = useState("English");
  const [sourceLanguages, setSourceLanguages] = useState([]);
  const [countries, setCountries] = useState(["United States"]);

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
        article_language: articleLanguage,
        source_languages: sourceLanguages,
        countries: countries,
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
      // Step 1: Generate article content ONLY (no image yet)
      const response = await callAjax("generate_article_from_news_source", {
        post_id: postId,
        source_url: article.link,
        source_title: article.title,
        source_snippet: article.snippet,
        source_date: article.date,
        source_domain: article.source,
        article_language: articleLanguage, // Pass the selected article language
        generate_image: false, // Always false - we'll handle image separately
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      // Step 2: Update editor with article content
      updateEditorContent(
        response.data.article_title,
        response.data.article_content,
        response.data.subtitle || ""
      );

      // Step 3: Handle image generation if requested
      if (shouldGenerateImage) {
        setStatusMessage(
          `Article generated from "${article.title}"! Now saving post...`
        );

        // Save the post first
        await wp.data.dispatch("core/editor").savePost();

        setStatusMessage(`Post saved! Generating featured image...`);

        try {
          const imageResponse = await callAjax("generate_featured_image", {
            post_id: postId,
            prompt: "", // Use default prompt
          });

          if (!imageResponse.success) {
            setStatusMessage(
              `Article generated from "${article.title}"! Image generation failed: ${imageResponse.data}`
            );
            // Remove result after showing error message for a few seconds
            setTimeout(() => {
              setSearchResults((prev) => prev.filter((_, i) => i !== index));
              setImageCheckboxes((prev) => {
                const newState = { ...prev };
                delete newState[index];
                return newState;
              });
            }, 3000);
          } else {
            setStatusMessage(
              `Article and featured image generated successfully from "${article.title}"! Reloading to display image...`
            );
            // Reload page to show the new featured image
            setTimeout(() => window.location.reload(), 2000);
            return; // Don't remove result - page will reload
          }
        } catch (imageError) {
          setStatusMessage(
            `Article generated from "${article.title}"! Image generation encountered an error.`
          );
          console.error("Image generation error:", imageError);
          // Remove result after showing error message
          setTimeout(() => {
            setSearchResults((prev) => prev.filter((_, i) => i !== index));
            setImageCheckboxes((prev) => {
              const newState = { ...prev };
              delete newState[index];
              return newState;
            });
          }, 3000);
        }
      } else {
        // No image requested - show success and remove result
        setStatusMessage(`Article generated from "${article.title}"!`);

        // Remove this result from the list after showing success message
        setTimeout(() => {
          setSearchResults((prev) => prev.filter((_, i) => i !== index));
          setImageCheckboxes((prev) => {
            const newState = { ...prev };
            delete newState[index];
            return newState;
          });
        }, 2000);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      // Don't remove result on error - user might want to try again
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
    const showPages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(showPages / 2));
    let endPage = Math.min(totalPages, startPage + showPages - 1);

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

        {/* Advanced Filters */}
        <div className="atm-advanced-filters-container">
          <h3 className="atm-filters-title">Advanced Filters</h3>
          <div className="atm-filters-grid">
            <AdvancedSearchableDropdown
              label="Article Language"
              placeholder="Select language for generated article..."
              options={LANGUAGES}
              value={articleLanguage}
              onChange={setArticleLanguage}
              multiSelect={false}
              disabled={isSearching || isGenerating}
            />

            <AdvancedSearchableDropdown
              label="Source Languages"
              placeholder="Search source languages..."
              options={LANGUAGES}
              value={sourceLanguages}
              onChange={setSourceLanguages}
              multiSelect={true}
              disabled={isSearching || isGenerating}
            />

            <AdvancedSearchableDropdown
              label="Countries"
              placeholder="Search countries..."
              options={COUNTRIES}
              value={countries}
              onChange={setCountries}
              multiSelect={true}
              disabled={isSearching || isGenerating}
            />
          </div>
        </div>

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

      {/* Rest of the component remains the same... */}
      {searchResults.length > 0 && (
        <div className="atm-news-search-results">
          <div className="atm-results-header">
            <h3>News Search Results for "{searchQuery}"</h3>
            <p>
              Found {totalResults} recent articles
              {articleLanguage && ` (generating in ${articleLanguage})`}
              {sourceLanguages.length > 0 &&
                ` from sources in: ${sourceLanguages.join(", ")}`}
              {countries.length > 0 && ` in: ${countries.join(", ")}`}
            </p>
          </div>

          <div className="atm-news-results-grid">
            {searchResults.map((article, index) => (
              <div
                key={index}
                className={`atm-news-item-card 
            ${generatingIndex === index ? "atm-news-item-generating" : ""} 
            ${article.is_used ? "atm-news-item-used" : ""}`}
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
                          <path
                            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 
                      1.1.9 2 2 2h14c1.1 0 2-.9 
                      2-2V5c0-1.1-.9-2-2-2zM9 
                      17H7v-7h2v7zm4 0h-2V7h2v10zm4 
                      0h-2v-4h2v4z"
                          />
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

                {/* Badge for used articles */}
                {article.is_used && (
                  <div className="atm-used-article-badge">Already Used</div>
                )}

                <div className="atm-news-actions">
                  <button
                    className="atm-generate-article-btn"
                    onClick={() => handleGenerateFromSource(article, index)}
                    disabled={isGenerating || article.is_used}
                  >
                    {article.is_used ? (
                      "Already Used"
                    ) : generatingIndex === index ? (
                      <>
                        <Spinner />
                        Generating...
                      </>
                    ) : (
                      "Generate Article"
                    )}
                  </button>

                  {/* Only show checkbox for unused articles */}
                  {!article.is_used && (
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
                  )}
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
