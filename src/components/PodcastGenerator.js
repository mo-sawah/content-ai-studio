import { useState, useEffect, useRef } from "@wordpress/element";
import { useDispatch, useSelect } from "@wordpress/data";
import { Button, TextareaControl } from "@wordpress/components";
import CustomSpinner from "./common/CustomSpinner";
import CustomDropdown from "./common/CustomDropdown";

// Player View Component
function PlayerView({ podcastUrl, onRegenerate, postId }) {
  const [isRegenerating, setIsRegenerating] = useState(false);
  const { editPost } = useDispatch("core/editor");
  const { podcastScript, podcastVoice, podcastProvider } = useSelect(
    (select) => ({
      podcastScript:
        select("core/editor").getEditedPostAttribute("meta")
          ?._atm_podcast_script,
      podcastVoice:
        select("core/editor").getEditedPostAttribute("meta")
          ?._atm_podcast_voice,
      podcastProvider:
        select("core/editor").getEditedPostAttribute("meta")
          ?._atm_podcast_provider,
    }),
    []
  );

  const handleChangeCover = () => {
    const mediaUploader = wp.media({
      title: "Select Podcast Cover Image",
      button: { text: "Use This Image" },
      multiple: false,
      library: { type: "image" },
    });
    mediaUploader.on("select", () => {
      const attachment = mediaUploader
        .state()
        .get("selection")
        .first()
        .toJSON();
      jQuery
        .ajax({
          url: atm_studio_data.ajax_url,
          type: "POST",
          data: {
            action: "upload_podcast_image",
            nonce: atm_studio_data.nonce,
            post_id: postId,
            image_id: attachment.id,
          },
        })
        .done(() => {
          editPost({ meta: { _atm_podcast_image_id: attachment.id } });
          window.location.reload();
        });
    });
    mediaUploader.open();
  };

  const handleRegenerateClick = async () => {
    setIsRegenerating(true);
    await onRegenerate(podcastScript, podcastVoice, podcastProvider);
    setIsRegenerating(false);
  };

  return (
    <div className="atm-form-container">
      <h4>Your Podcast is Ready</h4>
      <p className="components-base-control__help">
        A player has been automatically embedded at the top of your post.
      </p>
      <div className="atm-grid-2">
        <Button isSecondary onClick={handleChangeCover}>
          Change Cover Image
        </Button>
        <Button
          isDestructive
          onClick={handleRegenerateClick}
          disabled={isRegenerating}
        >
          {isRegenerating ? (
            <>
              <CustomSpinner /> Regenerating...
            </>
          ) : (
            "Regenerate Audio"
          )}
        </Button>
      </div>
    </div>
  );
}

