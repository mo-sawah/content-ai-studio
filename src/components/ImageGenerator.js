import { useState, useEffect, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner, DropdownMenu } from '@wordpress/components';
import CustomSpinner from './common/CustomSpinner';
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
    
    const providerRef = useRef(null);
    const sizeRef = useRef(null);
    const qualityRef = useRef(null);

    const { editPost } = useDispatch('core/editor');
    const { image_provider: defaultProvider } = atm_studio_data;
    const currentProvider = provider || defaultProvider;

    // Custom dropdown component with width matching using popoverProps
    const CustomDropdown = ({ label, text, options, onChange, disabled, helpText }) => {
        const dropdownRef = useRef(null);

        return (
            <div className="atm-dropdown-field" ref={dropdownRef}>
                <label className="atm-dropdown-label">{label}</label>
                <DropdownMenu
                    className="atm-custom-dropdown"
                    icon={chevronDown}
                    text={text}
                    controls={options.map(option => ({
                        title: option.label,
                        onClick: () => {
                            onChange(option);
                        }
                    }))}
                    disabled={disabled}
                    popoverProps={{
                        className: 'atm-popover',
                        style: {
                            '--atm-dropdown-width': dropdownRef.current?.offsetWidth
                                ? dropdownRef.current.offsetWidth + 'px'
                                : 'auto'
                        }
                    }}
                />
                {helpText && <p className="atm-dropdown-help">{helpText}</p>}
            </div>
        );
    };

    // Dropdown options
    const providerOptions = [
        { label: `Use Default (${defaultProvider})`, value: '' },
        { label: 'OpenAI (DALL-E 3)', value: 'openai' },
        { label: 'Google (Imagen 4)', value: 'google' },
        { label: 'BlockFlow (FLUX)', value: 'bfl' }, // <-- ADD THIS LINE

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
        setStatusMessage(''); // Set status to empty during processing
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
                setStatusMessage('âœ… Image generated and set!');
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
                <CustomDropdown
                    label="Image Provider"
                    text={providerLabel || `Use Default (${defaultProvider})`}
                    options={providerOptions}
                    onChange={(option) => {
                        setProvider(option.value);
                        setProviderLabel(option.label);
                    }}
                    disabled={isLoading}
                />

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
                    <CustomDropdown
                        label="Image Size (Override)"
                        text={imageSizeLabel}
                        options={sizeOptions}
                        onChange={(option) => {
                            setImageSize(option.value);
                            setImageSizeLabel(option.label);
                        }}
                        disabled={isLoading}
                    />

                    <CustomDropdown
                        label="Quality (Override)"
                        text={imageQualityLabel}
                        options={qualityOptions}
                        onChange={(option) => {
                            setImageQuality(option.value);
                            setImageQualityLabel(option.label);
                        }}
                        disabled={isLoading}
                        helpText={currentProvider !== 'openai' ? 'Note: Quality setting is only for OpenAI/DALL-E 3.' : ''}
                    />
                </div>

                <Button isPrimary onClick={handleGenerate} disabled={isLoading}>
                    {isLoading ? (
                        <>
                            <CustomSpinner />
                            Processing...
                        </>
                    ) : (
                        'Generate & Set Featured Image'
                    )}
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default ImageGenerator;