// src/components/KeyTakeawaysGenerator.js
import { useState } from "@wordpress/element";
import { Button, TextareaControl } from "@wordpress/components";
import CustomDropdown from "./common/CustomDropdown";
import CustomSpinner from "./common/CustomSpinner";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const getEditorContent = () =>
  wp.data.select("core/editor").getEditedPostContent();

function KeyTakeawaysGenerator({ setActiveView }) {
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [takeaways, setTakeaways] = useState("");
  const [articleModel, setArticleModel] = useState("");
  const [articleModelLabel, setArticleModelLabel] =
    useState("Use Default Model");

  // --- NEW: State for theme selection ---
  const [theme, setTheme] = useState("dark");
  const [themeLabel, setThemeLabel] = useState("Dark");

  const modelOptions = [
    { label: "Use Default Model", value: "" },
    ...Object.entries(atm_studio_data.content_models).map(([value, label]) => ({
      label,
      value,
    })),
  ];

  // --- NEW: Options for the theme dropdown ---
  const themeOptions = [
    { label: "Dark", value: "dark" },
    { label: "Light", value: "light" },
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
      // --- MODIFIED: Send theme along with takeaways ---
      const response = await callAjax("save_key_takeaways", {
        post_id: postId,
        takeaways: takeaways,
        theme: theme,
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
        {/* --- NEW: Grid layout for dropdowns --- */}
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
            label="Theme"
            text={themeLabel}
            options={themeOptions}
            onChange={(option) => {
              setTheme(option.value);
              setThemeLabel(option.label);
            }}
            disabled={isLoading || isSaving}
          />
        </div>
        {/* --- END NEW --- */}

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
