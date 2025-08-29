import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import CustomDropdown from './common/CustomDropdown'; // We will create this
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

    // Use our new reusable hook!
    const { isRecording, isTranscribing, startRecording, stopRecording } = useSpeechToText({
        onTranscriptionComplete: (transcript) => {
            setSourceText(current => current + (current ? ' ' : '') + transcript);
            setStatusMessage('✅ Transcription complete. Ready to translate.');
        },
        onTranscriptionError: (error) => {
            setStatusMessage(`Error: ${error}`);
        }
    });

    const languageOptions = [
        { label: 'Spanish', value: 'Spanish' },
        { label: 'French', value: 'French' },
        { label: 'German', value: 'German' },
        { label: 'Japanese', value: 'Japanese' },
        { label: 'Chinese', value: 'Chinese' },
        { label: 'Arabic', value: 'Arabic' },
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

                <div className="atm-grid-2">
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
                    <div className="atm-actions-group" style={{alignSelf: 'end'}}>
                        {isRecording ? (
                            <Button isDestructive onClick={stopRecording}>Stop Recording</Button>
                        ) : (
                            <Button isSecondary onClick={startRecording} disabled={isLoading || isTranscribing}>Record Audio</Button>
                        )}
                        <Button isPrimary onClick={handleTranslate} disabled={isLoading || isRecording || isTranscribing}>
                            {(isLoading || isTranscribing) ? <Spinner/> : 'Translate'}
                        </Button>
                    </div>
                </div>

                <TextareaControl
                    label="Translated Text"
                    value={translatedText}
                    readOnly
                    rows={8}
                />
                
                <Button isSecondary onClick={handleSendToEditor} disabled={!translatedText.trim()}>
                    Send to Editor
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default Translator;