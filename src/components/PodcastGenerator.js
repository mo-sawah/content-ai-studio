import { useState, useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, TextareaControl, Spinner } from '@wordpress/components';
import CustomDropdown from './common/CustomDropdown'; // Assuming this is in the common folder

// Player View Component
function PlayerView({ podcastUrl, onRegenerate, postId }) {
    const [isRegenerating, setIsRegenerating] = useState(false);
    const { editPost } = useDispatch('core/editor');

    const handleChangeCover = () => {
        const mediaUploader = wp.media({
            title: "Select Podcast Cover Image",
            button: { text: "Use This Image" },
            multiple: false, library: { type: "image" }
        });
        mediaUploader.on("select", () => {
            const attachment = mediaUploader.state().get("selection").first().toJSON();
            jQuery.ajax({ 
                url: atm_studio_data.ajax_url, 
                type: 'POST', 
                data: { 
                    action: 'upload_podcast_image', 
                    nonce: atm_studio_data.nonce,
                    post_id: postId, 
                    image_id: attachment.id 
                }
            }).done(() => {
                editPost({ meta: { _atm_podcast_image_id: attachment.id } });
                window.location.reload();
            });
        });
        mediaUploader.open();
    };

    const handleRegenerateClick = async () => {
        setIsRegenerating(true);
        const script = wp.data.select('core/editor').getEditedPostAttribute('meta')._atm_podcast_script;
        const voice = wp.data.select('core/editor').getEditedPostAttribute('meta')._atm_podcast_voice;
        const provider = wp.data.select('core/editor').getEditedPostAttribute('meta')._atm_podcast_provider;
        await onRegenerate(script, voice, provider);
        setIsRegenerating(false);
    };

    return (
        <div className="atm-form-container">
            <h4>Your Podcast is Ready</h4>
            {/* The player is now rendered via PHP on the frontend */}
            <p className="components-base-control__help">A player has been automatically embedded at the top of your post.</p>
            <div className="atm-grid-2">
                <Button isSecondary onClick={handleChangeCover}>Change Cover Image</Button>
                <Button isDestructive onClick={handleRegenerateClick} disabled={isRegenerating}>
                    {isRegenerating ? <Spinner /> : 'Regenerate Audio'}
                </Button>
            </div>
        </div>
    );
}

function GeneratorView({ handleGenerateScript, handleGenerateAudio, statusMessage, isLoading }) {
    const [scriptContent, setScriptContent] = useState('');
    const [selectedVoice, setSelectedVoice] = useState('');
    const [selectedVoiceLabel, setSelectedVoiceLabel] = useState('');
    const [selectedLanguage, setSelectedLanguage] = useState('English');
    const [selectedLanguageLabel, setSelectedLanguageLabel] = useState('English');
    const [audioProvider, setAudioProvider] = useState(atm_studio_data.audio_provider || 'openai');
    const [audioProviderLabel, setAudioProviderLabel] = useState(atm_studio_data.audio_provider === 'elevenlabs' ? 'ElevenLabs' : 'OpenAI TTS');

    const openaiVoices = Object.entries(atm_studio_data.tts_voices).map(([value, label]) => ({ label, value }));
    const elevenlabsVoices = Object.entries(atm_studio_data.elevenlabs_voices).map(([value, label]) => ({ label, value }));
    const voiceOptions = audioProvider === 'elevenlabs' ? elevenlabsVoices : openaiVoices;

    const languageOptions = [
        { label: 'English', value: 'English' }, { label: 'Spanish', value: 'Spanish' },
        { label: 'French', value: 'French' }, { label: 'German', value: 'German' }
    ];

    const providerOptions = [
        { label: 'OpenAI TTS', value: 'openai' },
        { label: 'ElevenLabs', value: 'elevenlabs' }
    ];

    useEffect(() => {
        if (voiceOptions.length > 0) {
            const currentSelection = voiceOptions.find(opt => opt.value === selectedVoice);
            if (!currentSelection) {
                setSelectedVoice(voiceOptions[0].value);
                setSelectedVoiceLabel(voiceOptions[0].label);
            }
        }
    }, [audioProvider, voiceOptions]);

    return (
        <div className="atm-form-container">
            <CustomDropdown
                label="Script Language"
                text={selectedLanguageLabel}
                options={languageOptions}
                onChange={(option) => {
                    setSelectedLanguage(option.value);
                    setSelectedLanguageLabel(option.label);
                }}
                disabled={isLoading}
            />
            <Button isSecondary onClick={() => handleGenerateScript(selectedLanguage, setScriptContent)} disabled={isLoading}>
                {isLoading && statusMessage.includes('script') ? <Spinner /> : 'Step 1: Generate Script from Post Content'}
            </Button>
            <TextareaControl
                label="Podcast Script"
                help="The generated script will appear here. You can edit it before generating the audio."
                value={scriptContent}
                onChange={setScriptContent}
                rows="15"
                disabled={isLoading}
            />
            <div className="atm-grid-2">
                <CustomDropdown
                    label="Audio Provider"
                    text={audioProviderLabel}
                    options={providerOptions}
                    onChange={(option) => {
                        setAudioProvider(option.value);
                        setAudioProviderLabel(option.label);
                    }}
                    disabled={isLoading || elevenlabsVoices.length === 0}
                    helpText={elevenlabsVoices.length === 0 ? 'Enter ElevenLabs API key in settings to enable.' : ''}
                />
                <CustomDropdown
                    label="AI Voice"
                    text={selectedVoiceLabel || (voiceOptions.length > 0 ? voiceOptions[0].label : 'No voices available')}
                    options={voiceOptions}
                    onChange={(option) => {
                        setSelectedVoice(option.value);
                        setSelectedVoiceLabel(option.label);
                    }}
                    disabled={isLoading || !voiceOptions.length}
                />
            </div>
            <Button isPrimary onClick={() => handleGenerateAudio(scriptContent, selectedVoice, audioProvider)} disabled={isLoading || !scriptContent.trim() || !selectedVoice}>
                {isLoading && statusMessage.includes('audio') ? <Spinner /> : 'Step 2: Generate MP3 Audio'}
            </Button>
        </div>
    );
}

function PodcastGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');

    const { postId, existingPodcastUrl } = useSelect(select => ({
        postId: select('core/editor').getCurrentPostId(),
        existingPodcastUrl: select('core/editor').getEditedPostAttribute('meta')?._atm_podcast_url,
    }), []);

    const handleGenerateScript = async (language, setScriptContent) => {
        const editorContent = wp.data.select('core/editor').getEditedPostContent();
        if (!editorContent.trim()) {
            alert('Please write some content in the editor before generating a script.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Generating podcast script...');
        try {
            const response = await jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action: 'generate_podcast_script', nonce: atm_studio_data.nonce, content: editorContent, post_id: postId, language: language } });
            if (!response.success) throw new Error(response.data);
            setScriptContent(response.data.script);
            setStatusMessage('✅ Script generated successfully. You can now edit it below.');
        } catch (error) {
            setStatusMessage(`Error generating script: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };

    const handleGenerateAudio = async (script, voice, provider) => {
        if (!script.trim()) {
            alert('Script cannot be empty.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Generating MP3 audio... this may take a minute.');
        try {
            const response = await jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action: 'generate_podcast', nonce: atm_studio_data.nonce, post_id: postId, script, voice, provider } });
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