import { useState, useEffect } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import { Button, TextareaControl } from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

function TakeawaysGenerator({ setActiveView }) {
  const [takeaways, setTakeaways] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  // FIX: Correctly get post ID and content from the editor's state
  const { postId, postContent } = useSelect(
    (select) => ({
      postId: select("core/editor").getCurrentPostId(),
      postContent: select("core/editor").getEditedPostContent(),
    }),
    []
  );

  useEffect(() => {
    const existingTakeaways = atm_studio_data.existing_takeaways || [];
    setTakeaways(existingTakeaways.join("\n"));
  }, []);

  const handleGenerate = async () => {
    setIsLoading(true);
    setStatusMessage("Generating key takeaways...");
    try {
      // FIX: Send the post_id and content to the backend
      const response = await callAjax("generate_takeaways", {
        post_id: postId,
        content: postContent,
      });
      if (response.success) {
        setTakeaways(response.data.takeaways.join("\n"));
        setStatusMessage("✅ Takeaways generated! You can edit them below.");
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSave = async () => {
    setIsSaving(true);
    setStatusMessage("Saving...");
    try {
      const response = await callAjax("save_takeaways", {
        post_id: postId,
        takeaways,
      });
      if (response.success) {
        setStatusMessage("✅ Takeaways saved successfully!");
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsSaving(false);
      setTimeout(() => setStatusMessage(""), 3000);
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
          rows="8"
          disabled={isLoading || isSaving}
        />
        <Button
          isSecondary
          onClick={handleSave}
          disabled={isSaving || isLoading || !takeaways.trim()}
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

export default TakeawaysGenerator;
