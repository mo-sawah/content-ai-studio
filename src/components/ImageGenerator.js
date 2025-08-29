import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner, DropdownMenu } from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

function ImageGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [prompt, setPrompt] = useState('');
    const [imageSize, setImageSize] = useState('');
    const [imageSizeLabel, setImageSizeLabel] = useState('Use Default');
    const [imageQuality, setImageQuality] = useState('');
    const [imageQualityLabel, setImageQualityLabel] = useState('Use Default');
    const [provider, setProvider] = useState('');
    const [providerLabel, setProviderLabel] = useState('');

    const { editPost } = useDispatch('core/editor');
    const { image_provider: defaultProvider } = atm_studio_data;
    const currentProvider = provider || defaultProvider;

    // Dropdown options
    const providerOptions = [
        { label: `Use Default (${defaultProvider})`, value: '' },
        { label: 'OpenAI (DALL-E 3)', value: 'openai' },
        { label: 'Google (Imagen 4)', value: 'google' },
    ];

    const sizeOptions = [
        { label: 'Use Default', value: '' },
        { label: '16:9 Landscape', value: '1792x1024' },
        { label: '1:1 Square', value: '1024x1024' },
        { label: '9:16 Portrait', value: '1024x1792' }
    ];

    const qualityOptions = [
        { label: 'Use Default', value: '' },
        { label: 'Standard', value: 'standard' },
        { label: 'HD', value: 'hd' }
    ];

    const handleGenerate = async () => {
        if (!prompt.trim()) {
            alert('Please enter a prompt for the image.');
            return;
        }

        setIsLoading(true);
        setStatusMessage('Enhancing prompt & generating image...');
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
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                </button>
                <h3>Generate Featured Image</h3>
            </div>

            <div className="atm-form-container">
                {/* Custom Dropdown for Image Provider */}
                <div className="atm-dropdown-field">
                    <label className="atm-dropdown-label">Image Provider</label>
                    <DropdownMenu
                        className="atm-custom-dropdown"
                        icon={chevronDown}
                        label={providerLabel || `Use Default (${defaultProvider})`}
                        controls={providerOptions.map(option => ({
                            title: option.label,
                            onClick: () => {
                                setProvider(option.value);
                                setProviderLabel(option.label);
                            }
                        }))}
                        disabled={isLoading}
                    />
                </div>

                <TextareaControl
                    label="Image Prompt"
                    help="A descriptive prompt is required for image generation."
                    value={prompt}
                    onChange={setPrompt}
                    placeholder="A cinematic, photorealistic image of..."
                    rows="5"
                    disabled={isLoading}
                />

                <div className="atm-grid-2">
                    {/* Custom Dropdown for Image Size */}
                    <div className="atm-dropdown-field">
                        <label className="atm-dropdown-label">Image Size (Override)</label>
                        <DropdownMenu
                            className="atm-custom-dropdown"
                            icon={chevronDown}
                            label={imageSizeLabel}
                            controls={sizeOptions.map(option => ({
                                title: option.label,
                                onClick: () => {
                                    setImageSize(option.value);
                                    setImageSizeLabel(option.label);
                                }
                            }))}
                            disabled={isLoading}
                        />
                    </div>

                    {/* Custom Dropdown for Quality */}
                    <div className="atm-dropdown-field">
                        <label className="atm-dropdown-label">Quality (Override)</label>
                        <DropdownMenu
                            className="atm-custom-dropdown"
                            icon={chevronDown}
                            label={imageQualityLabel}
                            controls={qualityOptions.map(option => ({
                                title: option.label,
                                onClick: () => {
                                    setImageQuality(option.value);
                                    setImageQualityLabel(option.label);
                                }
                            }))}
                            disabled={isLoading}
                        />
                        {currentProvider !== 'openai' && (
                            <p className="atm-dropdown-help">Note: Quality setting is only for OpenAI/DALL-E 3.</p>
                        )}
                    </div>
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