function GeneratorView({
  handleGenerateScript,
  handleGenerateAudio,
  statusMessage,
  isLoading,
}) {
  const [scriptContent, setScriptContent] = useState("");
  const [isGeneratingScript, setIsGeneratingScript] = useState(false);
  const [scriptProgress, setScriptProgress] = useState(0);
  const [currentSegment, setCurrentSegment] = useState("");
  const [scriptJobId, setScriptJobId] = useState(null);
  const [selectedLanguage, setSelectedLanguage] = useState("English");
  const [selectedLanguageLabel, setSelectedLanguageLabel] = useState("English");
  const [duration, setDuration] = useState("medium");
  const [durationLabel, setDurationLabel] = useState("Medium (8-12 minutes)");
  const [hostAVoice, setHostAVoice] = useState("alloy");
  const [hostAVoiceLabel, setHostAVoiceLabel] = useState("Alloy");
  const [hostBVoice, setHostBVoice] = useState("nova");
  const [hostBVoiceLabel, setHostBVoiceLabel] = useState("Nova");
  const [audioProvider, setAudioProvider] = useState(
    atm_studio_data?.audio_provider || "openai"
  );
  const [audioProviderLabel, setAudioProviderLabel] = useState(
    atm_studio_data?.audio_provider === "elevenlabs"
      ? "ElevenLabs"
      : "OpenAI TTS"
  );

  // Safe fallbacks for voice options
  const openaiVoices = atm_studio_data?.tts_voices
    ? Object.entries(atm_studio_data.tts_voices).map(([value, label]) => ({
        label,
        value,
      }))
    : [
        { label: "Alloy", value: "alloy" },
        { label: "Nova", value: "nova" },
      ];

  const elevenlabsVoices = atm_studio_data?.elevenlabs_voices
    ? Object.entries(atm_studio_data.elevenlabs_voices).map(
        ([value, label]) => ({ label, value })
      )
    : [];

  const voiceOptions =
    audioProvider === "elevenlabs" ? elevenlabsVoices : openaiVoices;

  const languageOptions = [
    { label: "English", value: "English" },
    { label: "Spanish", value: "Spanish" },
    { label: "French", value: "French" },
    { label: "German", value: "German" },
    { label: "Chinese (Simplified)", value: "Chinese" },
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
  ];

  const durationOptions = [
    { label: "Short (5-7 minutes)", value: "short" },
    { label: "Medium (8-12 minutes)", value: "medium" },
    { label: "Long (25-40 minutes)", value: "long" },
  ];

  const providerOptions = [
    { label: "OpenAI TTS", value: "openai" },
    { label: "ElevenLabs", value: "elevenlabs" },
  ];

  // Helper functions
  const getVoiceLabel = (voiceValue) => {
    const voice = voiceOptions.find((v) => v.value === voiceValue);
    return voice ? voice.label : "Select Voice";
  };

  const getDurationLabel = (durationValue) => {
    const duration = durationOptions.find((d) => d.value === durationValue);
    return duration ? duration.label : "Select Duration";
  };

  useEffect(() => {
    if (voiceOptions.length > 0) {
      // Update Host A voice if not found in current options
      const hostAMatch = voiceOptions.find((opt) => opt.value === hostAVoice);
      if (!hostAMatch) {
        setHostAVoice(voiceOptions[0].value);
        setHostAVoiceLabel(voiceOptions[0].label);
      } else {
        setHostAVoiceLabel(hostAMatch.label);
      }

      // Update Host B voice if not found in current options
      const hostBMatch = voiceOptions.find((opt) => opt.value === hostBVoice);
      if (!hostBMatch) {
        const differentVoice =
          voiceOptions.find((opt) => opt.value !== hostAVoice) ||
          voiceOptions[0];
        setHostBVoice(differentVoice.value);
        setHostBVoiceLabel(differentVoice.label);
      } else {
        setHostBVoiceLabel(hostBMatch.label);
      }
    }
  }, [audioProvider, voiceOptions]);

  // Polling function for script progress
  const pollScriptProgress = async (jobId, setScriptContent) => {
    let attempts = 0;
    const maxAttempts = 200; // 10 minutes max (3-second intervals)

    const checkProgress = async () => {
      try {
        attempts++;

        const response = await jQuery.ajax({
          url: atm_studio_data.ajax_url,
          type: "POST",
          data: {
            action: "check_script_progress",
            nonce: atm_studio_data.nonce,
            job_id: jobId,
          },
        });

        if (!response.success) {
          throw new Error(response.data);
        }

        const progress = response.data;

        // Update progress
        setScriptProgress(progress.progress);
        setCurrentSegment(progress.current_segment || "");

        if (progress.status === "completed") {
          setScriptContent(progress.script);
          setIsGeneratingScript(false);
          setScriptProgress(100);
          setCurrentSegment("Complete!");
          return;
        }

        if (progress.status === "failed") {
          throw new Error(progress.error_message || "Script generation failed");
        }

        if (attempts >= maxAttempts) {
          throw new Error("Script generation timed out. Please try again.");
        }

        // Continue polling
        setTimeout(checkProgress, 3000); // Check every 3 seconds
      } catch (error) {
        alert(`Error: ${error.message}`);
        setIsGeneratingScript(false);
        setScriptProgress(0);
        setCurrentSegment("");
      }
    };

    // Start checking after 3 seconds
    setTimeout(checkProgress, 3000);
  };

  // Progress display component
  const renderScriptProgress = () => {
    if (!isGeneratingScript) return null;

    const segmentNames = {
      intro_and_context: "Introduction & Context",
      main_discussion_part1: "Main Discussion (Part 1)",
      main_discussion_part2: "Main Discussion (Part 2)",
      conclusion_and_outro: "Conclusion & Outro",
    };

    return (
      <div
        style={{
          marginTop: "16px",
          padding: "16px",
          backgroundColor: "#f8fafc",
          borderRadius: "8px",
          border: "1px solid #e2e8f0",
        }}
      >
        <div
          style={{
            display: "flex",
            alignItems: "center",
            marginBottom: "12px",
            gap: "12px",
          }}
        >
          <CustomSpinner />
          <span style={{ fontWeight: "600" }}>
            Generating Script... {scriptProgress}%
          </span>
        </div>

        {/* Progress bar */}
        <div
          style={{
            width: "100%",
            height: "8px",
            backgroundColor: "#e2e8f0",
            borderRadius: "4px",
            overflow: "hidden",
            marginBottom: "8px",
          }}
        >
          <div
            style={{
              width: `${scriptProgress}%`,
              height: "100%",
              backgroundColor: "#10b981",
              transition: "width 0.3s ease",
              borderRadius: "4px",
            }}
          />
        </div>

        {currentSegment && (
          <div
            style={{
              fontSize: "14px",
              color: "#6b7280",
              fontStyle: "italic",
            }}
          >
            Current: {segmentNames[currentSegment] || currentSegment}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="atm-form-container">
      <h4>Two-Person Podcast Configuration</h4>
      <p className="components-base-control__help">
        Generate a professional conversational podcast with two hosts discussing
        your article content with research-enhanced insights.
      </p>

      <div className="atm-grid-2">
        <CustomDropdown
          label="Script Language"
          text={selectedLanguageLabel}
          options={languageOptions}
          onChange={(option) => {
            setSelectedLanguage(option.value);
            setSelectedLanguageLabel(option.label);
          }}
          disabled={isLoading || isGeneratingScript}
        />
        <CustomDropdown
          label="Podcast Duration"
          text={durationLabel}
          options={durationOptions}
          onChange={(option) => {
            setDuration(option.value);
            setDurationLabel(option.label);
          }}
          disabled={isLoading || isGeneratingScript}
          helpText="Target length for the podcast episode"
        />
      </div>

      <Button
        isSecondary
        onClick={() =>
          handleGenerateScript(
            selectedLanguage,
            duration,
            setScriptContent,
            setIsGeneratingScript,
            setScriptProgress,
            setCurrentSegment,
            setScriptJobId,
            pollScriptProgress
          )
        }
        disabled={isLoading || isGeneratingScript}
      >
        {isGeneratingScript ? (
          <>
            <CustomSpinner /> Generating Advanced Script...
          </>
        ) : (
          "Step 1: Generate Two-Person Script with Research"
        )}
      </Button>

      {/* Progress display for long scripts */}
      {renderScriptProgress()}

      <TextareaControl
        label="Podcast Script"
        help={
          duration === "long"
            ? "Long scripts are generated in the background and may take several minutes. The script will appear here when complete."
            : "The generated two-person conversational script will appear here featuring Alex Chen (analytical host) and Jordan Rivera (enthusiastic co-host). You can edit it before generating the audio."
        }
        value={scriptContent}
        onChange={setScriptContent}
        rows="25"
        disabled={isLoading || isGeneratingScript}
        className="atm-podcast-script-textarea"
      />

      <div className="atm-form-container">
        <h4>Voice Configuration</h4>

        <CustomDropdown
          label="Audio Provider"
          text={audioProviderLabel}
          options={providerOptions}
          onChange={(option) => {
            setAudioProvider(option.value);
            setAudioProviderLabel(option.label);
          }}
          disabled={isLoading || elevenlabsVoices.length === 0}
          helpText={
            elevenlabsVoices.length === 0
              ? "Enter ElevenLabs API key in settings to enable higher quality voices."
              : "ElevenLabs provides more natural-sounding voices for professional podcasts."
          }
        />

        <div className="atm-grid-2">
          <CustomDropdown
            label="Host A Voice (Primary)"
            text={hostAVoiceLabel}
            options={voiceOptions}
            onChange={(option) => {
              setHostAVoice(option.value);
              setHostAVoiceLabel(option.label);
            }}
            disabled={isLoading || !voiceOptions.length}
            helpText="The main host voice - typically leads the discussion"
          />
          <CustomDropdown
            label="Host B Voice (Co-host)"
            text={hostBVoiceLabel}
            options={voiceOptions}
            onChange={(option) => {
              setHostBVoice(option.value);
              setHostBVoiceLabel(option.label);
            }}
            disabled={isLoading || !voiceOptions.length}
            helpText="The co-host voice - provides reactions and questions"
          />
        </div>

        {hostAVoice === hostBVoice && voiceOptions.length > 1 && (
          <p
            style={{
              color: "#d97706",
              fontSize: "0.9rem",
              fontStyle: "italic",
            }}
          >
            Warning: Both hosts are using the same voice. Consider selecting
            different voices for better distinction.
          </p>
        )}
      </div>

      <Button
        isPrimary
        onClick={() =>
          handleGenerateAudio(
            scriptContent,
            hostAVoice,
            hostBVoice,
            audioProvider
          )
        }
        disabled={
          isLoading ||
          isGeneratingScript ||
          !scriptContent.trim() ||
          !hostAVoice ||
          !hostBVoice
        }
      >
        {isLoading && statusMessage.includes("audio") ? (
          <>
            <CustomSpinner /> Generating Two-Person Audio...
          </>
        ) : (
          "Step 2: Generate Professional Podcast Audio"
        )}
      </Button>
    </div>
  );
}

function PodcastGenerator({ setActiveView }) {
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");

  const { postId, existingPodcastUrl } = useSelect(
    (select) => ({
      postId: select("core/editor").getCurrentPostId(),
      existingPodcastUrl:
        select("core/editor").getEditedPostAttribute("meta")?._atm_podcast_url,
    }),
    []
  );

  const handleGenerateScript = async (
    language,
    duration,
    setScriptContent,
    setIsGeneratingScript,
    setScriptProgress,
    setCurrentSegment,
    setScriptJobId,
    pollScriptProgress
  ) => {
    const editorContent = wp.data
      ?.select("core/editor")
      ?.getEditedPostContent();
    if (!editorContent || !editorContent.trim()) {
      alert(
        "Please write some content in the editor before generating a script."
      );
      return;
    }

    setIsLoading(true);
    setIsGeneratingScript(true);
    setScriptProgress(0);
    setCurrentSegment("");
    setStatusMessage(
      "Analyzing article content and researching additional information..."
    );

    try {
      const response = await jQuery.ajax({
        url: atm_studio_data.ajax_url,
        type: "POST",
        data: {
          action: "generate_podcast_script",
          nonce: atm_studio_data.nonce,
          content: editorContent,
          post_id: postId,
          language: language,
          duration: duration,
        },
      });

      if (!response.success) throw new Error(response.data);

      // Check if this is a background job (long scripts)
      if (response.data.job_id) {
        setScriptJobId(response.data.job_id);
        setStatusMessage("Long script generation started in background...");
        // Start polling for progress
        await pollScriptProgress(response.data.job_id, setScriptContent);
      } else {
        // Immediate response for short/medium scripts
        setScriptContent(response.data.script);
        setStatusMessage(
          "Script generated successfully! Review the two-person conversation below, then generate audio."
        );
        setIsGeneratingScript(false);
      }
    } catch (error) {
      setStatusMessage(`Error generating script: ${error.message}`);
      setIsGeneratingScript(false);
      setScriptProgress(0);
      setCurrentSegment("");
    } finally {
      setIsLoading(false);
    }
  };

  const handleGenerateAudio = async (
    script,
    hostAVoice,
    hostBVoice,
    provider
  ) => {
    if (!script.trim()) {
      alert("Script cannot be empty.");
      return;
    }

    // Updated validation to check for both old and new formats
    const hasOldFormat =
      script.includes("HOST_A:") && script.includes("HOST_B:");
    const hasNewFormat = script.includes("ALEX:") && script.includes("JORDAN:");

    if (!hasOldFormat && !hasNewFormat) {
      alert(
        "The script doesn't appear to be in the correct two-person format. Please regenerate the script first."
      );
      return;
    }

    setIsLoading(true);
    setStatusMessage("Starting podcast generation...");

    try {
      // Start the generation process
      const response = await jQuery.ajax({
        url: atm_studio_data.ajax_url,
        type: "POST",
        data: {
          action: "generate_podcast",
          nonce: atm_studio_data.nonce,
          post_id: postId,
          script: script,
          host_a_voice: hostAVoice,
          host_b_voice: hostBVoice,
          provider: provider,
        },
      });

      if (!response.success) {
        throw new Error(response.data);
      }

      const jobId = response.data.job_id;
      setStatusMessage("Podcast generation in progress. Please wait...");

      // Start polling for progress
      await pollProgress(jobId);
    } catch (error) {
      setStatusMessage(`Error: ${error.message}`);
      setIsLoading(false);
    }
  };

  // Add this new function to your PodcastGenerator.js component:
  const pollProgress = async (jobId) => {
    let attempts = 0;
    const maxAttempts = 120; // 10 minutes max (5-second intervals)

    const checkProgress = async () => {
      try {
        attempts++;

        const response = await jQuery.ajax({
          url: atm_studio_data.ajax_url,
          type: "POST",
          data: {
            action: "check_podcast_progress",
            nonce: atm_studio_data.nonce,
            job_id: jobId,
          },
        });

        if (!response.success) {
          throw new Error(response.data);
        }

        const progress = response.data;

        // Update status message with progress
        setStatusMessage(
          `Generating podcast: ${progress.completed_segments}/${progress.total_segments} segments complete (${progress.progress_percentage}%)`
        );

        if (progress.status === "completed") {
          setStatusMessage("Podcast generation completed! Reloading page...");
          setTimeout(() => window.location.reload(), 2000);
          return;
        }

        if (progress.status === "failed") {
          throw new Error(progress.error_message || "Generation failed");
        }

        if (attempts >= maxAttempts) {
          throw new Error("Generation timed out. Please try again.");
        }

        // Continue polling
        setTimeout(checkProgress, 5000); // Check every 5 seconds
      } catch (error) {
        setStatusMessage(`Error: ${error.message}`);
        setIsLoading(false);
      }
    };

    // Start checking
    setTimeout(checkProgress, 5000); // First check after 5 seconds
  };

  return (
    <div className="atm-generator-view">
      {existingPodcastUrl ? (
        <PlayerView
          podcastUrl={existingPodcastUrl}
          onRegenerate={handleGenerateAudio}
          postId={postId}
        />
      ) : (
        <GeneratorView
          handleGenerateScript={handleGenerateScript}
          handleGenerateAudio={handleGenerateAudio}
          statusMessage={statusMessage}
          isLoading={isLoading}
        />
      )}
      {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
    </div>
  );
}

export default PodcastGenerator;
