// src/components/LiveNewsForm.js
import { useState, useRef } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
  CheckboxControl,
  Spinner,
} from "@wordpress/components";
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

function LiveNewsForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [keyword, setKeyword] = useState("");
  const [forceFresh, setForceFresh] = useState(false);
  const [generateImage, setGenerateImage] = useState(false);
  const [newsCategories, setNewsCategories] = useState([]);
  const [generatingCategory, setGeneratingCategory] = useState(null);

  const { savePost } = useDispatch("core/editor");
  const isSaving = useSelect((select) => select("core/editor").isSavingPost());

  const handleSearch = async () => {
    if (!keyword.trim()) {
      alert("Please enter a keyword to search for news.");
      return;
    }

    setIsSearching(true);
    setStatusMessage("Searching for latest news...");
    setNewsCategories([]);

    try {
      const response = await callAjax("search_live_news", {
        keyword,
        force_fresh: forceFresh,
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      setNewsCategories(response.data.categories || []);
      setStatusMessage(
        `Found ${response.data.categories?.length || 0} news categories`
      );
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setNewsCategories([]);
    } finally {
      setIsSearching(false);
    }
  };

  const handleGenerateFromCategory = async (category, categoryIndex) => {
    setGeneratingCategory(categoryIndex);
    setIsLoading(true);
    setStatusMessage("Generating article...");

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      // Step 1: Generate article content only (no image)
      const response = await callAjax("generate_article_from_live_news", {
        post_id: postId,
        keyword,
        category_title: category.title,
        category_sources: category.articles,
        generate_image: false, // Always set to false - we'll handle image separately
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

      setStatusMessage("✅ Article generated successfully!");

      // Step 3: Save the post if we have a subtitle
      if (response.data.subtitle) {
        setStatusMessage(
          "✅ Article inserted! Saving post to apply subtitle..."
        );
        await savePost();
        setStatusMessage("✅ Article and subtitle saved!");
      }

      // Step 4: Generate featured image if requested (separate from article generation)
      if (generateImage) {
        setStatusMessage("Saving post...");
        await savePost();

        setStatusMessage("Generating featured image...");
        const imageResponse = await callAjax("generate_featured_image", {
          post_id: postId,
          prompt: "", // Empty prompt - use default just like CreativeForm
        });

        if (!imageResponse.success) {
          alert(
            "Article was generated and saved, but the image failed: " +
              imageResponse.data
          );
          setStatusMessage("✅ Article generated! (Image generation failed)");
        } else {
          setStatusMessage("✅ All done! Featured image generated.");
        }
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      setGeneratingCategory(null);
    }
  };

  const NewsArticleItem = ({ article, index }) => (
    <div className="atm-live-news-item">
      {article.thumbnail && (
        <div className="atm-news-thumbnail">
          <img src={article.thumbnail} alt={article.title} />
        </div>
      )}
      <div className="atm-news-content">
        <h4 className="atm-news-title">{article.title}</h4>
        <div className="atm-news-meta">
          <span className="atm-news-source">{article.source}</span>
          <span className="atm-news-date">{article.date}</span>
        </div>
        {article.summary && (
          <p className="atm-news-summary">{article.summary}</p>
        )}
      </div>
    </div>
  );

  const NewsCategoryBlock = ({ category, categoryIndex }) => (
    <div className="atm-news-category-block">
      <div className="atm-category-header">
        <h3 className="atm-category-title">{category.title}</h3>
        <span className="atm-article-count">
          {category.articles?.length || 0} articles
        </span>
      </div>

      <div className="atm-news-grid">
        {category.articles?.map((article, index) => (
          <NewsArticleItem key={index} article={article} index={index} />
        ))}
      </div>

      <div className="atm-category-actions">
        <Button
          isPrimary
          onClick={() => handleGenerateFromCategory(category, categoryIndex)}
          disabled={isLoading || isSaving}
          className="atm-generate-from-category"
        >
          {generatingCategory === categoryIndex ? (
            <>
              <CustomSpinner />
              Generating...
            </>
          ) : (
            <>
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"
                  fill="currentColor"
                />
              </svg>
              Generate Article from This Topic
            </>
          )}
        </Button>
      </div>
    </div>
  );

  return (
    <div className="atm-form-container">
      <div className="atm-live-news-search">
        <TextControl
          label="Search Keyword"
          value={keyword}
          onChange={setKeyword}
          placeholder="e.g., Donald Trump, climate change, artificial intelligence"
          disabled={isSearching || isLoading}
          help="Enter a keyword to search for the latest news"
        />

        <CheckboxControl
          label="Force fresh search (bypass 3-hour cache)"
          checked={forceFresh}
          onChange={setForceFresh}
          disabled={isSearching || isLoading}
        />

        <CheckboxControl
          label="Also generate a featured image"
          checked={generateImage}
          onChange={setGenerateImage}
          disabled={isSearching || isLoading}
        />

        <Button
          isPrimary
          onClick={handleSearch}
          disabled={isSearching || isLoading || !keyword.trim()}
          className="atm-search-live-news"
        >
          {isSearching ? (
            <>
              <CustomSpinner />
              Searching...
            </>
          ) : (
            <>
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
              Search Live News
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

      {newsCategories.length > 0 && (
        <div className="atm-live-news-results">
          <div className="atm-results-header">
            <h3>Latest News about "{keyword}"</h3>
            <p>
              Found {newsCategories.length} topic categories with related
              articles
            </p>
          </div>

          <div className="atm-news-categories">
            {newsCategories.map((category, index) => (
              <NewsCategoryBlock
                key={index}
                category={category}
                categoryIndex={index}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default LiveNewsForm;
