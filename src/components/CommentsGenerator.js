import { useState, useMemo } from "@wordpress/element";
import { Button, RangeControl, ToggleControl } from "@wordpress/components";
import CustomDropdown from "./common/CustomDropdown";
import CustomSpinner from "./common/CustomSpinner";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const getEditorPayload = () => {
  const editor = wp.data.select("core/editor");
  return {
    postId: editor.getCurrentPostId(),
    title: editor.getEditedPostAttribute("title") || "",
    content: editor.getEditedPostContent() || "",
  };
};

function CommentsGenerator({ setActiveView }) {
  const [isGenerating, setIsGenerating] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [statusMessage, setStatusMessage] = useState(
    "Generate realistic, human-style comments from your post content."
  );
  const [count, setCount] = useState(7);
  const [threaded, setThreaded] = useState(true);
  const [approveNow, setApproveNow] = useState(false);
  const [model, setModel] = useState("");
  const [modelLabel, setModelLabel] = useState("Use Default Model");
  const [comments, setComments] = useState([]);

  const modelOptions = useMemo(
    () => [
      { label: "Use Default Model", value: "" },
      ...Object.entries(atm_studio_data.content_models).map(
        ([value, label]) => ({ label, value })
      ),
    ],
    []
  );

  const handleGenerate = async () => {
    const { content, postId, title } = getEditorPayload();
    if (!content.trim()) {
      alert("Editor content is empty. Please write or paste content first.");
      return;
    }
    setIsGenerating(true);
    setComments([]);
    setStatusMessage("Generating lifelike comments...");
    try {
      const res = await callAjax("generate_post_comments", {
        post_id: postId,
        title,
        content,
        count,
        threaded,
        model,
      });
      if (!res.success) throw new Error(res.data || "Generation failed.");
      setComments(res.data.comments || []);
      setStatusMessage("✅ Comments generated. Review and save to WordPress.");
    } catch (e) {
      setStatusMessage(`Error: ${e.message}`);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleSave = async () => {
    if (!comments.length) {
      alert("There are no comments to save.");
      return;
    }
    const { postId } = getEditorPayload();
    setIsSaving(true);
    setStatusMessage("Saving comments to WordPress...");
    try {
      const res = await callAjax("save_generated_comments", {
        post_id: postId,
        approve: approveNow ? "true" : "false",
        // Send as a JSON string to preserve parent_index
        comments: JSON.stringify(comments),
      });
      if (!res.success) throw new Error(res.data || "Save failed.");
      setStatusMessage(`✅ Saved ${res.data.inserted} comment(s).`);
      // Optionally refresh comments panel or clear
      // setComments([]);
    } catch (e) {
      setStatusMessage(`Error: ${e.message}`);
    } finally {
      setIsSaving(false);
    }
  };

  // Build a simple threaded structure based on parent_index
  const threadedList = useMemo(() => {
    const nodes = comments.map((c, i) => ({ ...c, index: i, children: [] }));
    const roots = [];
    const byIndex = Object.fromEntries(nodes.map((n) => [n.index, n]));

    nodes.forEach((node) => {
      const p = Number.isInteger(node.parent_index) ? node.parent_index : null;
      if (p === null || p < 0 || !(p in byIndex)) {
        roots.push(node);
      } else {
        byIndex[p].children.push(node);
      }
    });
    return roots;
  }, [comments]);

  const CommentCard = ({ comment, depth = 0 }) => {
    return (
      <div
        className="atm-form-container"
        style={{
          marginLeft: depth ? Math.min(depth, 4) * 18 : 0,
          borderLeft: depth ? "3px solid #e2e8f0" : "1px solid #e2e8f0",
        }}
      >
        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            marginBottom: 6,
          }}
        >
          <strong style={{ color: "#1e293b" }}>
            {comment.author_name || "Anonymous"}
          </strong>
          <span style={{ color: "#94a3b8", fontSize: 12 }}>
            {Number.isInteger(comment.parent_index) ? "Reply" : "Top-level"}
          </span>
        </div>
        <div style={{ whiteSpace: "pre-wrap", color: "#334155" }}>
          {comment.text || ""}
        </div>
        {comment.children?.length ? (
          <div style={{ marginTop: 10 }}>
            {comment.children.map((child) => (
              <CommentCard
                key={child.index}
                comment={child}
                depth={depth + 1}
              />
            ))}
          </div>
        ) : null}
      </div>
    );
  };

  return (
    <div className="atm-generator-view">
      <div className="atm-view-header">
        <button
          className="atm-back-btn"
          onClick={() => setActiveView("hub")}
          disabled={isGenerating || isSaving}
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path
              d="M15 18L9 12L15 6"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </button>
        <h3>Generate Comments</h3>
      </div>

      <div className="atm-form-container">
        <div className="atm-grid-2">
          <CustomDropdown
            label="AI Model"
            text={modelLabel}
            options={modelOptions}
            onChange={(opt) => {
              setModel(opt.value);
              setModelLabel(opt.label);
            }}
            disabled={isGenerating || isSaving}
          />
          <RangeControl
            label={`Number of Comments: ${count}`}
            value={count}
            onChange={setCount}
            min={5}
            max={10}
            disabled={isGenerating || isSaving}
          />
        </div>
        <div className="atm-grid-2">
          <ToggleControl
            label="Include Threaded Replies"
            checked={threaded}
            onChange={setThreaded}
            disabled={isGenerating || isSaving}
            help="Allow some comments to reply to others for realism."
          />
          <ToggleControl
            label="Approve Immediately"
            checked={approveNow}
            onChange={setApproveNow}
            disabled={isGenerating || isSaving}
            help="If off, comments are saved as Pending."
          />
        </div>

        <div style={{ display: "flex", gap: 10 }}>
          <Button
            isPrimary
            onClick={handleGenerate}
            disabled={isGenerating || isSaving}
          >
            {isGenerating ? (
              <>
                <CustomSpinner /> Generating...
              </>
            ) : (
              "Generate Comments"
            )}
          </Button>
          <Button
            isSecondary
            onClick={handleSave}
            disabled={isSaving || isGenerating || comments.length === 0}
          >
            {isSaving ? (
              <>
                <CustomSpinner /> Saving...
              </>
            ) : (
              "Save to WordPress"
            )}
          </Button>
        </div>

        {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
      </div>

      {comments.length > 0 && (
        <div style={{ marginTop: 16 }}>
          {threadedList.map((root) => (
            <CommentCard key={root.index} comment={root} />
          ))}
        </div>
      )}
    </div>
  );
}

export default CommentsGenerator;
