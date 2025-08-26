import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextareaControl, Button } from '@wordpress/components';
import { useState, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as noticesStore } from '@wordpress/notices';
import { createBlock } from '@wordpress/blocks';

import './style.scss';

// Custom SVG Icon
const AtmIcon = createElement('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' },
    createElement('defs', null,
        createElement('linearGradient', { id: 'atm-grad', x1: '0', y1: '0', x2: '24', y2: '24', gradientUnits: 'userSpaceOnUse' },
            createElement('stop', { stopColor: '#8E2DE2' }),
            createElement('stop', { offset: 1, stopColor: '#4A00E0' })
        )
    ),
    createElement('rect', { x: 2, y: 13, width: 20, height: 9, rx: 2, fill: 'url(#atm-grad)', opacity: 0.6 }),
    createElement('path', { d: 'M6 16H18 M6 18H15', stroke: 'white', strokeWidth: 1.2, strokeLinecap: 'round' }),
    createElement('rect', { x: 2, y: 8, width: 20, height: 9, rx: 2, fill: 'url(#atm-grad)', opacity: 0.8 }),
    createElement('path', { d: 'M6 12.5H8L10 11L12 14L14 11L16 12.5H18', stroke: 'white', strokeWidth: 1.2, strokeLinecap: 'round', strokeLinejoin: 'round' }),
    createElement('rect', { x: 2, y: 3, width: 20, height: 9, rx: 2, fill: 'url(#atm-grad)' }),
    createElement('circle', { cx: 8, cy: 7, r: 1, fill: 'white' }),
    createElement('path', { d: 'M6 10L9 8L13 9.5L18 7', stroke: 'white', strokeWidth: 1.2, strokeLinecap: 'round', strokeLinejoin: 'round' })
);

const AtmSidebar = () => {
    const [inlinePrompt, setInlinePrompt] = useState('');
    const [featuredPrompt, setFeaturedPrompt] = useState('');
    const [isInlineLoading, setIsInlineLoading] = useState(false);
    const [isFeaturedLoading, setIsFeaturedLoading] = useState(false);

    const { insertBlocks } = useDispatch(blockEditorStore);
    const { createSuccessNotice, createErrorNotice } = useDispatch(noticesStore);
    const { editPost } = useDispatch('core/editor'); // Get the editPost dispatcher
    
    const postId = useSelect( ( select ) => {
        return select('core/editor').getCurrentPostId();
    }, [] );

    const generateFeaturedImage = () => {
        if (!featuredPrompt) {
            alert('Please enter a prompt for the featured image.');
            return;
        }
        setIsFeaturedLoading(true);

        apiFetch({
            path: '/atm/v1/generate-featured-image',
            method: 'POST',
            data: { 
                prompt: featuredPrompt,
                post_id: postId,
             },
        }).then((response) => {
            // Update the editor state directly with the new featured media ID
            editPost({ featured_media: response.featured_media_id });
            
            createSuccessNotice('Featured image has been set!', { type: 'snackbar' });
            setIsFeaturedLoading(false);
            setFeaturedPrompt(''); // Clear the prompt after success
        }).catch((error) => {
            createErrorNotice(`Error: ${error.message}`, { type: 'snackbar' });
            setIsFeaturedLoading(false);
        });
    };

    const generateInlineImage = () => {
        if (!inlinePrompt) {
            alert('Please enter a prompt for the inline image.');
            return;
        }
        setIsInlineLoading(true);

        apiFetch({
            path: '/atm/v1/generate-inline-image',
            method: 'POST',
            data: { 
                prompt: inlinePrompt,
                post_id: postId,
             },
        }).then((response) => {
            const imageBlock = createBlock('core/image', {
                url: response.url,
                alt: response.alt,
            });
            insertBlocks(imageBlock);
            setInlinePrompt(''); 
            setIsInlineLoading(false);
            createSuccessNotice('Inline image generated and inserted!', { type: 'snackbar' });
        }).catch((error) => {
            createErrorNotice(`Error: ${error.message}`, { type: 'snackbar' });
            setIsInlineLoading(false);
        });
    };

    return (
        <>
            <PluginSidebarMoreMenuItem target="atm-sidebar">
                {__('Content AI Studio', 'article-to-media')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="atm-sidebar"
                title={__('Content AI Studio', 'article-to-media')}
            >
                <PanelBody title={__('Generate Featured Image', 'article-to-media')} initialOpen={true}>
                    <p className="atm-sidebar-desc">
                        Generate and set the post's main featured image. You can use shortcodes like <code>[article_title]</code>.
                    </p>
                    <TextareaControl
                        label={__('Image Prompt', 'article-to-media')}
                        value={featuredPrompt}
                        onChange={(value) => setFeaturedPrompt(value)}
                        disabled={isFeaturedLoading}
                        placeholder="A photorealistic image of..."
                    />
                    <Button
                        isPrimary
                        isBusy={isFeaturedLoading}
                        onClick={generateFeaturedImage}
                        disabled={isFeaturedLoading || !featuredPrompt}
                    >
                        {isFeaturedLoading ? __('Generating...', 'article-to-media') : __('Generate & Set Image', 'article-to-media')}
                    </Button>
                </PanelBody>

                <PanelBody title={__('Generate Inline Image', 'article-to-media')} initialOpen={false}>
                    <p className="atm-sidebar-desc">
                        Generate an image and insert it directly into your content.
                    </p>
                    <TextareaControl
                        label={__('Image Prompt', 'article-to-media')}
                        value={inlinePrompt}
                        onChange={(value) => setInlinePrompt(value)}
                        disabled={isInlineLoading}
                    />
                    <Button
                        isPrimary
                        isBusy={isInlineLoading}
                        onClick={generateInlineImage}
                        disabled={isInlineLoading || !inlinePrompt}
                    >
                        {isInlineLoading ? __('Generating...', 'article-to-media') : __('Generate & Insert Image', 'article-to-media')}
                    </Button>
                </PanelBody>
            </PluginSidebar>
        </>
    );
};

registerPlugin('content-ai-studio-sidebar', {
    icon: AtmIcon,
    render: AtmSidebar,
});