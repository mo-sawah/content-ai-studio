// src/components/HumanizeComponent.js
import { useState, useEffect } from "@wordpress/element";

function HumanizeComponent({ setActiveView }) {
  const [isLoading, setIsLoading] = useState(false);
  const [content, setContent] = useState("");
  const [humanizedContent, setHumanizedContent] = useState("");
  const [settings, setSettings] = useState({
    tone: "Standard",
    mode: "High", // High, Medium, Low
    businessMode: true, // Use 10x more powerful model
    preserveFormatting: true,
    autoReplace: false,
  });
  const [stats, setStats] = useState({
    originalWords: 0,
    humanizedWords: 0,
    detectionScore: null,
  });
  const [progress, setProgress] = useState("");

  // Load editor content on component mount
  useEffect(() => {
    loadEditorContent();
  }, []);

  const loadEditorContent = () => {
    // Get content from WordPress editor
    if (window.wp && window.wp.data) {
      const editorContent = window.wp.data
        .select("core/editor")
        .getEditedPostContent();
      if (editorContent) {
        setContent(stripHtml(editorContent));
        setStats((prev) => ({
          ...prev,
          originalWords: countWords(editorContent),
        }));
      }
    } else if (window.tinymce && window.tinymce.activeEditor) {
      const editorContent = window.tinymce.activeEditor.getContent({
        format: "text",
      });
      setContent(editorContent);
      setStats((prev) => ({
        ...prev,
        originalWords: countWords(editorContent),
      }));
    }
  };

  const stripHtml = (html) => {
    const div = document.createElement("div");
    div.innerHTML = html;
    return div.textContent || div.innerText || "";
  };

  const countWords = (text) => {
    return text
      .trim()
      .split(/\s+/)
      .filter((word) => word.length > 0).length;
  };

  const handleHumanize = async () => {
    if (!content.trim()) {
      alert("Please load content from the editor first.");
      return;
    }

    if (content.length < 50) {
      alert("Content must be at least 50 characters long for humanization.");
      return;
    }

    setIsLoading(true);
    setProgress("Analyzing content...");

    try {
      const response = await fetch(atm_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "humanize_content",
          nonce: atm_ajax.nonce,
          content: content,
          tone: settings.tone,
          mode: settings.mode,
          business_mode: settings.businessMode,
          preserve_formatting: settings.preserveFormatting,
        }),
      });

      const data = await response.json();

      if (data.success) {
        setHumanizedContent(data.data.humanized_content);
        setStats((prev) => ({
          ...prev,
          humanizedWords: countWords(data.data.humanized_content),
          detectionScore: data.data.detection_score || null,
        }));
        setProgress("Content successfully humanized!");
      } else {
        throw new Error(data.data || "Humanization failed");
      }
    } catch (error) {
      console.error("Humanization error:", error);
      alert(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      setTimeout(() => setProgress(""), 3000);
    }
  };

  const handleReplaceContent = async () => {
    if (!humanizedContent) {
      alert("No humanized content available to replace.");
      return;
    }

    try {
      // Replace content in WordPress editor
      if (window.wp && window.wp.data) {
        const { dispatch } = window.wp.data;
        dispatch("core/editor").editPost({ content: humanizedContent });
      } else if (window.tinymce && window.tinymce.activeEditor) {
        window.tinymce.activeEditor.setContent(humanizedContent);
      }

      alert("Content successfully replaced in editor!");

      if (settings.autoReplace) {
        setActiveView("hub");
      }
    } catch (error) {
      console.error("Content replacement error:", error);
      alert("Failed to replace content in editor.");
    }
  };

  const handleCopyContent = () => {
    if (!humanizedContent) return;

    navigator.clipboard
      .writeText(humanizedContent)
      .then(() => {
        alert("Humanized content copied to clipboard!");
      })
      .catch((err) => {
        console.error("Copy failed:", err);
      });
  };

  const detectAI = async () => {
    if (!humanizedContent) {
      alert("No humanized content to check.");
      return;
    }

    setIsLoading(true);
    setProgress("Running AI detection...");

    try {
      const response = await fetch(atm_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "check_ai_detection",
          nonce: atm_ajax.nonce,
          content: humanizedContent,
        }),
      });

      const data = await response.json();

      if (data.success) {
        setStats((prev) => ({
          ...prev,
          detectionScore: data.data.detection_score,
        }));
        setProgress(
          `Detection Score: ${data.data.detection_score}% AI detected`
        );
      } else {
        throw new Error(data.data || "AI detection failed");
      }
    } catch (error) {
      console.error("AI detection error:", error);
      alert(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
      setTimeout(() => setProgress(""), 5000);
    }
  };

  return (
    <div className="atm-humanize-container">
      <div className="atm-humanize-header">
        <h2>Humanize AI Content</h2>
        <p>
          Transform AI-generated content into natural, human-like text that
          bypasses AI detection.
        </p>
      </div>

      {/* Settings Panel */}
      <div className="atm-settings-panel">
        <h3>Humanization Settings</h3>
        <div className="atm-settings-grid">
          <div className="atm-setting-group">
            <label>Tone Style:</label>
            <select
              value={settings.tone}
              onChange={(e) =>
                setSettings((prev) => ({ ...prev, tone: e.target.value }))
              }
            >
              <option value="Standard">Standard</option>
              <option value="HighSchool">High School</option>
              <option value="College">College</option>
              <option value="PhD">PhD</option>
              <option value="Professional">Professional</option>
              <option value="Casual">Casual</option>
            </select>
          </div>

          <div className="atm-setting-group">
            <label>Detail Level:</label>
            <select
              value={settings.mode}
              onChange={(e) =>
                setSettings((prev) => ({ ...prev, mode: e.target.value }))
              }
            >
              <option value="High">High Quality</option>
              <option value="Medium">Balanced</option>
              <option value="Low">Fast</option>
            </select>
          </div>

          <div className="atm-setting-group">
            <label>
              <input
                type="checkbox"
                checked={settings.businessMode}
                onChange={(e) =>
                  setSettings((prev) => ({
                    ...prev,
                    businessMode: e.target.checked,
                  }))
                }
              />
              Use Enhanced Model (10x more powerful)
            </label>
          </div>

          <div className="atm-setting-group">
            <label>
              <input
                type="checkbox"
                checked={settings.preserveFormatting}
                onChange={(e) =>
                  setSettings((prev) => ({
                    ...prev,
                    preserveFormatting: e.target.checked,
                  }))
                }
              />
              Preserve Formatting
            </label>
          </div>

          <div className="atm-setting-group">
            <label>
              <input
                type="checkbox"
                checked={settings.autoReplace}
                onChange={(e) =>
                  setSettings((prev) => ({
                    ...prev,
                    autoReplace: e.target.checked,
                  }))
                }
              />
              Auto-replace in editor
            </label>
          </div>
        </div>
      </div>

      {/* Content Area */}
      <div className="atm-content-area">
        <div className="atm-content-section">
          <h3>Original Content</h3>
          <div className="atm-content-actions">
            <button
              onClick={loadEditorContent}
              className="atm-btn atm-btn-secondary"
            >
              Load from Editor
            </button>
            <span className="atm-word-count">{stats.originalWords} words</span>
          </div>
          <textarea
            value={content}
            onChange={(e) => {
              setContent(e.target.value);
              setStats((prev) => ({
                ...prev,
                originalWords: countWords(e.target.value),
              }));
            }}
            placeholder="Content will be loaded from the editor, or you can paste it here..."
            rows={10}
            className="atm-textarea"
          />
        </div>

        <div className="atm-content-section">
          <h3>Humanized Content</h3>
          <div className="atm-content-actions">
            <button
              onClick={handleReplaceContent}
              disabled={!humanizedContent}
              className="atm-btn atm-btn-primary"
            >
              Replace in Editor
            </button>
            <button
              onClick={handleCopyContent}
              disabled={!humanizedContent}
              className="atm-btn atm-btn-secondary"
            >
              Copy
            </button>
            <button
              onClick={detectAI}
              disabled={!humanizedContent || isLoading}
              className="atm-btn atm-btn-outline"
            >
              Check AI Detection
            </button>
            <span className="atm-word-count">{stats.humanizedWords} words</span>
          </div>
          <textarea
            value={humanizedContent}
            onChange={(e) => setHumanizedContent(e.target.value)}
            placeholder="Humanized content will appear here..."
            rows={10}
            className="atm-textarea"
          />
        </div>
      </div>

      {/* Statistics */}
      {stats.detectionScore !== null && (
        <div className="atm-stats-panel">
          <h3>AI Detection Score</h3>
          <div
            className={`atm-detection-score ${stats.detectionScore < 10 ? "success" : stats.detectionScore < 30 ? "warning" : "danger"}`}
          >
            <span className="score-value">{stats.detectionScore}%</span>
            <span className="score-label">AI detected</span>
            <div className="score-status">
              {stats.detectionScore < 10 && "✅ Excellent - Passes as human"}
              {stats.detectionScore >= 10 &&
                stats.detectionScore < 30 &&
                "⚠️ Good - Minor AI signatures"}
              {stats.detectionScore >= 30 && "❌ Poor - Clearly AI-generated"}
            </div>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="atm-action-panel">
        <button
          onClick={handleHumanize}
          disabled={isLoading || !content.trim()}
          className="atm-btn atm-btn-primary atm-btn-large"
        >
          {isLoading ? "Humanizing..." : "🧠 Humanize Content"}
        </button>

        {progress && <div className="atm-progress-message">{progress}</div>}
      </div>

      {/* Help Section */}
      <div className="atm-help-section">
        <h3>💡 Tips for Best Results</h3>
        <ul>
          <li>
            <strong>Content Length:</strong> Works best with 100+ word content
          </li>
          <li>
            <strong>Enhanced Model:</strong> Use for maximum undetectability
            (costs more credits)
          </li>
          <li>
            <strong>Multiple Passes:</strong> For very robotic content, run
            humanization twice
          </li>
          <li>
            <strong>Review Output:</strong> Always review and edit the humanized
            content
          </li>
          <li>
            <strong>Detection Check:</strong> Test with multiple AI detectors
            for validation
          </li>
        </ul>
      </div>
    </div>
  );
}

export default HumanizeComponent;
