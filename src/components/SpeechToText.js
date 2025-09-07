import { useState, useRef } from "@wordpress/element";
import { useDispatch } from "@wordpress/data";
import { Spinner } from "@wordpress/components";

function SpeechToText({ setActiveView }) {
  const [isRecording, setIsRecording] = useState(false);
  const [isTranscribing, setIsTranscribing] = useState(false);
  const [statusMessage, setStatusMessage] = useState(
    "Click the button to start recording."
  );

  const mediaRecorderRef = useRef(null);
  const audioChunksRef = useRef([]);

  const { insertBlocks } = useDispatch("core/block-editor");

  const handleStartRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorderRef.current = new MediaRecorder(stream);
      audioChunksRef.current = [];

      mediaRecorderRef.current.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorderRef.current.onstop = () => {
        setIsRecording(false);
        setStatusMessage("Recording stopped. Preparing for transcription...");
        handleTranscription();
      };

      mediaRecorderRef.current.start();
      setIsRecording(true);
      setStatusMessage("Recording... Click to stop.");
    } catch (err) {
      setStatusMessage(
        "Error: Could not access microphone. Please grant permission."
      );
      console.error("Microphone access error:", err);
    }
  };

  const handleStopRecording = () => {
    if (
      mediaRecorderRef.current &&
      mediaRecorderRef.current.state === "recording"
    ) {
      mediaRecorderRef.current.stop();
      mediaRecorderRef.current.stream
        .getTracks()
        .forEach((track) => track.stop());
    }
  };

  const handleTranscription = async () => {
    if (audioChunksRef.current.length === 0) {
      setStatusMessage("No audio recorded. Click to start again.");
      return;
    }

    setIsTranscribing(true);
    setStatusMessage("Transcribing audio... Please wait.");

    const audioBlob = new Blob(audioChunksRef.current, { type: "audio/webm" });

    const formData = new FormData();
    formData.append("action", "transcribe_audio");
    formData.append("nonce", atm_studio_data.nonce);
    formData.append("audio_file", audioBlob, "recording.webm");

    try {
      const response = await fetch(atm_studio_data.ajax_url, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        const transcript = result.data.transcript;
        if (transcript.trim()) {
          const newBlock = wp.blocks.createBlock("core/paragraph", {
            content: transcript.trim(),
          });
          insertBlocks(newBlock);
          setStatusMessage("✅ Transcription complete and inserted!");
        } else {
          setStatusMessage("Transcription returned empty. Please try again.");
        }
      } else {
        throw new Error(result.data || "Transcription failed on the server.");
      }
    } catch (error) {
      console.error("Transcription error:", error);
      setStatusMessage(`Error: ${error.message}`);
    } finally {
      setIsTranscribing(false);
      audioChunksRef.current = [];
      setTimeout(
        () => setStatusMessage("Click the button to start a new recording."),
        5000
      );
    }
  };

  const isDisabled = isTranscribing;

  return (
    <div className="atm-generator-view">
      <div className="atm-speech-container">
        <div className="atm-recording-section">
          <div
            className={`atm-record-button-wrapper ${isRecording ? "is-recording" : ""}`}
          >
            <div className="atm-pulse-ring atm-pulse-ring-1"></div>
            <div className="atm-pulse-ring atm-pulse-ring-2"></div>
            <div className="atm-pulse-ring atm-pulse-ring-3"></div>

            <button
              className={`atm-record-button ${isRecording ? "is-recording" : ""} ${isTranscribing ? "is-transcribing" : ""}`}
              onClick={isRecording ? handleStopRecording : handleStartRecording}
              disabled={isTranscribing}
            >
              {isTranscribing ? (
                <Spinner />
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
          <p className="atm-recording-status">{statusMessage}</p>
        </div>

        <div className="atm-instructions">
          <p>
            Click the microphone to start recording your voice. The audio will
            be automatically transcribed and inserted into your post content.
          </p>
        </div>
      </div>
    </div>
  );
}

export default SpeechToText;
