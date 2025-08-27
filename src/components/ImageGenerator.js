// src/components/ImageGenerator.js

import { useState } from '@wordpress/element';
import { Button, TextareaControl, Spinner } from '@wordpress/components';

// Helper to call our existing AJAX endpoints
const callAjax = (action, data) => {
    return jQuery.ajax({
        url: atm_studio_data.ajax_url,
        type: 'POST',
        data: {
            action: action,
            nonce: atm_studio_data.nonce,
            ...data,
        },
    });
};

// Helper to update the Featured Image UI in both editors
const updateFeaturedImageUI = (attachmentId, html) => {
    const isBlockEditor = document.body.classList.contains('block-editor-page');
    if (isBlockEditor) {
        wp.data.dispatch('core/editor').editPost({ featured_media: attachmentId });
    } else {
        // For the Classic Editor, WordPress provides a function to do this
        // but for simplicity and reliability, we can directly replace the HTML.
        jQuery('#postimagediv .inside').html(html);
    }
};

function ImageGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [prompt, setPrompt] = useState('');

    const handleGenerate = async () => {
        setIsLoading(true);
        setStatusMessage('Generating your image...');
        const postId = document.getElementById('atm-studio-root').getAttribute('data-post-id');

        try {
            const response = await callAjax('generate_featured_image', {
                post_id: postId,
                prompt: prompt,
            });

            if (response.success) {
                setStatusMessage('✅ Image generated and set!');
                updateFeaturedImageUI(response.data.attachment_id, response.data.html);
            } else {
                throw new Error(response.data);
            }

        } catch (error) {
            const errorMessage = error.message || 'An unknown error occurred.';
            setStatusMessage(`Error: ${errorMessage}`);
        } finally {
            setIsLoading(false);
            // Clear the status message after a few seconds
            setTimeout(() => setStatusMessage(''), 5000);
        }
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Generate Featured Image</h3>
            </div>

            <div className="atm-form-container">
                <TextareaControl
                    label="Image Prompt"
                    help="Leave empty to automatically generate a prompt based on the article's title and content. You can use shortcodes like [article_title]."
                    value={prompt}
                    onChange={setPrompt}
                    placeholder="A photorealistic image of..."
                    rows="5"
                    disabled={isLoading}
                />

                <Button isPrimary onClick={handleGenerate} disabled={isLoading}>
                    {isLoading ? <Spinner /> : 'Generate & Set Featured Image'}
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default ImageGenerator;