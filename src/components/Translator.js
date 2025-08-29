import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import CustomDropdown from './common/CustomDropdown';
import { useSpeechToText } from '../hooks/useSpeechToText';

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

function Translator({ setActiveView }) {
    const [sourceText, setSourceText] = useState('');
    const [translatedText, setTranslatedText] = useState('');
    const [targetLanguage, setTargetLanguage] = useState('Spanish');
    const [targetLanguageLabel, setTargetLanguageLabel] = useState('Spanish');
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    
    const { insertBlocks } = useDispatch('core/block-editor');
    
    // --- NEW: More specific status for recording ---
    const [recordStatus, setRecordStatus] = useState('');

    const { isRecording, isTranscribing, startRecording, stopRecording } = useSpeechToText({
        onTranscriptionComplete: (transcript) => {
            setSourceText(current => current + (current ? ' ' : '') + transcript);
            setRecordStatus('✅ Transcription complete.');
            setTimeout(() => setRecordStatus(''), 3000);
        },
        onTranscriptionError: (error) => {
            setRecordStatus(`Error: ${error}`);
        }
    });

    // --- NEW: Expanded language list ---
    const languageOptions = [
        { label: 'Spanish', value: 'Spanish' },
        { label: 'French', value: 'French' },
        { label: 'German', value: 'German' },
        { label: 'Chinese (Simplified)', value: 'Chinese (Simplified)' },
        { label: 'Japanese', value: 'Japanese' },
        { label: 'Russian', value: 'Russian' },
        { label: 'Portuguese', value: 'Portuguese' },
        { label: 'Italian', value: 'Italian' },
        { label: 'Arabic', value: 'Arabic' },
        { label: 'Hindi', value: 'Hindi' },
        { label: 'Korean', value: 'Korean' },
        { label: 'Dutch', value: 'Dutch' },
        { label: 'Turkish', value: 'Turkish' },
        { label: 'Polish', value: 'Polish' },
        { label: 'Swedish', value: 'Swedish' },
    ];
    
    const handleTranslate = async () => {
        if (!sourceText.trim()) {
            alert('Please enter text to translate.');
            return;
        }
        setIsLoading(true);
        setStatusMessage(`Translating to ${targetLanguage}...`);
        setTranslatedText('');

        try {
            const response = await callAjax('translate_text', {
                source_text: sourceText,
                target_language: targetLanguage
            });

            if (response.success) {
                setTranslatedText(response.data.translated_text);
                setStatusMessage('✅ Translation successful!');
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };
    
    const handleSendToEditor = () => {
        if (!translatedText.trim()) return;
        const newBlock = createBlock('core/paragraph', { content: translatedText.trim() });
        insertBlocks(newBlock);
        setStatusMessage('Text inserted into editor!');
    };
    
    // --- NEW: Update recording status message based on state ---
    useEffect(() => {
        if (isRecording) {
            setRecordStatus('Recording... Click to stop.');
        } else if (isTranscribing) {
            setRecordStatus('Transcribing...');
        }
    }, [isRecording, isTranscribing]);

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading || isRecording || isTranscribing}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>AI Translator</h3>
            </div>

            <div className="atm-form-container">
                <TextareaControl
                    label="Text to Translate"
                    value={sourceText}
                    onChange={setSourceText}
                    placeholder="Type or record your text here..."
                    rows={8}
                    disabled={isLoading || isRecording || isTranscribing}
                />
                
                {/* --- NEW: Record button styled like SpeechToText but smaller --- */}
                <div className="atm-recording-section" style={{gap: '0.5rem', marginBottom: '1rem'}}>
                    <div className={`atm-record-button-wrapper is-small ${isRecording ? 'is-recording' : ''}`}>
                         <div className="atm-pulse-ring atm-pulse-ring-1"></div>
                         <div className="atm-pulse-ring atm-pulse-ring-2"></div>
                         <button 
                            className={`atm-record-button ${isRecording ? 'is-recording' : ''} ${isTranscribing ? 'is-transcribing' : ''}`}
                            onClick={isRecording ? stopRecording : startRecording}
                            disabled={isLoading || isTranscribing}
                            aria-label={isRecording ? 'Stop Recording' : 'Start Recording'}
                        >
                            {isTranscribing ? <Spinner /> : isRecording ? (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                            ) : (
                                <img src={`${atm_studio_data.plugin_url}/includes/images/mic.svg`} alt="Microphone" className="atm-mic-icon"/>
                            )}
                        </button>
                    </div>
                    { (isRecording || isTranscribing || recordStatus) && <p className="atm-recording-status">{recordStatus}</p> }
                </div>


                <CustomDropdown
                    label="Translate To"
                    text={targetLanguageLabel}
                    options={languageOptions}
                    onChange={(option) => {
                        setTargetLanguage(option.value);
                        setTargetLanguageLabel(option.label);
                    }}
                    disabled={isLoading || isRecording || isTranscribing}
                />

                <Button isPrimary onClick={handleTranslate} disabled={isLoading || isRecording || isTranscribing || !sourceText.trim()}>
                    {isLoading ? <Spinner/> : 'Translate'}
                </Button>

                <TextareaControl
                    label="Translated Text"
                    value={translatedText}
                    readOnly
                    rows={8}
                />
                
                <Button isSecondary onClick={handleSendToEditor} disabled={!translatedText.trim() || isLoading}>
                    Send to Editor
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default Translator;