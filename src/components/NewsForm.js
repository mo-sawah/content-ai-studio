// src/components/NewsForm.js
import { useState, useRef } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import {
  Button,
  TextControl,
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

  // ADD SUBTITLE HANDLING
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

function NewsForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [topic, setTopic] = useState("");
  const [newsSource, setNewsSource] = useState("newsapi");
  const [newsSourceLabel, setNewsSourceLabel] = useState("NewsAPI.org");
  const [forceFresh, setForceFresh] = useState(false);
  const [generateImage, setGenerateImage] = useState(false);
  const [imagePrompt, setImagePrompt] = useState("");

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

  const newsSourceOptions = [
    { label: "NewsAPI.org", value: "newsapi" },
    { label: "GNews.io", value: "gnews" },
    { label: "The Guardian", value: "guardian" },
  ];

  const handleGenerate = async () => {
    if (!topic) {
      alert("Please enter a topic for the news article.");
      return;
    }
    setIsLoading(true);
    setStatusMessage("Searching for latest news...");
    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      const response = await callAjax("generate_news_article", {
        topic,
        news_source: newsSource,
        force_fresh: forceFresh,
      });

      if (!response.success) throw new Error(response.data);

      setStatusMessage("Writing article...");
      updateEditorContent(
        response.data.article_title,
        response.data.article_content,
        response.data.subtitle || ""
      );
      setStatusMessage("✅ News article inserted!");

      // ADD THIS
      if (response.data.subtitle) {
        setStatusMessage(
          "✅ News article inserted! Saving post to apply subtitle..."
        );
        await savePost();
        setStatusMessage("✅ News article and subtitle saved!");
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
          setStatusMessage("✅ All done! Reloading to show image...");
          setTimeout(() => window.location.reload(), 1500);
          return;
        }
      }

      setIsLoading(false);
      setTimeout(() => setStatusMessage(""), 3000);
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setIsLoading(false);
    }
  };

  return (
    <div className="atm-form-container">
      <TextControl
        label="News Topic"
        value={topic}
        onChange={setTopic}
        placeholder="e.g., recent AI developments"
        disabled={isLoading || isSaving}
      />
      <CustomDropdown
        label="News Source"
        text={newsSourceLabel}
        options={newsSourceOptions}
        onChange={(option) => {
          setNewsSource(option.value);
          setNewsSourceLabel(option.label);
        }}
        disabled={isLoading || isSaving}
      />
      <CheckboxControl
        label="Force fresh search (bypasses cache)"
        checked={forceFresh}
        onChange={setForceFresh}
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

export default NewsForm;
