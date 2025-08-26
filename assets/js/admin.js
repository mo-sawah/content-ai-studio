jQuery(document).ready(function($) {
    let isGenerating = false;

    // Handle the new "Generate Script" button
$('#atm-generate-script-btn').on('click', function() {
    const button = $(this);
    const textSpan = button.find('.atm-btn-text');
    const spinner = button.find('.atm-spinner');
    const originalText = textSpan.text();
    const editorContent = wp.data.select('core/editor').getEditedPostContent();
    const postId = wp.data.select('core/editor').getCurrentPostId();
    const language = $('#atm-language-select').val();

    if (!editorContent.trim()) {
        alert('Please write some content in the editor before generating a script.');
        return;
    }

    button.prop('disabled', true);
    textSpan.text('Generating...');
    spinner.show();
    button.css('display', 'inline-flex'); // Ensure flex alignment for spinner

    $.ajax({
        url: atm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'generate_podcast_script',
            content: editorContent,
            post_id: postId,
            language: language,
            nonce: atm_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                $('#atm-podcast-script').val(response.data.script).height(250);
            } else {
                alert('Error: ' . response.data);
            }
        },
        error: function() {
            alert('An unknown error occurred while generating the script.');
        },
        complete: function() {
            button.prop('disabled', false);
            textSpan.text(originalText);
            spinner.hide();
        }
    });
});

// --- Update the main "Generate Podcast" button handler ---
// Remove the old one and replace it with this:
$('#atm-generate-podcast-btn').on('click', function() {
    if (isGenerating) return;
    const button = $(this);
    const postId = button.data("post-id");
    const script = $("#atm-podcast-script").val();

    if (!script.trim()) {
        alert("Please generate a script before creating the podcast.");
        return;
    }

    isGenerating = true;
    button.prop("disabled", true).html('<div class="atm-spinner"></div> Creating podcast...');

    const voice = $("#atm-voice-select").val();

    $.ajax({
        url: atm_ajax.ajax_url, type: "POST",
        data: {
            action: "generate_podcast",
            post_id: postId,
            script: script, // Send the script from the textarea
            voice: voice,
            nonce: atm_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                button.html("✅ Success! Refreshing...");
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                alert("Error: " . response.data);
                resetButton(button, 'Generate Podcast');
            }
        },
        error: function() {
            alert("An error occurred.");
            resetButton(button, 'Generate Podcast');
        },
        complete: function() {
            isGenerating = false;
        }
    });
});

    // In admin.js, the existing 'change' handler will be updated
$('#atm-article-type-select').on('change', function() {
    const selectedType = $(this).val();

    if (selectedType === 'rss_feed') {
        $('#atm-rss-feed-wrapper').slideDown();
        $('#atm-standard-article-wrapper').slideUp();
    } else {
        $('#atm-standard-article-wrapper').slideDown();
        $('#atm-rss-feed-wrapper').slideUp();

        // This is the existing logic for the "Latest News" sub-options
        if (selectedType === 'news') {
            $('#atm-news-source-wrapper').slideDown();
            $('#atm-force-fresh-wrapper').slideDown();
        } else {
            $('#atm-news-source-wrapper').slideUp();
            $('#atm-force-fresh-wrapper').slideUp();
        }
    }
}).trigger('change'); // Trigger on page load to set the correct initial state

// Enhanced RSS handling functions

