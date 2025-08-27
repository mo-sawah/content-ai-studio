// src/components/NewsForm.js
import { useState } from '@wordpress/element';
import { Button, TextControl, CheckboxControl, Spinner, SelectControl } from '@wordpress/components';

// We can move these helpers to a shared file later
const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });
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

function NewsForm() {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [topic, setTopic] = useState('');
    const [newsSource, setNewsSource] = useState('newsapi');
    const [forceFresh, setForceFresh] = useState(false);
    const [generateImage, setGenerateImage] = useState(false);

    const handleGenerate = async () => {
        if (!topic) {
            alert('Please enter a topic for the news article.');
            return;
        }
        setIsLoading(true);
        setStatusMessage('Searching for latest news...');
        const postId = document.getElementById('atm-studio-root').getAttribute('data-post-id');

        try {
            const response = await callAjax('generate_news_article', {
                topic,
                news_source: newsSource,
                force_fresh: forceFresh,
            });

            if (!response.success) throw new Error(response.data);

            setStatusMessage('Writing article...');
            updateEditorContent(response.data.article_title, response.data.article_content);
            setStatusMessage('✅ News article inserted!');

            if (generateImage) {
                setStatusMessage('Generating featured image...');
                const imageResponse = await callAjax('generate_featured_image', { post_id: postId, prompt: '' });
                if (!imageResponse.success) {
                    alert('Article was generated, but the image failed: ' + imageResponse.data);
                } else {
                    setStatusMessage('✅ All done! Reloading to show image...');
                    setTimeout(() => window.location.reload(), 2000);
                    return;
                }
            }

            setIsLoading(false);
            setTimeout(() => setStatusMessage(''), 3000);
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
            setIsLoading(false);
        }
    };

    return (
        <div className="atm-form-container">
            <TextControl
                label="News Topic"
                value={topic}
                onChange={setTopic}
                placeholder="e.g., recent AI developments"
                disabled={isLoading}
            />
            <SelectControl
                label="News Source"
                value={newsSource}
                options={[
                    { label: 'NewsAPI.org', value: 'newsapi' },
                    { label: 'GNews.io', value: 'gnews' },
                    { label: 'The Guardian', value: 'guardian' },
                ]}
                onChange={setNewsSource}
                disabled={isLoading}
            />
            <CheckboxControl
                label="Force fresh search (bypasses cache)"
                checked={forceFresh}
                onChange={setForceFresh}
                disabled={isLoading}
            />
            <CheckboxControl
                label="Also generate a featured image"
                checked={generateImage}
                onChange={setGenerateImage}
                disabled={isLoading}
            />
            <Button isPrimary onClick={handleGenerate} disabled={isLoading || !topic}>
                {isLoading ? <Spinner /> : 'Generate News Article'}
            </Button>
            {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
        </div>
    );
}

export default NewsForm;