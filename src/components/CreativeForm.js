// src/components/CreativeForm.js
import { useState, useRef } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
  TextareaControl,
  CheckboxControl,
  DropdownMenu,
} from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";
import { chevronDown } from "@wordpress/icons";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });
const updateEditorContent = (title, markdownContent) => {
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
};

function CreativeForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [keyword, setKeyword] = useState("");
  const [title, setTitle] = useState("");
  const [writingStyle, setWritingStyle] = useState("default_seo");
  const [writingStyleLabel, setWritingStyleLabel] = useState("");
  const [articleModel, setArticleModel] = useState("");
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");
  const [wordCount, setWordCount] = useState("");
  const [wordCountLabel, setWordCountLabel] = useState("Default");
  const [customPrompt, setCustomPrompt] = useState("");
  const [generateImage, setGenerateImage] = useState(false);
  const [imagePrompt, setImagePrompt] = useState(""); // <-- ADD THIS

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
            style: {
              "--atm-dropdown-width": dropdownRef.current?.offsetWidth
                ? dropdownRef.current.offsetWidth + "px"
                : "auto",
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

  const styleOptions = Object.entries(atm_studio_data.writing_styles).map(
    ([value, { label }]) => ({ label, value })
  );

  const lengthOptions = [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Standard (~800 words)", value: "800" },
    { label: "Medium (~1200 words)", value: "1200" },
    { label: "Long (~2000 words)", value: "2000" },
  ];

  // Set initial writing style label
  if (!writingStyleLabel && styleOptions.length > 0) {
    const defaultStyle = styleOptions.find(
      (option) => option.value === writingStyle
    );
    if (defaultStyle) {
      setWritingStyleLabel(defaultStyle.label);
    }
  }

  const handleGenerate = async () => {
    setIsLoading(true);
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
        const titleResponse = await callAjax("generate_article_title", {
          keyword,
          model: articleModel,
        });
        if (!titleResponse.success) throw new Error(titleResponse.data);
        finalTitle = titleResponse.data.article_title;
      }

      setStatusMessage("Writing article content...");
      const contentResponse = await callAjax("generate_article_content", {
        post_id: postId,
        article_title: finalTitle,
        model: articleModel,
        writing_style: writingStyle,
        custom_prompt: customPrompt,
        word_count: wordCount,
      });
      if (!contentResponse.success) throw new Error(contentResponse.data);

      updateEditorContent(finalTitle, contentResponse.data.article_content);
      setStatusMessage("✅ Article content inserted!");

      // Replace the subtitle handling section with this enhanced version:
      if (contentResponse.data.subtitle) {
        setStatusMessage("✅ Article content inserted! Setting subtitle...");

        try {
          // Method 1: Update Block Editor meta directly
          if (typeof wp !== "undefined" && wp.data) {
            const currentMeta =
              wp.data.select("core/editor").getEditedPostAttribute("meta") ||
              {};

            wp.data.dispatch("core/editor").editPost({
              meta: {
                ...currentMeta,
                _bunyad_sub_title: contentResponse.data.subtitle,
                _atm_subtitle: contentResponse.data.subtitle, // Backup field
              },
            });

            console.log(
              "ATM: Updated Block Editor meta with subtitle:",
              contentResponse.data.subtitle
            );
          }

          // Method 2: Also try to update any visible form fields (for SmartMag's custom meta box)
          const subtitleInputs = document.querySelectorAll(
            'input[name="_bunyad_sub_title"], input[name*="bunyad_sub_title"]'
          );
          subtitleInputs.forEach((input) => {
            input.value = contentResponse.data.subtitle;
            // Trigger change event to notify SmartMag's scripts
            input.dispatchEvent(new Event("change", { bubbles: true }));
            console.log("ATM: Updated visible subtitle input field");
          });
        } catch (error) {
          console.warn("ATM: Error setting subtitle:", error);
        }

        // Save the post to persist all changes
        await savePost();
        setStatusMessage("✅ Article and subtitle saved!");
      }

      if (generateImage) {
        setStatusMessage("Saving post...");
        await savePost();

        setStatusMessage("Generating featured image...");
        const imageResponse = await callAjax("generate_featured_image", {
          post_id: postId,
          prompt: imagePrompt,
        });

        if (!imageResponse.success) {
          alert(
            "Article was generated and saved, but the image failed: " +
              imageResponse.data
          );
        } else {
          // --- ADD THESE LINES ---
          if (imageResponse.data.generated_prompt) {
            console.log("--- AI-Generated Image Prompt (Creative Article) ---");
            console.log(imageResponse.data.generated_prompt);
          }
          // --- END ---

          setStatusMessage("✅ All done! Reloading to show new image...");
          setTimeout(() => window.location.reload(), 2500);
          return;
        }
      }

      setIsLoading(false);
      setTimeout(() => setStatusMessage(""), 3000);
    } catch (error) {
      const errorMessage = error.message || "An unknown error occurred.";
      setStatusMessage(`Error: ${errorMessage}`);
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-form-container">
      <div className="atm-grid-2">
        <TextControl
          label="Keyword"
          value={keyword}
          onChange={setKeyword}
          placeholder="e.g., AI in digital marketing"
          disabled={isLoading || isSaving}
        />
        <TextControl
          label="or Article Title"
          value={title}
          onChange={setTitle}
          placeholder="e.g., 5 Ways AI is Revolutionizing Marketing"
          disabled={isLoading || isSaving}
        />
      </div>
      <div className="atm-grid-3">
        <CustomDropdown
          label="Article Model"
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
          text={
            writingStyleLabel ||
            (styleOptions[0] ? styleOptions[0].label : "Loading...")
          }
          options={styleOptions}
          onChange={(option) => {
            setWritingStyle(option.value);
            setWritingStyleLabel(option.label);
          }}
          disabled={isLoading || isSaving}
        />
        <CustomDropdown
          label="Article Length"
          text={wordCountLabel}
          options={lengthOptions}
          onChange={(option) => {
            setWordCount(option.value);
            setWordCountLabel(option.label);
          }}
          disabled={isLoading || isSaving}
        />
      </div>
      <TextareaControl
        label="Custom Prompt (Optional)"
        value={customPrompt}
        onChange={setCustomPrompt}
        placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will be used instead."
        rows="6"
        disabled={isLoading || isSaving}
      />
      <CheckboxControl
        label="Also generate a featured image"
        checked={generateImage}
        onChange={(isChecked) => {
          setGenerateImage(isChecked);
          if (isChecked) {
            // Pre-fill the prompt when the box is checked
            const defaultPrompt = `Create a highly photorealistic image that visually represents the following article title in the most accurate and engaging way:\n\n"{{article_title}}"\n\nGuidelines:\n- Style: photorealistic, ultra-realistic, natural lighting\n- Composition: clear, well-framed subject that directly illustrates the article title\n- Avoid: text, logos, watermarks, artistic/cartoon styles\n- Aspect ratio: 16:9 (suitable for a featured article image)\n- Quality: high-resolution, detailed textures, realistic colors`;
            const finalTitle = title || keyword; // Use title if available, fallback to keyword
            setImagePrompt(
              defaultPrompt.replace("{{article_title}}", finalTitle)
            );
          }
        }}
        disabled={isLoading || isSaving}
      />

      {generateImage && (
        <TextareaControl
          label="Featured Image Prompt"
          help="This prompt will be used to generate the image. You can edit it as needed."
          value={imagePrompt}
          onChange={setImagePrompt}
          rows={8}
          disabled={isLoading || isSaving}
        />
      )}

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
          "Generate Creative Article"
        )}
      </Button>
      {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
    </div>
  );
}

export default CreativeForm;
