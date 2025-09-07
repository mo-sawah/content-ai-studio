// src/components/ListicleForm.js
import { useState, useRef, useEffect } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
  TextareaControl,
  CheckboxControl,
  RangeControl,
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

const updateEditorContent = (title, htmlContent, subtitle) => {
  const isBlockEditor = document.body.classList.contains("block-editor-page");

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
    setTimeout(function () {
      const subtitleField = jQuery('input[name="_bunyad_sub_title"]');
      if (subtitleField.length > 0) {
        subtitleField.val(subtitle);
        subtitleField.trigger("input").trigger("change").trigger("keyup");
      }
    }, 1000);
  }
};

function ListicleForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [topic, setTopic] = useState("");
  const [title, setTitle] = useState("");
  const [itemCount, setItemCount] = useState(10);
  const [category, setCategory] = useState("");
  const [categoryLabel, setCategoryLabel] = useState("Technology");
  const [includePricing, setIncludePricing] = useState(false);
  const [includeRatings, setIncludeRatings] = useState(true);
  const [articleModel, setArticleModel] = useState("");
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [customPrompt, setCustomPrompt] = useState("");
  const [generateImage, setGenerateImage] = useState(false);

  const { savePost } = useDispatch("core/editor");
  const isSaving = useSelect((select) => select("core/editor").isSavingPost());

  // Custom dropdown component
  const CustomDropdown = ({
    label,
    text,
    options,
    onChange,
    disabled,
    helpText,
  }) => {
    const dropdownRef = useRef(null);

    useEffect(() => {
      if (dropdownRef.current) {
        const width = dropdownRef.current.offsetWidth;
        document.documentElement.style.setProperty(
          "--atm-dropdown-width",
          width + "px"
        );
      }
    }, [text]);

    return (
      <div className="atm-dropdown-field" ref={dropdownRef}>
        <label className="atm-dropdown-label">{label}</label>
        <DropdownMenu
          className="atm-custom-dropdown"
          icon={chevronDown}
          text={text}
          controls={options.map((option) => ({
            title: option.label,
            onClick: () => {
              onChange(option);
            },
          }))}
          disabled={disabled}
          popoverProps={{
            className: "atm-popover",
            onMount: () => {
              if (dropdownRef.current) {
                const width = dropdownRef.current.offsetWidth;
                document.documentElement.style.setProperty(
                  "--atm-dropdown-width",
                  width + "px"
                );
              }
            },
          }}
        />
        {helpText && <p className="atm-dropdown-help">{helpText}</p>}
      </div>
    );
  };

  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...Object.entries(atm_studio_data.article_models).map(([value, label]) => ({
      label,
      value,
    })),
  ];

  const categoryOptions = [
    { label: "Technology", value: "technology" },
    { label: "Business", value: "business" },
    { label: "Health & Fitness", value: "health" },
    { label: "Travel", value: "travel" },
    { label: "Food & Cooking", value: "food" },
    { label: "Fashion & Beauty", value: "fashion" },
    { label: "Home & Garden", value: "home" },
    { label: "Entertainment", value: "entertainment" },
    { label: "Education", value: "education" },
    { label: "Finance", value: "finance" },
    { label: "Lifestyle", value: "lifestyle" },
    { label: "Sports", value: "sports" },
  ];

  const handleGenerate = async () => {
    setIsLoading(true);
    setStatusMessage("");

    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    const mainTopic = title || topic;

    if (!mainTopic) {
      alert("Please provide a topic or article title.");
      setIsLoading(false);
      return;
    }

    try {
      let finalTitle = title;

      if (!finalTitle && topic) {
        setStatusMessage("Generating compelling listicle title...");
        const titleResponse = await callAjax("generate_listicle_title", {
          topic,
          item_count: itemCount,
          category,
          model: articleModel,
        });

        if (!titleResponse.success) {
          throw new Error(titleResponse.data);
        }

        finalTitle = titleResponse.data.article_title;
      }

      setStatusMessage("Creating listicle content...");
      const contentResponse = await callAjax("generate_listicle_content", {
        post_id: postId,
        article_title: finalTitle,
        topic: mainTopic,
        item_count: itemCount,
        category,
        include_pricing: includePricing,
        include_ratings: includeRatings,
        model: articleModel,
        custom_prompt: customPrompt,
      });

      if (!contentResponse.success) {
        throw new Error(contentResponse.data);
      }

      updateEditorContent(
        finalTitle,
        contentResponse.data.article_content,
        contentResponse.data.subtitle || ""
      );
      setStatusMessage("✅ Listicle article created successfully!");

      if (contentResponse.data.subtitle) {
        setStatusMessage(
          "✅ Article inserted! Saving post to apply subtitle..."
        );
        await savePost();
        setStatusMessage("✅ Listicle article and subtitle saved!");
      }

      if (generateImage) {
        setStatusMessage("Saving post...");
        await savePost();

        setStatusMessage("Generating featured image...");
        const imageResponse = await callAjax("generate_featured_image", {
          post_id: postId,
          prompt: `A modern, clean illustration representing: ${finalTitle}`,
        });

        if (!imageResponse.success) {
          alert(
            "Article was generated and saved, but the image failed: " +
              imageResponse.data
          );
          setStatusMessage("✅ Listicle generated! (Image generation failed)");
        } else {
          setStatusMessage("✅ All done! Featured image generated.");
        }
      }
    } catch (error) {
      console.error("Generation error:", error);
      alert("Error: " + error.message);
      setStatusMessage("⚠ Generation failed. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-form-container">
      <TextControl
        label="Topic or Theme"
        placeholder="e.g., best productivity apps, top marketing tools"
        value={topic}
        onChange={setTopic}
        disabled={isLoading || isSaving}
        help="The main topic for your listicle (what you want to list)"
      />

      <TextControl
        label="Custom Title (Optional)"
        placeholder="Leave empty to auto-generate"
        value={title}
        onChange={setTitle}
        disabled={isLoading || isSaving}
        help="Provide a specific title or let AI generate one from your topic"
      />

      <div className="atm-grid-2">
        <div>
          <RangeControl
            label={`Number of Items: ${itemCount}`}
            value={itemCount}
            onChange={setItemCount}
            min={5}
            max={25}
            disabled={isLoading || isSaving}
            help="How many items to include in your list"
          />
        </div>

        <CustomDropdown
          label="Category"
          text={categoryLabel}
          options={categoryOptions}
          onChange={(option) => {
            setCategory(option.value);
            setCategoryLabel(option.label);
          }}
          disabled={isLoading || isSaving}
          helpText="Category helps generate more relevant content"
        />
      </div>

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

      <div className="atm-grid-2">
        <CheckboxControl
          label="Include pricing information"
          checked={includePricing}
          onChange={setIncludePricing}
          disabled={isLoading || isSaving}
          help="Add price details when relevant"
        />

        <CheckboxControl
          label="Include ratings/scores"
          checked={includeRatings}
          onChange={setIncludeRatings}
          disabled={isLoading || isSaving}
          help="Add star ratings or numerical scores"
        />
      </div>

      <TextareaControl
        label="Custom Instructions (Optional)"
        placeholder="Add specific requirements, tone, or focus areas..."
        value={customPrompt}
        onChange={setCustomPrompt}
        rows={4}
        disabled={isLoading || isSaving}
        help="Additional instructions to customize the listicle content"
      />

      <CheckboxControl
        label="Also generate a featured image"
        checked={generateImage}
        onChange={setGenerateImage}
        disabled={isLoading || isSaving}
      />

      <Button
        isPrimary
        onClick={handleGenerate}
        disabled={isLoading || isSaving || (!topic && !title)}
      >
        {isLoading || isSaving ? (
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
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h.01M9 12h.01m0 4h.01m3-6h4m-4 4h4m2-5h.01M21 12h.01"
                fill="currentColor"
              />
            </svg>
            Generate Listicle Article
          </>
        )}
      </Button>

      {statusMessage && (
        <p
          className={`atm-status-message ${
            statusMessage.includes("✅")
              ? "success"
              : statusMessage.includes("⚠")
                ? "error"
                : "info"
          }`}
        >
          {statusMessage}
        </p>
      )}
    </div>
  );
}

export default ListicleForm;
