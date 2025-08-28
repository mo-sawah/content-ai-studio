// src/components/CreativeForm.js
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data'; // --- NEW: Import useSelect ---
import { Button, SelectControl, TextControl, TextareaControl, CheckboxControl, Spinner } from '@wordpress/components';

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

function CreativeForm() {
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const [keyword, setKeyword] = useState('');
    const [title, setTitle] = useState('');
    const [writingStyle, setWritingStyle] = useState('default_seo');
    const [articleModel, setArticleModel] = useState('');
    const [wordCount, setWordCount] = useState('');
    const [customPrompt, setCustomPrompt] = useState('');
    const [generateImage, setGenerateImage] = useState(false);

    // --- NEW: Get the savePost function and check if the post is saving ---
    const { savePost } = useDispatch('core/editor');
    const isSaving = useSelect(select => select('core/editor').isSavingPost());
    // --- END NEW ---

    const modelOptions = [ { label: 'Use Default Model', value: '' }, ...Object.entries(atm_studio_data.article_models).map(([value, label]) => ({ label, value })) ];
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
            if (!finalTitle && keyword) {
                setStatusMessage('Generating compelling title...');
                const titleResponse = await callAjax('generate_article_title', { keyword, model: articleModel });
                if (!titleResponse.success) throw new Error(titleResponse.data);
                finalTitle = titleResponse.data.article_title;
            }

            setStatusMessage('Writing article content...');
            const contentResponse = await callAjax('generate_article_content', { post_id: postId, article_title: finalTitle, model: articleModel, writing_style: writingStyle, custom_prompt: customPrompt, word_count: wordCount });
            if (!contentResponse.success) throw new Error(contentResponse.data);

            updateEditorContent(finalTitle, contentResponse.data.article_content);
            setStatusMessage('✅ Article content inserted!');

            if (generateImage) {
                // --- NEW: Save the post before generating the image ---
                setStatusMessage('Saving post...');
                await savePost();
                // --- END NEW ---

                setStatusMessage('Generating featured image...');
                const imageResponse = await callAjax('generate_featured_image', { post_id: postId, prompt: '' }); // Prompt is empty to trigger auto-generation
                if (!imageResponse.success) {
                    alert('Article was generated and saved, but the image failed: ' + imageResponse.data);
                } else {
                    setStatusMessage('✅ All done! Reloading to show new image...');
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }
            }

            setIsLoading(false);
            setTimeout(() => setStatusMessage(''), 3000);
        } catch (error) {
            const errorMessage = error.message || 'An unknown error occurred.';
            setStatusMessage(`Error: ${errorMessage}`);
            setIsLoading(false);
        }
    };

    return (
        <div className="atm-form-container">
            <div className="atm-grid-2">
                <TextControl label="Keyword" value={keyword} onChange={setKeyword} placeholder="e.g., AI in digital marketing" disabled={isLoading || isSaving} />
                <TextControl label="or Article Title" value={title} onChange={setTitle} placeholder="e.g., 5 Ways AI is Revolutionizing Marketing" disabled={isLoading || isSaving} />
            </div>
            <div className="atm-grid-3">
                <SelectControl label="Article Model" value={articleModel} options={modelOptions} onChange={setArticleModel} disabled={isLoading || isSaving} />
                <SelectControl label="Writing Style" value={writingStyle} options={styleOptions} onChange={setWritingStyle} disabled={isLoading || isSaving} />
                <SelectControl label="Article Length" value={wordCount} options={[ { label: 'Default', value: '' }, { label: 'Short (~500 words)', value: '500' }, { label: 'Standard (~800 words)', value: '800' }, { label: 'Medium (~1200 words)', value: '1200' }, { label: 'Long (~2000 words)', value: '2000' } ]} onChange={setWordCount} disabled={isLoading || isSaving} />
            </div>
            <TextareaControl label="Custom Prompt (Optional)" value={customPrompt} onChange={setCustomPrompt} placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will be used instead." rows="6" disabled={isLoading || isSaving} />
            <CheckboxControl label="Also generate a featured image" checked={generateImage} onChange={setGenerateImage} disabled={isLoading || isSaving} />
            <Button isPrimary onClick={handleGenerate} disabled={isLoading || isSaving || (!keyword && !title)}>
                {isLoading || isSaving ? <Spinner /> : 'Generate Creative Article'}
            </Button>
            {statusMessage && <p className="atm-status-message">{statusMessage}</p>}
        </div>
    );
}

export default CreativeForm;