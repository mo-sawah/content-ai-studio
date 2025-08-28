import { useState, useRef, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, Spinner } from '@wordpress/components';

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
                const response = await jQuery.ajax({
                    url: atm_studio_data.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                });

                if (response.success) {
                    fullTranscript += response.data.transcript + ' ';
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                setStatusMessage(`Error during transcription: ${error.message || 'Unknown error'}`);
                setIsTranscribing(false);
                return;
            }
        }

        // Insert the final combined transcript into the editor
        const newBlock = wp.blocks.createBlock('core/paragraph', { content: fullTranscript.trim() });
        insertBlocks(newBlock);

        setStatusMessage('✅ Transcription complete and inserted into the editor!');
        setIsTranscribing(false);
        audioChunksRef.current = []; // Clear chunks for next time

        setTimeout(() => setStatusMessage('Click the button to start a new recording.'), 3000);
    };

    const isDisabled = isRecording || isTranscribing;

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isDisabled}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Speech to Text</h3>
            </div>
            <div className="atm-speech-to-text-container">
                <Button 
                    isPrimary 
                    className={`atm-record-button ${isRecording ? 'is-recording' : ''}`}
                    onClick={isRecording ? handleStopRecording : handleStartRecording}
                    disabled={isTranscribing}
                >
                    {isTranscribing ? <Spinner /> : (isRecording ? 'Stop Recording' : 'Start Recording')}
                </Button>
                <p className="atm-status-message">{statusMessage}</p>
            </div>
        </div>
    );
}

export default SpeechToText;