// Function to handle both fetch and search with enhanced options
function fetchRSSArticles(button, keyword = '', useScraping = false) {
    const resultsContainer = $('#atm-rss-results');
    const originalText = button.html();
    const postId = button.closest('.postbox').find('#post_ID').val();

    button.prop('disabled', true).html('<div class="atm-spinner"></div> ' + 
        (useScraping ? 'Deep searching...' : 'Fetching...'));
    resultsContainer.html('');

    $.ajax({
        url: atm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'fetch_rss_articles',
            keyword: keyword,
            post_id: postId,
            use_scraping: useScraping,
            nonce: atm_ajax.nonce
        },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let html = '<div class="atm-rss-results-header">';
                html += '<p style="margin: 0 0 15px 0; color: #a0aec0; font-size: 13px;">';
                html += 'Found ' + response.data.length + ' articles';
                if (keyword) html += ' matching "' + keyword + '"';
                html += '</p></div>';
                
                html += '<ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 15px;">';
                response.data.forEach(function(article) {
                    const relevanceClass = (article.relevance === 'high') ? 'atm-high-relevance' : '';
                    const relevanceBadge = (article.relevance === 'high') ? 
                        '<span class="atm-relevance-badge">High Match</span>' : '';
                    
                    html += `<li class="atm-rss-item ${relevanceClass}" style="padding: 15px; background: #2d3748; border-radius: 6px; border: 1px solid #4a5568; position: relative;">
                                ${relevanceBadge}
                                <div class="atm-article-title" style="font-weight: 600; margin-bottom: 8px; line-height: 1.3;">${article.title}</div>
                                <div class="atm-article-meta" style="font-size: 12px; color:#a0aec0; margin-bottom: 8px;">${article.source} | ${article.date}</div>
                                <div class="atm-article-excerpt" style="font-size: 13px; color: #cbd5e1; margin-bottom: 12px; line-height: 1.4;">${(article.description || '').substring(0, 150)}...</div>
                                <div class="atm-article-actions">
                                    <button class="atm-button atm-button-small atm-primary generate-from-rss-btn" 
                                            data-url="${article.link}" 
                                            data-title="${escapeHtml(article.title)}" 
                                            data-guid="${article.guid}"
                                            data-content="${escapeHtml(article.content || article.description || '')}">
                                        Generate Article
                                    </button>
                                    <a href="${article.link}" target="_blank" rel="noopener" class="atm-button atm-button-small" style="margin-left: 8px; text-decoration: none; color: inherit;">
                                        View Original
                                    </a>
                                </div>
                             </li>`;
                });
                html += '</ul>';
                resultsContainer.html(html);
            } else {
                let errorMsg = 'No articles found.';
                if (keyword) {
                    errorMsg += ' Try different keywords or check if your RSS feeds contain recent content about "' + keyword + '".';
                }
                resultsContainer.html('<div class="atm-error">' + errorMsg + '</div>');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'An error occurred while fetching articles.';
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMsg = xhr.responseJSON.data;
            }
            resultsContainer.html('<div class="atm-error">' + errorMsg + '</div>');
        },
        complete: function() { 
            button.prop('disabled', false).html(originalText); 
        }
    });
}

// Enhanced RSS search with deep content analysis
$('#atm-search-rss-btn').on('click', function() {
    const keyword = $('#atm-rss-keyword').val().trim();
    if (!keyword) {
        alert('Please enter a keyword.');
        return;
    }
    
    // Check if deep search is enabled
    const useDeepSearch = $('#atm-rss-deep-search').is(':checked');
    fetchRSSArticles($(this), keyword, useDeepSearch);
});

// Regular fetch without keyword
$('#atm-fetch-rss-btn').on('click', function() {
    fetchRSSArticles($(this));
});

