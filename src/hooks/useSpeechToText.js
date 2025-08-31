import { useState, useRef } from '@wordpress/element';

const callAjaxWithFile = (action, formData) => {
    formData.append('action', action);
    formData.append('nonce', atm_studio_data.nonce);

    return fetch(atm_studio_data.ajax_url, {
        method: 'POST',
        body: formData,
    }).then(response => response.json());
};

export function useSpeechToText({ onTranscriptionComplete, onTranscriptionError }) {
    const [isRecording, setIsRecording] = useState(false);
    const [isTranscribing, setIsTranscribing] = useState(false);
    const mediaRecorderRef = useRef(null);
    const audioChunksRef = useRef([]);

    const handleTranscription = async () => {
        if (audioChunksRef.current.length === 0) {
            onTranscriptionError?.('No audio was recorded.');
            return;
        }

        setIsTranscribing(true);
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio_file', audioBlob, 'recording.webm');

        try {
            const result = await callAjaxWithFile('transcribe_audio', formData);
            if (result.success) {
                onTranscriptionComplete?.(result.data.transcript);
            } else {
                throw new Error(result.data || 'Transcription failed on the server.');
            }
        } catch (error) {
            onTranscriptionError?.(error.message);
        } finally {
            setIsTranscribing(false);
            audioChunksRef.current = [];
        }
    };

    const startRecording = async () => {
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
                handleTranscription();
            };

            mediaRecorderRef.current.start();
            setIsRecording(true);
        } catch (err) {
            onTranscriptionError?.('Could not access microphone. Please grant permission.');
        }
    };

    const stopRecording = () => {
        if (mediaRecorderRef.current && mediaRecorderRef.current.state === 'recording') {
            mediaRecorderRef.current.stop();
            // Stop all media tracks to turn off the microphone indicator
            mediaRecorderRef.current.stream.getTracks().forEach(track => track.stop());
        }
    };

    return { isRecording, isTranscribing, startRecording, stopRecording };
}