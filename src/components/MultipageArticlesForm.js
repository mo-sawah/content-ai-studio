// src/components/MultipageArticlesForm.js
import { useState } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
  TextareaControl,
  CheckboxControl,
  RangeControl,
  ToggleControl,
} from "@wordpress/components";
import CustomDropdown from "./common/CustomDropdown";
import CustomSpinner from "./common/CustomSpinner";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const updateEditorContent = (title, markdownContent) => {
  const isBlockEditor = !!wp.data.select("core/block-editor");
  const htmlContent = window.marked
    ? window.marked.parse(markdownContent)
    : markdownContent;

  if (isBlockEditor) {
    wp.data.dispatch("core/editor").editPost({ title });
    const blocks = wp.blocks.parse(htmlContent);
    wp.data.dispatch("core/block-editor").resetBlocks(blocks);
  } else {
    jQuery("#title").val(title).trigger("blur");
    if (window.tinymce?.get("content")) {
      window.tinymce.get("content").setContent(htmlContent);
    } else {
      jQuery("#content").val(htmlContent);
    }
  }
};

function MultipageArticlesForm({ setActiveView }) {
  // Receive setActiveView prop
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [keyword, setKeyword] = useState("");
  const [title, setTitle] = useState("");
  const [writingStyle, setWritingStyle] = useState("default_seo");
  const [writingStyleLabel, setWritingStyleLabel] = useState(
    "Standard / SEO-Optimized"
  );
  const [articleModel, setArticleModel] = useState("");
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [pageCount, setPageCount] = useState(3);
  const [wordsPerPage, setWordsPerPage] = useState(800);
  const [customPrompt, setCustomPrompt] = useState("");
  const [generateImage, setGenerateImage] = useState(false);
  const [enableWebSearch, setEnableWebSearch] = useState(true);
  const [includeSubheadlines, setIncludeSubheadlines] = useState(true);
  const [navigationStyle, setNavigationStyle] = useState("modern");
  const [navigationStyleLabel, setNavigationStyleLabel] =
    useState("Modern Navigation");

  const [generatedPages, setGeneratedPages] = useState([]);

  const { savePost } = useDispatch("core/editor");
  const isSaving = useSelect((select) => select("core/editor").isSavingPost());

  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...Object.entries(atm_studio_data.article_models).map(([value, label]) => ({
      label,
      value,
    })),
  ];

  const styleOptions = Object.entries(atm_studio_data.writing_styles).map(
    ([value, { label }]) => ({ label, value })
  );

  const navigationOptions = [
    { label: "Modern Navigation", value: "modern" },
    { label: "Classic Pagination", value: "classic" },
  ];

  const handleGenerate = async () => {
    setIsLoading(true);
    setStatusMessage("");
    setGeneratedPages([]);

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");
    const topic = title || keyword;

    if (!topic) {
      alert("Please provide a keyword or an article title.");
      setIsLoading(false);
      return;
    }

    try {
      let finalTitle = title;

      if (!finalTitle && keyword) {
        setStatusMessage("Generating compelling title...");
        const titleResponse = await callAjax("generate_multipage_title", {
          keyword,
          model: articleModel,
          page_count: pageCount,
        });
        if (!titleResponse.success) throw new Error(titleResponse.data);
        finalTitle = titleResponse.data.article_title;
        setTitle(finalTitle);
      }

      setStatusMessage("Planning article structure...");
      const outlineResponse = await callAjax("generate_multipage_outline", {
        post_id: postId,
        article_title: finalTitle,
        keyword: keyword,
        page_count: pageCount,
        words_per_page: wordsPerPage,
        model: articleModel,
        writing_style: writingStyle,
        include_subheadlines: includeSubheadlines,
        enable_web_search: enableWebSearch,
      });
      if (!outlineResponse.success) throw new Error(outlineResponse.data);

      const outline = outlineResponse.data.outline;
      const pages = [];

      for (let i = 0; i < pageCount; i++) {
        setStatusMessage(`Writing page ${i + 1} of ${pageCount}...`);
        const pageResponse = await callAjax("generate_multipage_content", {
          post_id: postId,
          article_title: finalTitle,
          page_number: i + 1,
          page_outline: outline.pages[i],
          total_pages: pageCount,
          words_per_page: wordsPerPage,
          model: articleModel,
          writing_style: writingStyle,
          custom_prompt: customPrompt,
          enable_web_search: enableWebSearch,
          include_subheadlines: includeSubheadlines,
        });
        if (!pageResponse.success) throw new Error(pageResponse.data);
        pages.push({
          title: outline.pages[i].title,
          content: pageResponse.data.page_content,
          slug: outline.pages[i].slug,
        });
      }

      setGeneratedPages(pages);
      setStatusMessage("Creating posts and navigation...");

      const multipageResponse = await callAjax("create_multipage_article", {
        post_id: postId,
        main_title: finalTitle,
        pages: pages,
        navigation_style: navigationStyle,
      });
      if (!multipageResponse.success) throw new Error(multipageResponse.data);

      updateEditorContent(finalTitle, multipageResponse.data.main_content);
      setStatusMessage("✅ Multipage article created successfully!");

      if (generateImage) {
        setStatusMessage(
          "✅ Multipage article created! Saving post to generate image..."
        );
        await savePost();
        setStatusMessage("Generating featured image...");
        const imageResponse = await callAjax("generate_featured_image", {
          post_id: postId,
          prompt: finalTitle,
        });
        if (!imageResponse.success) {
          throw new Error(
            "Article created, but image failed: " + imageResponse.data
          );
        }
        setStatusMessage("✅ All done! Reloading to show image...");
        setTimeout(() => window.location.reload(), 1500);
      }
    } catch (error) {
      console.error("Generation error:", error);
      setStatusMessage(`❌ Generation failed: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    // --- NEW: Added wrapper and header ---
    <div className="atm-generator-view">
      <div className="atm-view-header">
        <button
          className="atm-back-btn"
          onClick={() => setActiveView("hub")}
          disabled={isLoading || isSaving}
        >
          <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M15 18L9 12L15 6"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </button>
        <h3>Generate Multipage Article</h3>
      </div>
      <div className="atm-form-container">
        <div className="atm-form-section">
          <h4 className="atm-section-title">Content Configuration</h4>
          <TextControl
            label="Keyword"
            placeholder="e.g., The future of renewable energy"
            value={keyword}
            onChange={setKeyword}
            disabled={isLoading || isSaving}
            help="The main topic for your multipage article."
          />
          <TextControl
            label="Article Title (Optional)"
            placeholder="Leave empty to auto-generate a title"
            value={title}
            onChange={setTitle}
            disabled={isLoading || isSaving}
            help="Provide a specific title, or let AI create one from your keyword."
          />
          <div className="atm-grid-2">
            <CustomDropdown
              label="AI Model"
              text={articleModelLabel}
              options={modelOptions}
              onChange={(option) => {
                setArticleModel(option.value);
                setArticleModelLabel(option.label);
              }}
              disabled={isLoading || isSaving}
            />
            <CustomDropdown
              label="Writing Style"
              text={writingStyleLabel}
              options={styleOptions}
              onChange={(option) => {
                setWritingStyle(option.value);
                setWritingStyleLabel(option.label);
              }}
              disabled={isLoading || isSaving}
            />
          </div>
        </div>

        <div className="atm-form-section">
          <h4 className="atm-section-title">Multipage Structure</h4>
          <div className="atm-grid-2">
            <RangeControl
              label={`Number of Pages: ${pageCount}`}
              value={pageCount}
              onChange={setPageCount}
              min={2}
              max={10}
              disabled={isLoading || isSaving}
            />
            <RangeControl
              label={`Words per Page: ~${wordsPerPage}`}
              value={wordsPerPage}
              onChange={setWordsPerPage}
              min={300}
              max={1500}
              step={50}
              disabled={isLoading || isSaving}
            />
          </div>
          <CustomDropdown
            label="Frontend Navigation Style"
            text={navigationStyleLabel}
            options={navigationOptions}
            onChange={(option) => {
              setNavigationStyle(option.value);
              setNavigationStyleLabel(option.label);
            }}
            disabled={isLoading || isSaving}
          />
        </div>

        <div className="atm-form-section">
          <h4 className="atm-section-title">Advanced Options</h4>
          <div className="atm-grid-2">
            <ToggleControl
              label="Enable Web Search"
              checked={enableWebSearch}
              onChange={setEnableWebSearch}
              disabled={isLoading || isSaving}
              help="Use real-time data for accuracy."
            />
            <ToggleControl
              label="Include Subheadlines"
              checked={includeSubheadlines}
              onChange={setIncludeSubheadlines}
              disabled={isLoading || isSaving}
              help="Add H2/H3 tags for structure."
            />
          </div>
          <TextareaControl
            label="Custom Prompt (Optional)"
            placeholder="Add specific instructions here..."
            value={customPrompt}
            onChange={setCustomPrompt}
            rows={4}
            disabled={isLoading || isSaving}
          />
          <CheckboxControl
            label="Also generate a featured image for the main article"
            checked={generateImage}
            onChange={setGenerateImage}
            disabled={isLoading || isSaving}
          />
        </div>

        <div className="atm-form-actions">
          <Button
            isPrimary
            onClick={handleGenerate}
            disabled={isLoading || isSaving || (!keyword && !title)}
          >
            {isLoading || isSaving ? (
              <>
                <CustomSpinner /> Generating...
              </>
            ) : (
              "Generate Multipage Article"
            )}
          </Button>
        </div>
        {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
      </div>
    </div>
  );
}

export default MultipageArticlesForm;
