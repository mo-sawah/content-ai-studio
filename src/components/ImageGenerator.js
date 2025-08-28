import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner, SelectControl } from '@wordpress/components';

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

function ImageGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [prompt, setPrompt] = useState('');
    const [imageSize, setImageSize] = useState('');
    const [imageQuality, setImageQuality] = useState('');
    const [provider, setProvider] = useState('');

    const { editPost } = useDispatch('core/editor');
    const { image_provider: defaultProvider } = atm_studio_data;
    const currentProvider = provider || defaultProvider;

    const handleGenerate = async () => {
        setIsLoading(true);
        setStatusMessage('Generating your image...');
        const postId = document.getElementById('atm-studio-root').getAttribute('data-post-id');

        try {
            const response = await callAjax('generate_featured_image', {
                post_id: postId,
                prompt: prompt,
                size: imageSize,
                quality: imageQuality,
                provider: currentProvider,
            });

            if (response.success) {
                setStatusMessage('✅ Image generated and set!');
                const { attachment_id, html } = response.data;
                const isBlockEditor = document.body.classList.contains('block-editor-page');

                if (isBlockEditor) {
                    editPost({ featured_media: attachment_id });
                } else {
                    jQuery('#postimagediv .inside').html(html);
                }
            } else {
                throw new Error(response.data);
            }

        } catch (error) {
            const errorMessage = error.message || 'An unknown error occurred.';
            setStatusMessage(`Error: ${errorMessage}`);
        } finally {
            setIsLoading(false);
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
                <SelectControl
                    label="Image Provider"
                    value={provider}
                    onChange={setProvider}
                    options={[
                        { label: `Use Default (${defaultProvider})`, value: '' },
                        { label: 'OpenAI (DALL-E 3)', value: 'openai' },
                        { label: 'Fal.ai (Imagen 4)', value: 'imagen4' },
                    ]}
                    disabled={isLoading}
                />

                <TextareaControl
                    label="Image Prompt"
                    help="Leave empty for an automatic prompt based on your article's content."
                    value={prompt}
                    onChange={setPrompt}
                    placeholder="A photorealistic image of..."
                    rows="5"
                    disabled={isLoading}
                />

                <div className="atm-grid-2">
                    <SelectControl
                        label="Image Size (Override)"
                        value={imageSize}
                        onChange={setImageSize}
                        options={[ { label: 'Use Default', value: '' }, { label: '16:9 Landscape', value: '1792x1024' }, { label: '1:1 Square', value: '1024x1024' }, { label: '9:16 Portrait', value: '1024x1792' } ]}
                        disabled={isLoading}
                    />
                    <SelectControl
                        label="Quality (Override)"
                        value={imageQuality}
                        onChange={setImageQuality}
                        options={[ { label: 'Use Default', value: '' }, { label: 'Standard', value: 'standard' }, { label: 'HD', value: 'hd' } ]}
                        disabled={isLoading}
                        help={currentProvider !== 'openai' ? 'Note: Quality setting is only for OpenAI/DALL-E 3.' : ''}
                    />
                </div>

                <Button isPrimary onClick={handleGenerate} disabled={isLoading}>
                    {isLoading ? <Spinner /> : 'Generate & Set Featured Image'}
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default ImageGenerator;