// Enhanced article generation with full content option
$('#atm-rss-results').on('click', '.generate-from-rss-btn', function() {
    const button = $(this);
    const originalText = button.html();
    button.prop('disabled', true).html('<div class="atm-spinner"></div> Generating...');
    const postId = button.closest('.postbox').find('#post_ID').val();
    
    // Check if full content scraping should be used
    const useFullContent = $('#atm-rss-use-full-content').is(':checked');

    $.ajax({
        url: atm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'generate_article_from_rss',
            article_url: button.data('url'),
            article_title: button.data('title'),
            article_guid: button.data('guid'),
            rss_content: button.data('content'),
            use_full_content: useFullContent,
            post_id: postId,
            nonce: atm_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                // Update the post editor
                wp.data.dispatch('core/editor').editPost({ title: response.data.article_title });
                const htmlContent = marked.parse(response.data.article_content);
                const blocks = wp.blocks.parse(htmlContent);
                const currentBlocks = wp.data.select('core/block-editor').getBlocks();
                wp.data.dispatch('core/block-editor').removeBlocks(currentBlocks.map(b => b.clientId));
                wp.data.dispatch('core/block-editor').insertBlocks(blocks);

                button.html('✅ Article Generated').css('background', '#2f855a');
                
                // Optionally hide the item after successful generation
                setTimeout(() => {
                    button.closest('li').fadeOut(1000);
                }, 2000);
            } else {
                alert('Error: ' + response.data);
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'An error occurred during article generation.';
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMsg = 'Error: ' + xhr.responseJSON.data;
            }
            alert(errorMsg);
            button.prop('disabled', false).html(originalText);
        }
    });
});

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

    function fetchHeadlines(button, keyword = '') {
        const resultsContainer = $('#atm-google-news-results');
        const country = $('#atm-google-news-country').val();
        const language = $('#atm-google-news-language').val();
        const originalText = button.html();

        button.prop('disabled', true).html('<div class="atm-spinner"></div> Fetching...');
        resultsContainer.html('');

        $.ajax({
            url: atm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_google_news_headlines',
                country: country,
                language: language,
                keyword: keyword,
                nonce: atm_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 15px;">';
                    response.data.forEach(function(article) {
                        // THE FIX: Display the date next to the source
                        html += `<li style="padding: 15px; background: #1a202c; border-radius: 6px;">
                                    <div style="margin-bottom: 10px; font-weight: 600;">${article.title} - <em style="color:#a0aec0; font-weight: 400;">(${article.source} | ${article.date})</em></div>
                                    <button class="atm-button atm-button-small atm-primary generate-from-headline-btn" data-url="${article.link}" data-title="${escape(article.title)}">Generate Article</button>
                                 </li>`;
                    });
                    html += '</ul>';
                    resultsContainer.html(html);
                } else {
                    resultsContainer.html('<div class="atm-error">No high-quality headlines found. Please try a different keyword.</div>');
                }
            },
            error: function() {
                 resultsContainer.html('<div class="atm-error">An error occurred while fetching news.</div>');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    }

    // 1. Fetch Top 5 Headlines
    $('#atm-fetch-headlines-btn').on('click', function() {
        fetchHeadlines($(this));
    });

    // 2. Fetch News by Keyword
    $('#atm-fetch-keyword-news-btn').on('click', function() {
        const keyword = $('#atm-google-news-keyword').val().trim();
        if (!keyword) {
            alert('Please enter a keyword to search for.');
            return;
        }
        fetchHeadlines($(this), keyword);
    });

    // 3. Generate Article from a specific headline
    $('#atm-google-news-results').on('click', '.generate-from-headline-btn', function() {
        const button = $(this);
        const articleUrl = button.data('url');
        const articleTitle = unescape(button.data('title'));
        const language = $('#atm-google-news-language').val(); // Get selected language

        button.prop('disabled', true).html('<div class="atm-spinner"></div> Generating...');
        
        $.ajax({
            url: atm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_article_from_url',
                article_url: articleUrl,
                article_title: articleTitle,
                language: language, // Send language
                nonce: atm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const markdownContent = response.data.article_content;
                    const htmlContent = marked.parse(markdownContent);
                    let blocks = wp.blocks.parse(htmlContent);

                    wp.data.dispatch('core/editor').editPost({ title: response.data.article_title });

                    const currentBlocks = wp.data.select('core/block-editor').getBlocks();
                    const clientIds = currentBlocks.map(block => block.clientId);
                    wp.data.dispatch('core/block-editor').removeBlocks(clientIds);
                    wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                    
                    button.html('✅ Inserted').css('background', '#2f855a');
                } else {
                     alert('Error: ' + response.data);
                     button.prop('disabled', false).html('Generate Article');
                }
            },
            error: function() {
                alert('An unknown error occurred during article generation.');
                button.prop('disabled', false).html('Generate Article');
            }
        });
    });

    // --- Generate Article Button ---
    $('#atm-generate-article-btn').on('click', function() {
        if (isGenerating) return;
        const button = $(this);
        const postId = button.data("post-id");
        generateArticle(button, postId);
    });

    // Show/hide the News options based on the Article Type
    $('#atm-article-type-select').on('change', function() {
        if ($(this).val() === 'news') {
            $('#atm-news-source-wrapper').slideDown();
            $('#atm-force-fresh-wrapper').slideDown();
        } else {
            $('#atm-news-source-wrapper').slideUp();
            $('#atm-force-fresh-wrapper').slideUp();
        }
    }).trigger('change'); // Trigger on page load

    // --- Generate Image Button ---
    $('#atm-generate-image-btn').on('click', function() {
        if (isGenerating) return;
        const button = $(this);
        const postId = button.data("post-id");
        generateImage(button, postId);
    });

    // --- Generate Podcast Button ---
    $('#atm-generate-podcast-btn').on('click', function() {
        if (isGenerating) return;
        const button = $(this);
        const postId = button.data("post-id");
        generatePodcast(button, postId);
    });

    // Regenerate button
    $('.atm-regenerate').on('click', function() {
        if (isGenerating) return;
        const button = $(this);
        const postId = button.data("post-id");
        generatePodcast(button, postId);
    });

    // Image uploader for podcast cover
    $(document).on("click", ".atm-upload-image, .atm-change-image", function(e) {
        e.preventDefault();
        const button = $(this);
        const postId = button.data("post-id");
        const mediaUploader = wp.media({
            title: "Select Podcast Cover Image",
            button: { text: "Use This Image" },
            multiple: false, library: { type: "image" }
        });
        mediaUploader.on("select", function() {
            const attachment = mediaUploader.state().get("selection").first().toJSON();
            $.ajax({
                url: atm_ajax.ajax_url, type: "POST",
                data: { action: "upload_podcast_image", post_id: postId, image_url: attachment.url, nonce: atm_ajax.nonce },
                success: function(response) { if (response.success) { location.reload(); } }
            });
        });
        mediaUploader.open();
    });
    
    /**
     * Article Generation with conditional logic for user-provided titles and article types.
     */
    function generateArticle(button, postId) {
        isGenerating = true;
        button.prop("disabled", true);

        const articleType = $('#atm-article-type-select').val();
        const newsSource = $('#atm-news-source-select').val(); // Get the selected news source
        const forceFresh = $('#atm-force-fresh-search').is(':checked');
        const keyword = $('#atm-article-keyword').val().trim();
        const title = $('#atm-article-title-input').val().trim();
        const model = $('#atm-article-model-select').val();
        const writingStyle = $('#atm-writing-style-select').val();
        const customPrompt = $('#atm-custom-article-prompt').val().trim();
        const wordCount = $('#atm-word-count-select').val();
        
        const topic = title || keyword;
        if (!topic) {
            alert('Please provide a keyword or an article title.');
            resetButton(button, 'Generate Article');
            isGenerating = false;
            return;
        }

        if (articleType === 'news') {
            button.html('<div class="atm-spinner"></div> Searching for news...');
            
            $.ajax({
                url: atm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_news_article',
                    topic: topic,
                    model: model,
                    force_fresh: forceFresh,
                    news_source: newsSource, // Send the selected news source
                    nonce: atm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // ... (rest of the success function is the same)
                        button.html('<div class="atm-spinner"></div> Writing article...');
                        const finalTitle = response.data.article_title;
                        const markdownContent = response.data.article_content;
                        const htmlContent = marked.parse(markdownContent);
                        let blocks = wp.blocks.parse(htmlContent);

                        wp.data.dispatch('core/editor').editPost({ title: finalTitle });

                        const currentBlocks = wp.data.select('core/block-editor').getBlocks();
                        const clientIds = currentBlocks.map(block => block.clientId);
                        wp.data.dispatch('core/block-editor').removeBlocks(clientIds);
                        wp.data.dispatch('core/block-editor').insertBlocks(blocks);

                        button.html("✅ News Article Inserted!");
                        setTimeout(() => resetButton(button, 'Generate Article'), 2000);
                    } else {
                        alert("Error: " + response.data);
                        resetButton(button, 'Generate Article');
                    }
                },
                error: function() {
                    alert("An error occurred during news generation.");
                    resetButton(button, 'Generate Article');
                },
                complete: function() {
                    isGenerating = false;
                }
            });

        } else {
            // ... (creative article logic remains the same)
            if (title) {
                button.html('<div class="atm-spinner"></div> Generating Content...');
                generateContentForTitle(title, postId, button, model, writingStyle, customPrompt, wordCount, articleType);
            } else if (keyword) {
                button.html('<div class="atm-spinner"></div> Generating Title...');
                generateTitleFromKeyword(keyword, postId, button, model, writingStyle, customPrompt, wordCount, articleType);
            }
        }
    }

    function generateTitleFromKeyword(keyword, postId, button, model, writingStyle, customPrompt, wordCount, articleType) {
        $.ajax({
            url: atm_ajax.ajax_url,
            type: "POST",
            data: {
                action: "generate_article_title",
                post_id: postId,
                keyword: keyword,
                title: '',
                model: model, 
                article_type: articleType, // Add article type here
                nonce: atm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const generatedTitle = response.data.article_title;
                    button.html('<div class="atm-spinner"></div> Generating Content...');
                    generateContentForTitle(generatedTitle, postId, button, model, writingStyle, customPrompt, wordCount, articleType); 
                } else {
                    alert("Error generating title: " + response.data);
                    resetButton(button, 'Generate Article');
                    isGenerating = false;
                }
            },
            error: function() {
                alert("An error occurred during title generation.");
                resetButton(button, 'Generate Article');
                isGenerating = false;
            }
        });
    }

    function generateContentForTitle(finalTitle, postId, button, model, writingStyle, customPrompt, wordCount, articleType) {
        $.ajax({
            url: atm_ajax.ajax_url,
            type: "POST",
            data: {
                action: "generate_article_content",
                post_id: postId,
                article_title: finalTitle,
                model: model,
                writing_style: writingStyle,
                custom_prompt: customPrompt,
                word_count: wordCount,
                article_type: articleType, // Add article type here
                nonce: atm_ajax.nonce
            },
            success: function(contentResponse) {
                if (contentResponse.success) {
                    const markdownContent = contentResponse.data.article_content;
                    const htmlContent = marked.parse(markdownContent);
                    let blocks = wp.blocks.parse(htmlContent);

                    wp.data.dispatch('core/editor').editPost({ title: finalTitle });

                    const currentBlocks = wp.data.select('core/block-editor').getBlocks();
                    const clientIds = currentBlocks.map(block => block.clientId);
                    wp.data.dispatch('core/block-editor').removeBlocks(clientIds);
                    wp.data.dispatch('core/block-editor').insertBlocks(blocks);

                    // --- NEW LOGIC FOR COMBINED GENERATION ---
                    const generateImageAfter = $('#atm-generate-image-with-article').is(':checked');

                    if (generateImageAfter) {
                        button.html('<div class="atm-spinner"></div> Saving & Generating Image...');
                        
                        // Save the post so the backend can read the new title/content
                        wp.data.dispatch('core/editor').savePost();

                        // Wait a moment for the save to complete before firing the image request
                        setTimeout(function() {
                            $.ajax({
                                url: atm_ajax.ajax_url,
                                type: "POST",
                                data: {
                                    action: "generate_featured_image",
                                    post_id: postId,
                                    prompt: '', // Send an empty prompt to trigger the default logic
                                    nonce: atm_ajax.nonce
                                },
                                success: function(imgResponse) {
                                    if (imgResponse.success) {
                                        button.html("✅ All Done! Refreshing...");
                                        setTimeout(() => window.location.reload(), 2000);
                                    } else {
                                        alert("Article was created, but image generation failed: " + imgResponse.data);
                                        resetButton(button, 'Generate Article');
                                    }
                                },
                                error: function() {
                                    alert("Article was created, but an error occurred during image generation.");
                                    resetButton(button, 'Generate Article');
                                }
                            });
                        }, 2500); // 2.5 second delay

                    } else {
                        // Original success logic if checkbox is not checked
                        button.html("✅ Content Inserted!");
                        setTimeout(() => resetButton(button, 'Generate Article'), 2000);
                    }
                } else {
                    alert("Error generating content: " + contentResponse.data);
                    resetButton(button, 'Generate Article');
                }
            },
            error: function() {
                alert("An error occurred during content generation.");
                resetButton(button, 'Generate Article');
            },
            complete: function() {
                isGenerating = false;
            }
        });
    }

    function generateImage(button, postId) {
        isGenerating = true;
        button.prop("disabled", true).html('<div class="atm-spinner"></div> Generating Image...');

        const prompt = $('#atm-image-prompt').val();

        $.ajax({
            url: atm_ajax.ajax_url,
            type: "POST",
            data: {
                action: "generate_featured_image",
                post_id: postId,
                prompt: prompt,
                nonce: atm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.html("✅ Image Set! Refreshing...");
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    alert("Error: " + response.data);
                    resetButton(button, 'Generate & Set Featured Image');
                }
            },
            error: function() {
                alert("An error occurred during image generation.");
                resetButton(button, 'Generate & Set Featured Image');
            },
            complete: function() {
                isGenerating = false;
            }
        });
    }

    function generatePodcast(button, postId) {
        isGenerating = true;
        button.prop("disabled", true).html('<div class="atm-spinner"></div> Creating podcast...');
        const section = button.closest(".atm-section");
        if (!section.find(".atm-progress-bar").length) {
            section.append('<div class="atm-progress-bar"><div class="atm-progress-fill"></div></div>');
        }
        
        const language = $("#atm-language-select").val();
        const voice = $("#atm-voice-select").val();
        const model = $("#atm-content-model-select").val();
        const customPrompt = $("#atm-custom-prompt").val();
        
        $.ajax({
            url: atm_ajax.ajax_url, type: "POST",
            data: {
                action: "generate_podcast",
                post_id: postId,
                language: language,
                voice: voice,
                model: model,
                custom_prompt: customPrompt,
                nonce: atm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.html("✅ Success! Refreshing...");
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    alert("Error: " + response.data);
                    resetButton(button, 'Generate Podcast');
                }
            },
            error: function() {
                alert("An error occurred.");
                resetButton(button, 'Generate Podcast');
            },
            complete: function() {
                isGenerating = false;
            }
        });
    }
    
    function resetButton(button, text) {
        button.prop("disabled", false).html(text);
        button.closest(".atm-section").find(".atm-progress-bar").remove();
    }

});

