// src/components/TwitterNewsForm.js
import { useState } from "@wordpress/element";
import {
  Button,
  TextControl,
  Spinner,
  ToggleControl,
} from "@wordpress/components";

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

function TwitterNewsForm() {
  const [isSearching, setIsSearching] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState([]);
  const [selectedTweets, setSelectedTweets] = useState(new Set());
  const [articleLanguage, setArticleLanguage] = useState("English");

  // Filter states
  const [verifiedOnly, setVerifiedOnly] = useState(true);
  const [credibleSourcesOnly, setCredibleSourcesOnly] = useState(true);
  const [minFollowers, setMinFollowers] = useState(10000);

  const handleSearch = async () => {
    if (!searchQuery.trim()) {
      alert("Please enter a search term.");
      return;
    }

    setIsSearching(true);
    setStatusMessage("Searching Twitter for credible news sources...");
    setSearchResults([]);
    setSelectedTweets(new Set());

    try {
      const response = await callAjax("search_twitter_news", {
        keyword: searchQuery,
        verified_only: verifiedOnly,
        credible_sources_only: credibleSourcesOnly,
        min_followers: minFollowers,
        max_results: 20,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      setSearchResults(response.data.results || []);
      setStatusMessage(
        `Found ${response.data.total || 0} credible tweets about "${searchQuery}"`
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setSearchResults([]);
    } finally {
      setIsSearching(false);
    }
  };

  const handleTweetSelect = (tweetId) => {
    const newSelected = new Set(selectedTweets);
    if (newSelected.has(tweetId)) {
      newSelected.delete(tweetId);
    } else {
      newSelected.add(tweetId);
    }
    setSelectedTweets(newSelected);
  };

  const handleGenerateArticle = async () => {
    if (selectedTweets.size === 0) {
      alert("Please select at least one tweet to generate an article.");
      return;
    }

    setIsGenerating(true);
    setStatusMessage("Generating article from selected tweets...");

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      const selectedTweetData = searchResults.filter((tweet) =>
        selectedTweets.has(tweet.id)
      );

      const response = await callAjax("generate_article_from_tweets", {
        post_id: postId,
        keyword: searchQuery,
        selected_tweets: selectedTweetData,
        article_language: articleLanguage,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      updateEditorContent(
        response.data.article_title,
        response.data.article_content,
        response.data.subtitle || ""
      );

      setStatusMessage(
        `Article generated successfully from ${selectedTweets.size} tweets!`
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsGenerating(false);
    }
  };

  const getCredibilityBadge = (tweet) => {
    if (tweet.is_credible_source) {
      return (
        <span className="atm-credibility-badge credible-source">
          📰 Credible Source
        </span>
      );
    }
    if (tweet.user.verified) {
      return <span className="atm-credibility-badge verified">✓ Verified</span>;
    }
    return null;
  };

  const formatNumber = (num) => {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + "M";
    }
    if (num >= 1000) {
      return (num / 1000).toFixed(1) + "K";
    }
    return num.toString();
  };

  return (
    <div className="atm-form-container">
      <div className="atm-twitter-news-form">
        <div className="atm-twitter-header">
          <h3>🐦 Twitter/X News Search</h3>
          <p>
            Search for breaking news and credible information from verified
            Twitter sources
          </p>
        </div>

        <TextControl
          label="Search Twitter for News"
          value={searchQuery}
          onChange={setSearchQuery}
          placeholder="e.g., breaking news, Trump, climate change"
          disabled={isSearching || isGenerating}
          help="Enter keywords to search for news on Twitter/X"
        />

        {/* Search Filters */}
        <div className="atm-twitter-filters">
          <h4>Search Filters</h4>
          <div className="atm-filters-grid">
            <div className="atm-filter-group">
              <label htmlFor="verified-only">Account Type:</label>
              <select
                id="verified-only"
                value={verifiedOnly ? "verified" : "all"}
                onChange={(e) => setVerifiedOnly(e.target.value === "verified")}
                disabled={isSearching || isGenerating}
              >
                <option value="all">All accounts</option>
                <option value="verified">Verified only</option>
              </select>
            </div>

            <div className="atm-filter-group">
              <label htmlFor="credible-sources">Source Type:</label>
              <select
                id="credible-sources"
                value={credibleSourcesOnly ? "credible" : "all"}
                onChange={(e) =>
                  setCredibleSourcesOnly(e.target.value === "credible")
                }
                disabled={isSearching || isGenerating}
              >
                <option value="all">All sources</option>
                <option value="credible">Credible news only</option>
              </select>
            </div>

            <div className="atm-filter-group">
              <label htmlFor="min-followers">Minimum followers:</label>
              <select
                id="min-followers"
                value={minFollowers}
                onChange={(e) => setMinFollowers(parseInt(e.target.value))}
                disabled={isSearching || isGenerating}
              >
                <option value={1000}>1K+ followers</option>
                <option value={10000}>10K+ followers</option>
                <option value={50000}>50K+ followers</option>
                <option value={100000}>100K+ followers</option>
                <option value={1000000}>1M+ followers</option>
              </select>
            </div>

            <div className="atm-filter-group">
              <label htmlFor="article-language">Article Language:</label>
              <select
                id="article-language"
                value={articleLanguage}
                onChange={(e) => setArticleLanguage(e.target.value)}
                disabled={isSearching || isGenerating}
              >
                <option value="English">English</option>
                <option value="Spanish">Spanish</option>
                <option value="French">French</option>
                <option value="German">German</option>
                <option value="Portuguese">Portuguese</option>
                <option value="Italian">Italian</option>
                <option value="Arabic">Arabic</option>
              </select>
            </div>
          </div>
        </div>

        <div className="atm-twitter-actions">
          <Button
            isPrimary
            onClick={handleSearch}
            disabled={isSearching || isGenerating || !searchQuery.trim()}
            className="atm-search-twitter-btn"
          >
            {isSearching ? (
              <>
                <Spinner />
                Searching Twitter...
              </>
            ) : (
              <>
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                </svg>
                Search Twitter
              </>
            )}
          </Button>

          {selectedTweets.size > 0 && (
            <Button
              isSecondary
              onClick={handleGenerateArticle}
              disabled={isGenerating || selectedTweets.size === 0}
              className="atm-generate-twitter-article-btn"
            >
              {isGenerating ? (
                <>
                  <Spinner />
                  Generating Article...
                </>
              ) : (
                `Generate Article from ${selectedTweets.size} Selected Tweets`
              )}
            </Button>
          )}
        </div>
      </div>

      {statusMessage && (
        <p
          className={`atm-status-message ${
            statusMessage.includes("Error") ? "error" : "info"
          }`}
        >
          {statusMessage}
        </p>
      )}

      {/* Tweet Results */}
      {searchResults.length > 0 && (
        <div className="atm-twitter-results">
          <div className="atm-results-header">
            <h3>Twitter Results for "{searchQuery}"</h3>
            <p>
              Select tweets to include in your article. Higher credibility
              scores appear first.
            </p>
          </div>

          <div className="atm-tweets-grid">
            {searchResults.map((tweet) => (
              <div
                key={tweet.id}
                className={`atm-tweet-card ${
                  selectedTweets.has(tweet.id) ? "selected" : ""
                }`}
              >
                <div className="atm-tweet-header">
                  <div className="atm-tweet-user">
                    <img
                      src={tweet.user.profile_image}
                      alt={tweet.user.name}
                      className="atm-user-avatar"
                    />
                    <div className="atm-user-info">
                      <div className="atm-user-name">
                        {tweet.user.name}
                        {tweet.user.verified && (
                          <span className="atm-verified">✓</span>
                        )}
                      </div>
                      <div className="atm-user-handle">
                        @{tweet.user.screen_name}
                      </div>
                      <div className="atm-user-followers">
                        {formatNumber(tweet.user.followers)} followers
                      </div>
                    </div>
                  </div>
                  {getCredibilityBadge(tweet)}
                </div>

                <div className="atm-tweet-content">
                  <p>{tweet.text}</p>
                  {tweet.media.length > 0 && (
                    <div className="atm-tweet-media">
                      {tweet.media.map((media, index) => (
                        <img
                          key={index}
                          src={media.url}
                          alt="Tweet media"
                          className="atm-tweet-image"
                        />
                      ))}
                    </div>
                  )}
                </div>

                <div className="atm-tweet-meta">
                  <div className="atm-tweet-date">{tweet.formatted_date}</div>
                  <div className="atm-tweet-engagement">
                    <span>🔄 {formatNumber(tweet.metrics.retweets)}</span>
                    <span>❤️ {formatNumber(tweet.metrics.likes)}</span>
                    {tweet.credibility_score && (
                      <span className="atm-credibility-score">
                        📊 {tweet.credibility_score}/100
                      </span>
                    )}
                  </div>
                </div>

                <div className="atm-tweet-actions">
                  <input
                    type="checkbox"
                    id={`tweet-${tweet.id}`}
                    checked={selectedTweets.has(tweet.id)}
                    onChange={() => handleTweetSelect(tweet.id)}
                    disabled={isGenerating}
                  />
                  <label htmlFor={`tweet-${tweet.id}`}>
                    Include in article
                  </label>

                  {tweet.urls.length > 0 && (
                    <div className="atm-tweet-links">
                      {tweet.urls.map((url, index) => (
                        <a
                          key={index}
                          href={url.expanded_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="atm-tweet-link"
                        >
                          🔗 {url.display_url}
                        </a>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {searchResults.length === 0 && searchQuery && !isSearching && (
        <div className="atm-twitter-empty-state">
          <svg
            width="64"
            height="64"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
          >
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
          </svg>
          <h3>No credible tweets found</h3>
          <p>
            Try different keywords or adjust your filters to find more results.
          </p>
        </div>
      )}
    </div>
  );
}

export default TwitterNewsForm;
