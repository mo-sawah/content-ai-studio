import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextareaControl, Spinner, DropdownMenu, MenuItem } from '@wordpress/components';

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

    // Define options for our dropdowns
    const providerOptions = [
        { label: `Use Default (${defaultProvider})`, value: '' },
        { label: 'OpenAI (DALL-E 3)', value: 'openai' },
        { label: 'Google (Imagen 4)', value: 'google' },
    ];
    const sizeOptions = [
        { label: 'Use Default', value: '' },
        { label: '16:9 Landscape', value: '1792x1024' },
        { label: '1:1 Square', value: '1024x1024' },
        { label: '9:16 Portrait', value: '1024x1792' },
    ];
    const qualityOptions = [
        { label: 'Use Default', value: '' },
        { label: 'Standard', value: 'standard' },
        { label: 'HD', value: 'hd' },
    ];

    // Helper to find the label for the currently selected value
    const findLabel = (options, value) => (options.find(opt => opt.value === value) || options[0]).label;

    const handleGenerate = async () => {
        // ... (this function remains the same)
    };

    return (
        <div className="atm-generator-view">
            {/* Header remains the same */}
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Generate Featured Image</h3>
            </div>

            <div className="atm-form-container">
                <DropdownMenu
                    label="Image Provider"
                    toggleProps={{ children: findLabel(providerOptions, provider), isPrimary: false, isSecondary: true }}
                    controls={providerOptions.map((opt) => ({
                        title: opt.label,
                        onClick: () => setProvider(opt.value),
                        isActive: provider === opt.value,
                    }))}
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
                    <DropdownMenu
                        label="Image Size (Override)"
                        toggleProps={{ children: findLabel(sizeOptions, imageSize), isPrimary: false, isSecondary: true }}
                        controls={sizeOptions.map((opt) => ({
                            title: opt.label,
                            onClick: () => setImageSize(opt.value),
                            isActive: imageSize === opt.value,
                        }))}
                    />
                    <DropdownMenu
                        label="Quality (Override)"
                        toggleProps={{ children: findLabel(qualityOptions, imageQuality), isPrimary: false, isSecondary: true }}
                        controls={qualityOptions.map((opt) => ({
                            title: opt.label,
                            onClick: () => setImageQuality(opt.value),
                            isActive: imageQuality === opt.value,
                        }))}
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