// Make testRSSFeed globally available
window.testRSSFeed = function(feedUrl, keyword = '') {
    if (!feedUrl.trim()) {
        alert('Please enter a valid RSS feed URL.');
        return;
    }
    
    jQuery('#atm-rss-test-results').html('<div class="atm-spinner"></div> Testing feed...');
    
    jQuery.ajax({
        url: atm_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'test_rss_feed',
            feed_url: feedUrl,
            keyword: keyword,
            nonce: atm_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                let html = '<div class="atm-success">✅ Feed is working!</div>';
                html += '<p><strong>Total articles found:</strong> ' + data.total_found + '</p>';
                
                if (data.articles.length > 0) {
                    html += '<h4>Sample Articles:</h4>';
                    html += '<ul>';
                    data.articles.slice(0, 3).forEach(function(article) {
                        html += '<li><strong>' + article.title + '</strong><br>';
                        html += '<small>' + article.source + ' | ' + article.date + '</small></li>';
                    });
                    html += '</ul>';
                }
                
                jQuery('#atm-rss-test-results').html(html);
            } else {
                jQuery('#atm-rss-test-results').html('<div class="atm-error">❌ ' + response.data + '</div>');
            }
        },
        error: function() {
            jQuery('#atm-rss-test-results').html('<div class="atm-error">❌ Connection error while testing feed.</div>');
        }
    });
};