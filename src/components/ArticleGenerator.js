// src/components/ArticleGenerator.js

import { useState, useEffect } from '@wordpress/element';
import { Button, SelectControl, TextControl, TextareaControl, CheckboxControl, Spinner } from '@wordpress/components';

// Helper function to call our existing AJAX endpoints
const callAjax = (action, data) => {
    return jQuery.ajax({
        url: atm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: `atm_${action}`, // Prefixes the action for security
            nonce: atm_ajax.nonce,
            ...data,
        },
    });
};

// Helper to update the editor (re-implemented for React)
const updateEditorContent = (title, markdownContent) => {
    const isBlockEditor = document.body.classList.contains('block-editor-page');
    const htmlContent = window.marked ? window.marked.parse(markdownContent) : markdownContent;

    if (isBlockEditor) {
        wp.data.dispatch('core/editor').editPost({ title });
        const blocks = wp.blocks.parse(htmlContent);
        const currentBlocks = wp.data.select('core/block-editor').getBlocks();
        if (currentBlocks.length) {
            const clientIds = currentBlocks.map(block => block.clientId);
            wp.data.dispatch('core/block-editor').removeBlocks(clientIds);
        }
        wp.data.dispatch('core/block-editor').insertBlocks(blocks);
    } else {
        jQuery('#title').val(title);
        jQuery('#title-prompt-text').hide();
        jQuery('#title').trigger('blur');
        if (window.tinymce && window.tinymce.get('content')) {
            window.tinymce.get('content').setContent(htmlContent);
        } else {
            jQuery('#content').val(htmlContent);
        }
    }
};

function ArticleGenerator({ setActiveView }) {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');

    // Form state
    const [articleType, setArticleType] = useState('creative');
    const [keyword, setKeyword] = useState('');
    const [title, setTitle] = useState('');
    const [generateImage, setGenerateImage] = useState(false);

    const handleGenerate = async () => {
        setIsLoading(true);
        setStatusMessage('Generating...');

        const postId = document.getElementById('atm-studio-root').getAttribute('data-post-id');
        const articleData = {
            post_id: postId,
            keyword: keyword,
            title: title,
            // We will add more fields like writing style, etc. later
        };

        try {
            // This is a simplified example. We'll expand this for news/rss later.
            const response = await callAjax('generate_article_content', { article_title: title || keyword });

            if (response.success) {
                setStatusMessage('Article generated successfully!');
                updateEditorContent(title || keyword, response.data.article_content);

                if (generateImage) {
                    setStatusMessage('Generating featured image...');
                    await callAjax('generate_featured_image', { post_id: postId, prompt: '' });
                    setStatusMessage('All done! Reloading page to show image...');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                   setTimeout(() => {
                       setIsLoading(false);
                       setStatusMessage('');
                   }, 2000);
                }
            } else {
                throw new Error(response.data || 'An unknown error occurred.');
            }
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
            setIsLoading(false);
        }
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Generate Article</h3>
            </div>

            <div className="atm-form-container">
                <SelectControl
                    label="Article Type"
                    value={articleType}
                    options={[
                        { label: 'Creative Article', value: 'creative' },
                        { label: 'Latest News Article', value: 'news' },
                        { label: 'Article from RSS Feed', value: 'rss_feed' },
                    ]}
                    onChange={(value) => setArticleType(value)}
                    disabled={isLoading}
                />

                <TextControl
                    label="Keyword"
                    value={keyword}
                    onChange={(value) => setKeyword(value)}
                    placeholder="e.g., AI in digital marketing"
                    disabled={isLoading}
                />

                <TextControl
                    label="or Article Title"
                    value={title}
                    onChange={(value) => setTitle(value)}
                    placeholder="e.g., 5 Ways AI is Revolutionizing Marketing"
                    disabled={isLoading}
                />

                {/* We will add the other controls (writing style, etc.) in a future step */}

                <CheckboxControl
                    label="Also generate a featured image"
                    checked={generateImage}
                    onChange={setGenerateImage}
                    disabled={isLoading}
                />

                <Button isPrimary onClick={handleGenerate} disabled={isLoading || (!keyword && !title)}>
                    {isLoading ? <Spinner /> : 'Generate Article'}
                </Button>

                {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
            </div>
        </div>
    );
}

export default ArticleGenerator;