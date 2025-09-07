import { useState, useEffect, useRef } from "@wordpress/element";
import { useDispatch } from "@wordpress/data";
import { Button, TextareaControl } from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";
import { createBlock } from "@wordpress/blocks";
import CustomDropdown from "./common/CustomDropdown";
import { useSpeechToText } from "../hooks/useSpeechToText";

const callAjax = (action, data) =>
  jQuery.ajax({
    url: atm_studio_data.ajax_url,
    type: "POST",
    data: { action, nonce: atm_studio_data.nonce, ...data },
  });

const getEditorContent = () => {
  const editor = wp.data.select("core/editor");
  return {
    title: editor.getEditedPostAttribute("title"),
    content: editor.getEditedPostContent(),
  };
};

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

function Translator({ setActiveView }) {
  const [sourceText, setSourceText] = useState("");
  const [translatedText, setTranslatedText] = useState("");
  const [targetLanguage, setTargetLanguage] = useState("Spanish");
  const [targetLanguageLabel, setTargetLanguageLabel] = useState("Spanish");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [recordStatus, setRecordStatus] = useState("");
  const [editorTargetLanguage, setEditorTargetLanguage] = useState("Spanish");
  const [editorTargetLanguageLabel, setEditorTargetLanguageLabel] =
    useState("Spanish");
  const [isEditorTranslating, setIsEditorTranslating] = useState(false);

  const { insertBlocks } = useDispatch("core/block-editor");
  const { savePost } = useDispatch("core/editor");

  const { isRecording, isTranscribing, startRecording, stopRecording } =
    useSpeechToText({
      onTranscriptionComplete: (transcript) => {
        setSourceText((current) => current + (current ? " " : "") + transcript);
        setRecordStatus("✅ Transcription complete.");
        setTimeout(() => setRecordStatus(""), 3000);
      },
      onTranscriptionError: (error) => {
        setRecordStatus(`Error: ${error}`);
      },
    });

  const languageOptions = [
    { label: "Spanish", value: "Spanish" },
    { label: "French", value: "French" },
    { label: "German", value: "German" },
    { label: "Chinese (Simplified)", value: "Chinese (Simplified)" },
    { label: "Japanese", value: "Japanese" },
    { label: "Russian", value: "Russian" },
    { label: "Portuguese", value: "Portuguese" },
    { label: "Italian", value: "Italian" },
    { label: "Arabic", value: "Arabic" },
    { label: "Hindi", value: "Hindi" },
    { label: "Korean", value: "Korean" },
    { label: "Dutch", value: "Dutch" },
    { label: "Turkish", value: "Turkish" },
    { label: "Polish", value: "Polish" },
    { label: "Swedish", value: "Swedish" },
  ];

  const handleTranslate = async () => {
    if (!sourceText.trim()) return alert("Please enter text to translate.");
    setIsLoading(true);
    setStatusMessage(`Translating to ${targetLanguage}...`);
    setTranslatedText("");
    try {
      const res = await callAjax("translate_text", {
        source_text: sourceText,
        target_language: targetLanguage,
      });
      if (res.success) {
        setTranslatedText(res.data.translated_text);
        setStatusMessage("✅ Translation successful!");
      } else {
        throw new Error(res.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSendToEditor = () => {
    if (!translatedText.trim()) return;
    insertBlocks(
      createBlock("core/paragraph", { content: translatedText.trim() })
    );
    setStatusMessage("Text inserted into editor!");
  };

  const handleTranslateEditor = async () => {
    setIsEditorTranslating(true);
    setStatusMessage("Reading editor content...");
    const { title, content } = getEditorContent();

    if (!content.trim()) {
      alert("Editor content is empty. Please write something to translate.");
      setIsEditorTranslating(false);
      return;
    }

    setStatusMessage(`Translating entire post to ${editorTargetLanguage}...`);
    try {
      const response = await callAjax("translate_editor_content", {
        title,
        content,
        target_language: editorTargetLanguage,
      });

      if (response.success) {
        const { translated_title, translated_content } = response.data;
        updateEditorContent(translated_title, translated_content);
        setStatusMessage("✅ Post translated! Saving...");
        await savePost();
        setStatusMessage("✅ Post translated and saved!");
      } else {
        throw new Error(response.data);
      }
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsEditorTranslating(false);
    }
  };

  useEffect(() => {
    if (isRecording) setRecordStatus("Recording...");
    else if (isTranscribing) setRecordStatus("Transcribing...");
  }, [isRecording, isTranscribing]);

  const isBusy =
    isLoading || isRecording || isTranscribing || isEditorTranslating;

  return (
    <div className="atm-generator-view">
      <div className="atm-form-container">
        <div className="atm-translator-full-editor">
          <Button isPrimary onClick={handleTranslateEditor} disabled={isBusy}>
            {isEditorTranslating ? (
              <>
                <CustomSpinner /> Translating...
              </>
            ) : (
              "Translate Editor"
            )}
          </Button>
          <span>to</span>
          <CustomDropdown
            label=""
            text={editorTargetLanguageLabel}
            options={languageOptions}
            onChange={(option) => {
              setEditorTargetLanguage(option.value);
              setEditorTargetLanguageLabel(option.label);
            }}
            disabled={isBusy}
          />
        </div>

        <hr className="atm-form-divider" />

        <label className="components-base-control__label">
          Text Snippet to Translate
        </label>
        <div className="atm-translator-main-area">
          <TextareaControl
            value={sourceText}
            onChange={setSourceText}
            placeholder="Type your text here..."
            rows={8}
            disabled={isBusy}
            className="atm-translator-text-input"
          />
          <div className="atm-translator-recorder">
            <h4>Or Record to Translate</h4>
            <div
              className={`atm-record-button-wrapper is-small ${isRecording ? "is-recording" : ""}`}
            >
              <div className="atm-pulse-ring atm-pulse-ring-1"></div>
              <div className="atm-pulse-ring atm-pulse-ring-2"></div>
              <button
                className={`atm-record-button ${isRecording ? "is-recording" : ""} ${isTranscribing ? "is-transcribing" : ""}`}
                onClick={isRecording ? stopRecording : startRecording}
                disabled={isBusy}
                aria-label={isRecording ? "Stop Recording" : "Start Recording"}
              >
                {isTranscribing ? (
                  <CustomSpinner />
                ) : isRecording ? (
                  <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                  >
                    <rect x="6" y="6" width="12" height="12" rx="2" />
                  </svg>
                ) : (
                  <img
                    src={`${atm_studio_data.plugin_url}/includes/images/mic.svg`}
                    alt="Microphone"
                    className="atm-mic-icon"
                  />
                )}
              </button>
            </div>
            {(isRecording || isTranscribing || recordStatus) && (
              <p className="atm-recording-status">{recordStatus}</p>
            )}
          </div>
        </div>

        <CustomDropdown
          label="Translate Snippet To"
          text={targetLanguageLabel}
          options={languageOptions}
          onChange={(option) => {
            setTargetLanguage(option.value);
            setTargetLanguageLabel(option.label);
          }}
          disabled={isBusy}
        />
        <Button
          isSecondary
          onClick={handleTranslate}
          disabled={isBusy || !sourceText.trim()}
        >
          {isLoading ? (
            <>
              <CustomSpinner /> Translating...
            </>
          ) : (
            "Translate Snippet"
          )}
        </Button>
        <TextareaControl
          label="Translated Snippet"
          value={translatedText}
          readOnly
          rows={8}
        />
        <Button
          isSecondary
          onClick={handleSendToEditor}
          disabled={!translatedText.trim() || isBusy}
        >
          Insert Snippet into Editor
        </Button>
        {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
      </div>
    </div>
  );
}

export default Translator;
