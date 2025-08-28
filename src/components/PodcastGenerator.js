import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, TextareaControl, Spinner, SelectControl } from '@wordpress/components';

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });
const getEditorContent = () => wp.data.select('core/editor').getEditedPostContent();

// --- NEW: Player View Component ---
function PlayerView({ podcastUrl, initialScript, onRegenerate, postId }) {
    const [isRegenerating, setIsRegenerating] = useState(false);

    const handleChangeCover = () => {
        const mediaUploader = wp.media({
            title: "Select Podcast Cover Image",
            button: { text: "Use This Image" },
            multiple: false, library: { type: "image" }
        });
        mediaUploader.on("select", () => {
            const attachment = mediaUploader.state().get("selection").first().toJSON();
            callAjax('upload_podcast_image', { post_id: postId, image_url: attachment.url })
                .done(() => window.location.reload());
        });
        mediaUploader.open();
    };

    return (
        <div className="atm-form-container">
            <h4>Your Podcast is Ready</h4>
            <audio controls src={podcastUrl} style={{ width: '100%', marginBottom: '1rem' }}>
                Your browser does not support the audio element.
            </audio>
            <div className="atm-grid-2">
                <Button isSecondary onClick={handleChangeCover}>Change Cover Image</Button>
                <Button isDestructive onClick={() => onRegenerate(initialScript)} disabled={isRegenerating}>
                    {isRegenerating ? <Spinner /> : 'Regenerate Audio'}
                </Button>
            </div>
        </div>
    );
}

function GeneratorView({ handleGenerateScript, handleGenerateAudio, statusMessage, isLoading }) {
    const [scriptContent, setScriptContent] = useState('');
    const [selectedVoice, setSelectedVoice] = useState('');
    const [selectedLanguage, setSelectedLanguage] = useState('English');
    const [audioProvider, setAudioProvider] = useState(atm_studio_data.audio_provider || 'openai');

    const openaiVoices = Object.entries(atm_studio_data.tts_voices).map(([value, label]) => ({ label, value }));
    const elevenlabsVoices = Object.entries(atm_studio_data.elevenlabs_voices).map(([value, label]) => ({ label, value }));
    const voiceOptions = audioProvider === 'elevenlabs' ? elevenlabsVoices : openaiVoices;

    useEffect(() => {
        if (voiceOptions.length > 0 && !selectedVoice) {
            setSelectedVoice(voiceOptions[0].value);
        }
    }, [audioProvider, voiceOptions.length]);

    return (
        <div className="atm-form-container">
            <SelectControl
                label="Script Language" value={selectedLanguage} onChange={setSelectedLanguage}
                options={[ { label: 'English', value: 'English' }, { label: 'Spanish', value: 'Spanish' }, { label: 'French', value: 'French' }, { label: 'German', value: 'German' } ]}
                disabled={isLoading}
            />
            <Button isSecondary onClick={() => handleGenerateScript(selectedLanguage, setScriptContent)} disabled={isLoading}>
                {isLoading && statusMessage.includes('script') ? <Spinner /> : '1. Generate Script from Post Content'}
            </Button>
            <TextareaControl
                label="Podcast Script" help="The generated script will appear here. You can edit it before generating the audio."
                value={scriptContent} onChange={setScriptContent}
                rows="15" disabled={isLoading}
            />
            <div className="atm-grid-2">
                <SelectControl
                    label="Audio Provider" value={audioProvider} onChange={setAudioProvider}
                    options={[ { label: 'OpenAI TTS', value: 'openai' }, { label: 'ElevenLabs', value: 'elevenlabs' } ]}
                    disabled={isLoading || elevenlabsVoices.length === 0}
                    help={elevenlabsVoices.length === 0 ? 'Enter ElevenLabs API key in settings to enable.' : ''}
                />
                <SelectControl
                    label="AI Voice" value={selectedVoice} onChange={setSelectedVoice}
                    options={voiceOptions} disabled={isLoading || !voiceOptions.length}
                    help={!voiceOptions.length ? 'No voices available for this provider.' : ''}
                />
            </div>
            <Button isPrimary onClick={() => handleGenerateAudio(scriptContent, selectedVoice, audioProvider)} disabled={isLoading || !scriptContent.trim() || !selectedVoice}>
                {isLoading && statusMessage.includes('audio') ? <Spinner /> : '2. Generate MP3 Audio'}
            </Button>
        </div>
    );
}

function PodcastGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');

    const postId = useSelect(select => select('core/editor').getCurrentPostId(), []);
    const { existing_podcast_url, existing_podcast_script } = atm_studio_data;

    const handleGenerateScript = async (language, setScriptContent) => {
        // ... (same as before)
    };

    const handleGenerateAudio = async (script, voice, provider) => {
        // ... (same as before)
    };

    // Refactor handleGenerateScript and handleGenerateAudio to be outside the child components
    // so they can be passed down and also used for regeneration.
    const memoizedHandleGenerateScript = async (language, setScriptContent) => {
        const editorContent = getEditorContent();
        if (!editorContent.trim()) {
            alert('Please write some content in the editor before generating a script.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Generating podcast script...');
        try {
            const response = await callAjax('generate_podcast_script', {
                content: editorContent, post_id: postId, language: language,
            });
            if (!response.success) throw new Error(response.data);
            setScriptContent(response.data.script);
            setStatusMessage('✅ Script generated successfully. You can now edit it below.');
        } catch (error) {
            setStatusMessage(`Error generating script: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };

    const memoizedHandleGenerateAudio = async (script, voice, provider) => {
        if (!script.trim()) {
            alert('Script cannot be empty.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Generating MP3 audio... this may take a minute.');
        try {
            const response = await callAjax('generate_podcast', {
                post_id: postId, script: script, voice: voice, provider: provider,
            });
            if (!response.success) throw new Error(response.data);
            setStatusMessage('✅ Success! The page will now reload to show the audio player.');
            setTimeout(() => window.location.reload(), 2000);
        } catch (error) {
            setStatusMessage(`Error generating audio: ${error.message}`);
            setIsLoading(false);
        }
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Generate Podcast</h3>
            </div>
            {existing_podcast_url ? (
                <PlayerView 
                    podcastUrl={existing_podcast_url}
                    initialScript={existing_podcast_script}
                    onRegenerate={memoizedHandleGenerateAudio}
                    postId={postId}
                />
            ) : (
                <GeneratorView 
                    handleGenerateScript={memoizedHandleGenerateScript}
                    handleGenerateAudio={memoizedHandleGenerateAudio}
                    statusMessage={statusMessage}
                    isLoading={isLoading}
                />
            )}
             {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
        </div>
    );
}

export default PodcastGenerator;