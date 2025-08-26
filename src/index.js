import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextareaControl, Button } from '@wordpress/components';
import { useState, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

import './style.scss';

// --- NEW: Custom SVG Icon ---
const AtmIcon = createElement(
    'svg',
    { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' },
    createElement('path', {
        d: 'M12 4C5.373 4 0 9.373 0 16H2C2 10.477 6.477 6 12 6C17.523 6 22 10.477 22 16H24C24 9.373 18.627 4 12 4Z',
        fill: 'url(#paint0_linear_1_2)'
    }),
    createElement('path', {
        d: 'M12 10C8.686 10 6 12.686 6 16H8C8 13.791 9.791 12 12 12C14.209 12 16 13.791 16 16H18C18 12.686 15.314 10 12 10Z',
        fill: 'url(#paint1_linear_1_2)'
    }),
    createElement('circle', { cx: 12, cy: 16, r: 2, fill: 'url(#paint2_linear_1_2)' }),
    createElement('defs', null,
        createElement('linearGradient', { id: 'paint0_linear_1_2', x1: 12, y1: 4, x2: 12, y2: 16, gradientUnits: 'userSpaceOnUse' },
            createElement('stop', { stopColor: '#8E2DE2' }),
            createElement('stop', { offset: 1, stopColor: '#4A00E0' })
        ),
        createElement('linearGradient', { id: 'paint1_linear_1_2', x1: 12, y1: 10, x2: 12, y2: 16, gradientUnits: 'userSpaceOnUse' },
            createElement('stop', { stopColor: '#8E2DE2' }),
            createElement('stop', { offset: 1, stopColor: '#4A00E0' })
        ),
        createElement('linearGradient', { id: 'paint2_linear_1_2', x1: 12, y1: 14, x2: 12, y2: 18, gradientUnits: 'userSpaceOnUse' },
            createElement('stop', { stopColor: '#8E2DE2' }),
            createElement('stop', { offset: 1, stopColor: '#4A00E0' })
        )
    )
);


const AtmSidebar = () => {
    const [prompt, setPrompt] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const { insertBlocks } = useDispatch(blockEditorStore);
    
    // --- FIX: Use useSelect to correctly get the post ID ---
    const postId = useSelect( ( select ) => {
        return select('core/editor').getCurrentPostId();
    }, [] );

    const generateImage = () => {
        if (!prompt) {
            alert('Please enter a prompt.');
            return;
        }
        setIsLoading(true);

        apiFetch({
            path: '/atm/v1/generate-inline-image',
            method: 'POST',
            data: { 
                prompt: prompt,
                post_id: postId, // Use the correctly fetched post ID
             },
        }).then((response) => {
            const imageBlock = createBlock('core/image', {
                url: response.url,
                alt: response.alt,
            });
            insertBlocks(imageBlock);
            setPrompt(''); 
            setIsLoading(false);
        }).catch((error) => {
            alert(`Error: ${error.message}`);
            setIsLoading(false);
        });
    };

    return (
        <>
            <PluginSidebarMoreMenuItem target="atm-sidebar">
                {__('Article To Media', 'article-to-media')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="atm-sidebar"
                title={__('Article To Media', 'article-to-media')}
            >
                <PanelBody title={__('Generate Inline Image', 'article-to-media')}>
                    <p className="atm-sidebar-desc">
                        Generate an image and insert it directly into your content. You can use shortcodes like <code>[article_title]</code>.
                    </p>
                    <TextareaControl
                        label={__('Image Prompt', 'article-to-media')}
                        value={prompt}
                        onChange={(value) => setPrompt(value)}
                        disabled={isLoading}
                    />
                    <Button
                        isPrimary
                        isBusy={isLoading}
                        onClick={generateImage}
                        disabled={isLoading || !prompt}
                    >
                        {isLoading ? __('Generating...', 'article-to-media') : __('Generate & Insert Image', 'article-to-media')}
                    </Button>
                </PanelBody>
            </PluginSidebar>
        </>
    );
};

registerPlugin('article-to-media-sidebar', {
    icon: AtmIcon, // Use our new custom color icon
    render: AtmSidebar,
});