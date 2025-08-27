import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl, TextareaControl, CheckboxControl, Spinner } from '@wordpress/components';

// This makes our AJAX calls to the backend
const callAjax = (action, data) => {
    return jQuery.ajax({
        url: atm_studio_data.ajax_url,
        type: 'POST',
        data: {
            action: action, // The action is now passed in full
            nonce: atm_studio_data.nonce,
            ...data,
        },
    });
};

// This function updates the editor content (Classic or Gutenberg)
const updateEditorContent = (title, markdownContent) => {
    const isBlockEditor = document.body.classList.contains('block-editor-page');
    const htmlContent = window.marked ? window.marked.parse(markdownContent) : markdownContent;

    if (isBlockEditor) {
        wp.data.dispatch('core/editor').editPost({ title });
        const blocks = wp.blocks.parse(htmlContent);
        const currentBlocks = wp.data.select('core/block-editor').getBlocks();
        if (currentBlocks.length > 0 && !(currentBlocks.length === 1 && currentBlocks[0].name === 'core/paragraph' && currentBlocks[0].attributes.content === '')) {
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

    // Form state for all our fields
    const [articleType, setArticleType] = useState('creative');
    const [keyword, setKeyword] = useState('');
    const [title, setTitle] = useState('');
    const [writingStyle, setWritingStyle] = useState('default_seo');
    const [articleModel, setArticleModel] = useState(''); // Empty means use default from settings
    const [wordCount, setWordCount] = useState('');
    const [customPrompt, setCustomPrompt] = useState('');
    const [generateImage, setGenerateImage] = useState(false);

    // Prepare options for our Select controls from the data we passed from PHP
    const modelOptions = [
        { label: 'Use Default Model', value: '' },
        ...Object.entries(atm_studio_data.article_models).map(([value, label]) => ({ label, value }))
    ];
    const styleOptions = Object.entries(atm_studio_data.writing_styles).map(([value, { label }]) => ({ label, value }));

    const handleGenerate = async () => {
        setIsLoading(true);

        const postId = document.getElementById('atm-studio-root').getAttribute('data-post-id');
        const topic = title || keyword;

        if (!topic) {
            alert('Please provide a keyword or an article title.');
            setIsLoading(false);
            return;
        }

        try {
            let finalTitle = title;

            // Step 1: Generate title from keyword if no title is provided
            if (!finalTitle && keyword) {
                setStatusMessage('Generating compelling title...');
                const titleResponse = await callAjax('generate_article_title', { keyword, model: articleModel });
                if (!titleResponse.success) throw new Error(titleResponse.data);
                finalTitle = titleResponse.data.article_title;
            }

            // Step 2: Generate the article content
            setStatusMessage('Writing article content...');
            const contentResponse = await callAjax('generate_article_content', {
                post_id: postId,
                article_title: finalTitle,
                model: articleModel,
                writing_style: writingStyle,
                custom_prompt: customPrompt,
                word_count: wordCount
            });

            if (!contentResponse.success) throw new Error(contentResponse.data);

            updateEditorContent(finalTitle, contentResponse.data.article_content);
            setStatusMessage('✅ Article content inserted!');

            // Step 3 (Optional): Generate the featured image
            if (generateImage) {
                setStatusMessage('Generating featured image...');
                const imageResponse = await callAjax('generate_featured_image', { post_id: postId, prompt: '' });

                if (!imageResponse.success) {
                    alert('Article was generated, but the image failed: ' + imageResponse.data);
                } else {
                    setStatusMessage('✅ All done! Reloading to show image...');
                    setTimeout(() => window.location.reload(), 2000);
                    return; // Stop execution here
                }
            }

            // If we reach here, we're done (and not reloading)
            setIsLoading(false);
            setTimeout(() => setStatusMessage(''), 3000);

        } catch (error) {
            // Catch any error from the process
            const errorMessage = error.message || 'An unknown error occurred.';
            setStatusMessage(`Error: ${errorMessage}`);
            setIsLoading(false);
        }
    };

    // For now, we only show the 'creative' fields. We'll add logic for news/rss later.
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
                    options={[ { label: 'Creative Article', value: 'creative' } ]}
                    onChange={(value) => setArticleType(value)}
                    disabled={isLoading}
                />

                <div className="atm-grid-2">
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
                </div>

                <div className="atm-grid-3">
                    <SelectControl
                        label="Article Model"
                        value={articleModel}
                        options={modelOptions}
                        onChange={(value) => setArticleModel(value)}
                        disabled={isLoading}
                    />
                    <SelectControl
                        label="Writing Style"
                        value={writingStyle}
                        options={styleOptions}
                        onChange={(value) => setWritingStyle(value)}
                        disabled={isLoading}
                    />
                    <SelectControl
                        label="Article Length"
                        value={wordCount}
                        options={[
                            { label: 'Default', value: '' },
                            { label: 'Short (~500 words)', value: '500' },
                            { label: 'Standard (~800 words)', value: '800' },
                            { label: 'Medium (~1200 words)', value: '1200' },
                            { label: 'Long (~2000 words)', value: '2000' },
                        ]}
                        onChange={(value) => setWordCount(value)}
                        disabled={isLoading}
                    />
                </div>

                <TextareaControl
                    label="Custom Prompt (Optional)"
                    value={customPrompt}
                    onChange={(value) => setCustomPrompt(value)}
                    placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will be used instead."
                    rows="6"
                    disabled={isLoading}
                />

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