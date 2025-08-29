import { useState, useRef, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Spinner } from '@wordpress/components';

function SpeechToText({ setActiveView }) {
    const [isRecording, setIsRecording] = useState(false);
    const [isTranscribing, setIsTranscribing] = useState(false);
    const [statusMessage, setStatusMessage] = useState('Click the button to start recording.');
    const mediaRecorderRef = useRef(null);
    const audioChunksRef = useRef([]);

    const { insertBlocks } = useDispatch('core/block-editor');

    const handleStartRecording = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorderRef.current = new MediaRecorder(stream);

            // Clear out any old chunks
            audioChunksRef.current = [];

            mediaRecorderRef.current.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunksRef.current.push(event.data);
                }
            };

            mediaRecorderRef.current.onstop = () => {
                setIsRecording(false);
                // This triggers the useEffect below to start transcription
            };

            // Start recording, creating a new chunk every 30 seconds
            mediaRecorderRef.current.start(30000); 
            setIsRecording(true);
            setStatusMessage('Recording... Click to stop.');
        } catch (err) {
            setStatusMessage('Error: Could not access microphone. Please grant permission.');
            console.error("Microphone access error:", err);
        }
    };

    const handleStopRecording = () => {
        if (mediaRecorderRef.current) {
            mediaRecorderRef.current.stop();
            // Get the tracks and stop them to turn off the microphone indicator
            mediaRecorderRef.current.stream.getTracks().forEach(track => track.stop());
        }
    };

    // This effect runs when recording stops to process the audio chunks
    useEffect(() => {
        if (!isRecording && audioChunksRef.current.length > 0) {
            transcribeChunks();
        }
    }, [isRecording]);

    const transcribeChunks = async () => {
        setIsTranscribing(true);
        let fullTranscript = '';
        const totalChunks = audioChunksRef.current.length;

        for (let i = 0; i < totalChunks; i++) {
            setStatusMessage(`Transcribing chunk ${i + 1} of ${totalChunks}...`);
            const chunk = audioChunksRef.current[i];

            const formData = new FormData();
            formData.append('action', 'transcribe_audio');
            formData.append('nonce', atm_studio_data.nonce);
            formData.append('audio_chunk', chunk, 'audio.webm');

            try {
                // Use fetch instead of jQuery.ajax to avoid conflicts
                const response = await fetch(atm_studio_data.ajax_url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    fullTranscript += result.data.transcript + ' ';
                } else {
                    throw new Error(result.data || 'Transcription failed');
                }
            } catch (error) {
                console.error('Transcription error:', error);
                setStatusMessage(`Error during transcription: ${error.message || 'Unknown error'}`);
                setIsTranscribing(false);
                return;
            }
        }

        // Insert the final combined transcript into the editor
        if (fullTranscript.trim()) {
            const newBlock = wp.blocks.createBlock('core/paragraph', { content: fullTranscript.trim() });
            insertBlocks(newBlock);
            setStatusMessage('✅ Transcription complete and inserted into the editor!');
        } else {
            setStatusMessage('No transcript generated. Please try recording again.');
        }
        
        setIsTranscribing(false);
        audioChunksRef.current = []; // Clear chunks for next time

        setTimeout(() => setStatusMessage('Click the button to start a new recording.'), 3000);
    };

    const isDisabled = isTranscribing;

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isDisabled || isRecording}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Speech to Text</h3>
            </div>
            
            <div className="atm-speech-container">
                <div className="atm-recording-section">
                    <div className={`atm-record-button-wrapper ${isRecording ? 'is-recording' : ''}`}>
                        {/* Pulse rings for recording animation */}
                        <div className="atm-pulse-ring atm-pulse-ring-1"></div>
                        <div className="atm-pulse-ring atm-pulse-ring-2"></div>
                        <div className="atm-pulse-ring atm-pulse-ring-3"></div>
                        
                        {/* Main record button */}
                        <button 
                            className={`atm-record-button ${isRecording ? 'is-recording' : ''} ${isTranscribing ? 'is-transcribing' : ''}`}
                            onClick={isRecording ? handleStopRecording : handleStartRecording}
                            disabled={isTranscribing}
                        >
                            {isTranscribing ? (
                                <Spinner />
                            ) : isRecording ? (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                                </svg>
                            ) : (
                                <img 
                                    src={`${atm_studio_data.plugin_url || '/wp-content/plugins/article-to-media'}/includes/images/mic.svg`} 
                                    alt="Microphone" 
                                    width="24" 
                                    height="24"
                                    className="atm-mic-icon"
                                />
                            )}
                        </button>
                    </div>
                    
                    <p className="atm-recording-status">{statusMessage}</p>
                </div>
                
                {/* Instructions */}
                <div className="atm-instructions">
                    <p>Click the microphone to start recording your voice. The audio will be automatically transcribed and inserted into your post content.</p>
                </div>
            </div>
        </div>
    );
}

export default SpeechToText;