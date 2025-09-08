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
    { label: "Long (15-20 minutes)", value: "long" },
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
            setIsGeneratingScript
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

      <TextareaControl
        label="Podcast Script"
        help="The generated two-person conversational script will appear here featuring Alex Chen (analytical host) and Jordan Rivera (enthusiastic co-host). You can edit it before generating the audio."
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
    setIsGeneratingScript
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
      setScriptContent(response.data.script);
      setStatusMessage(
        "Script generated successfully! Review the two-person conversation below, then generate audio."
      );
    } catch (error) {
      setStatusMessage(`Error generating script: ${error.message}`);
    } finally {
      setIsLoading(false);
      setIsGeneratingScript(false);
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
    setStatusMessage(
      "Generating professional two-person podcast audio... this may take several minutes."
    );

    try {
      const response = await jQuery.ajax({
        url: atm_studio_data.ajax_url,
        type: "POST",
        data: {
          action: "generate_podcast",
          nonce: atm_studio_data.nonce,
          post_id: postId,
          script,
          host_a_voice: hostAVoice,
          host_b_voice: hostBVoice,
          provider,
        },
      });
      if (!response.success) throw new Error(response.data);
      setStatusMessage(
        "Success! Your professional two-person podcast has been generated. The page will reload to show the audio player."
      );
      setTimeout(() => window.location.reload(), 2000);
    } catch (error) {
      setStatusMessage(`Error generating audio: ${error.message}`);
      setIsLoading(false);
    }
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
