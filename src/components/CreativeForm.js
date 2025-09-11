// src/components/CreativeForm.js
import { useState, useRef, useEffect } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
  TextareaControl,
  CheckboxControl,
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

const updateEditorContent = (title, markdownContent, subtitle) => {
  const isBlockEditor = document.body.classList.contains("block-editor-page");

  if (isBlockEditor) {
    // Set the title
    wp.data.dispatch("core/editor").editPost({ title });

    // Convert markdown to HTML first
    const htmlContent = window.marked
      ? window.marked.parse(markdownContent)
      : markdownContent;

    // Clear existing blocks
    const currentBlocks = wp.data.select("core/block-editor").getBlocks();
    if (currentBlocks.length > 0) {
      const clientIds = currentBlocks.map((block) => block.clientId);
      wp.data.dispatch("core/block-editor").removeBlocks(clientIds);
    }

    // Convert HTML to proper WordPress blocks
    const blocks = htmlToGutenbergBlocks(htmlContent);
    wp.data.dispatch("core/block-editor").insertBlocks(blocks);
  } else {
    // Classic editor fallback
    const htmlContent = window.marked
      ? window.marked.parse(markdownContent)
      : markdownContent;
    jQuery("#title").val(title);
    jQuery("#title-prompt-text").hide();
    jQuery("#title").trigger("blur");
    if (window.tinymce && window.tinymce.get("content")) {
      window.tinymce.get("content").setContent(htmlContent);
    } else {
      jQuery("#content").val(htmlContent);
    }
  }

  // Handle subtitle (same as before)
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

// New function to convert HTML to Gutenberg blocks
const htmlToGutenbergBlocks = (htmlContent) => {
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = htmlContent;
  const blocks = [];

  // Process each child element
  Array.from(tempDiv.children).forEach((element) => {
    const tagName = element.tagName.toLowerCase();

    switch (tagName) {
      case "h1":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 1,
          })
        );
        break;
      case "h2":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 2,
          })
        );
        break;
      case "h3":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 3,
          })
        );
        break;
      case "h4":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 4,
          })
        );
        break;
      case "h5":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 5,
          })
        );
        break;
      case "h6":
        blocks.push(
          wp.blocks.createBlock("core/heading", {
            content: element.innerHTML,
            level: 6,
          })
        );
        break;
      case "ul":
        blocks.push(
          wp.blocks.createBlock("core/list", {
            values: element.outerHTML,
          })
        );
        break;
      case "ol":
        blocks.push(
          wp.blocks.createBlock("core/list", {
            ordered: true,
            values: element.outerHTML,
          })
        );
        break;
      case "blockquote":
        blocks.push(
          wp.blocks.createBlock("core/quote", {
            value: element.innerHTML,
          })
        );
        break;
      case "p":
      default:
        // Handle paragraphs and any other content
        if (element.innerHTML.trim()) {
          blocks.push(
            wp.blocks.createBlock("core/paragraph", {
              content: element.innerHTML,
            })
          );
        }
        break;
    }
  });

  return blocks;
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

  const { savePost } = useDispatch("core/editor");
  const isSaving = useSelect((select) => select("core/editor").isSavingPost());

  // Custom dropdown component with proper width matching
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
  useEffect(() => {
    if (!writingStyleLabel && styleOptions.length > 0) {
      const defaultStyle = styleOptions.find(
        (option) => option.value === writingStyle
      );
      if (defaultStyle) {
        setWritingStyleLabel(defaultStyle.label);
      }
    }
  }, [styleOptions, writingStyle, writingStyleLabel]);

  const handleGenerate = async () => {
    setIsLoading(true);
    setStatusMessage("");

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

        if (!titleResponse.success) {
          throw new Error(titleResponse.data);
        }

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

      if (!contentResponse.success) {
        throw new Error(contentResponse.data);
      }

      console.log("ATM Debug - Response data:", contentResponse.data);
      console.log(
        "ATM Debug - Subtitle received:",
        contentResponse.data.subtitle
      );

      updateEditorContent(
        finalTitle,
        contentResponse.data.article_content,
        contentResponse.data.subtitle || ""
      );
      setStatusMessage("✅ Article content inserted!");

      if (contentResponse.data.subtitle) {
        setStatusMessage(
          "✅ Article inserted! Saving post to apply subtitle..."
        );
        await savePost();
        setStatusMessage("✅ Article and subtitle saved!");
      }

      if (generateImage) {
        setStatusMessage("Saving post...");
        await savePost();

        setStatusMessage("Generating featured image...");
        const imageResponse = await callAjax("generate_featured_image", {
          post_id: postId,
          prompt: "",
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
        label="Keyword"
        placeholder="e.g., AI in digital marketing"
        value={keyword}
        onChange={setKeyword}
        disabled={isLoading || isSaving}
        help="The main topic or keyword for your article"
      />

      <TextControl
        label="Article Title (Optional)"
        placeholder="Leave empty to auto-generate"
        value={title}
        onChange={setTitle}
        disabled={isLoading || isSaving}
        help="Provide a specific title or let AI generate one from your keyword"
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

      <CustomDropdown
        label="Word Count"
        text={wordCountLabel}
        options={lengthOptions}
        onChange={(option) => {
          setWordCount(option.value);
          setWordCountLabel(option.label);
        }}
        disabled={isLoading || isSaving}
      />

      <TextareaControl
        label="Custom Prompt (Optional)"
        placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will be used instead."
        value={customPrompt}
        onChange={setCustomPrompt}
        rows={6}
        disabled={isLoading || isSaving}
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
        disabled={isLoading || isSaving || (!keyword && !title)}
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
                d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"
                fill="currentColor"
              />
            </svg>
            Generate Creative Article
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

export default CreativeForm;
