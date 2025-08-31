// src/components/KeyTakeawaysGenerator.js
import { useState } from "@wordpress/element";
import { Button, TextareaControl } from "@wordpress/components";
import CustomDropdown from "./common/CustomDropdown";
import CustomSpinner from "./common/CustomSpinner";

// Helper function for AJAX calls
const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: {
      action,
      nonce: atm_studio_data.nonce,
      ...data,
    },
  });

// Helper function to get content from the editor
const getEditorContent = () => {
  const isBlockEditor = !!wp.data.select("core/block-editor");
  if (isBlockEditor) {
    return wp.data.select("core/editor").getEditedPostContent();
  }
  // Fallback for Classic Editor
  if (typeof tinymce !== "undefined" && tinymce.get("content")) {
    return tinymce.get("content").getContent();
  }
  return jQuery("#content").val();
};

function KeyTakeawaysGenerator({ setActiveView }) {
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [takeaways, setTakeaways] = useState("");
  const [articleModel, setArticleModel] = useState("");
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");

  // Model options derived from localized data
  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...Object.entries(atm_studio_data.content_models).map(([value, label]) => ({
      label,
      value,
    })),
  ];

  const handleGenerate = async () => {
    const content = getEditorContent();
    if (!content.trim()) {
      alert("Editor content is empty. Please write something first.");
      return;
    }

    setIsLoading(true);
    setStatusMessage("Generating takeaways from post content...");
    setTakeaways("");

    try {
      const response = await callAjax("generate_key_takeaways", {
        content: content,
        model: articleModel,
      });

      if (response.success) {
        setTakeaways(response.data.takeaways);
        setStatusMessage(
          "✅ Takeaways generated successfully. You can edit them below."
        );
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(
        `Error: ${error.message || "An unknown error occurred."}`
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleSave = async () => {
    if (!takeaways.trim()) {
      alert("There are no takeaways to save.");
      return;
    }

    setIsSaving(true);
    setStatusMessage("Saving takeaways...");
    const postId = document
      .getElementById("atm-studio-root")
      .getAttribute("data-post-id");

    try {
      const response = await callAjax("save_key_takeaways", {
        post_id: postId,
        takeaways: takeaways,
      });

      if (response.success) {
        setStatusMessage("✅ Takeaways saved!");
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(
        `Error: ${error.message || "An unknown error occurred."}`
      );
    } finally {
      setIsSaving(false);
    }
  };

  return (
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
        <h3>Key Takeaways</h3>
      </div>

      <div className="atm-form-container">
        <CustomDropdown
          label="AI Model"
          text={articleModelLabel}
          options={modelOptions}
          // --- THIS IS THE FIX ---
          onChange={(option) => {
            setArticleModel(option.value);
            setArticleModelLabel(option.label);
          }}
          disabled={isLoading || isSaving}
        />
        <Button
          isPrimary
          onClick={handleGenerate}
          disabled={isLoading || isSaving}
        >
          {isLoading ? (
            <>
              <CustomSpinner /> Generating...
            </>
          ) : (
            "Generate Takeaways from Post Content"
          )}
        </Button>
        <TextareaControl
          label="Key Takeaways"
          help="One takeaway per line. Edit as needed before saving."
          value={takeaways}
          onChange={setTakeaways}
          rows={8}
          disabled={isLoading || isSaving}
        />
        <Button
          isSecondary
          onClick={handleSave}
          disabled={isLoading || isSaving || !takeaways}
        >
          {isSaving ? (
            <>
              <CustomSpinner /> Saving...
            </>
          ) : (
            "Save Takeaways"
          )}
        </Button>
        {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
      </div>
    </div>
  );
}

export default KeyTakeawaysGenerator;
