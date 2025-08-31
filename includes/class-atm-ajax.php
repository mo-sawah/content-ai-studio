<?php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Ajax {

    public function generate_takeaways() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $post_content = wp_strip_all_tags(get_post_field('post_content', $post_id));

            if (empty($post_content) || strlen($post_content) < 100) {
                throw new Exception('Post content is too short to generate takeaways.');
            }

            $model = get_option('atm_takeaways_model', 'anthropic/claude-3-haiku');

            $system_prompt = 'You are an expert editor. Your task is to read the following article and extract the 3 to 5 most important key takeaways. Each takeaway should be a single, concise sentence.

            **Final Output Format:**
            Your entire output MUST be a single, valid JSON array of strings, where each string is one key takeaway.
            Example: ["Takeaway one is about this.", "Takeaway two covers this topic.", "Takeaway three highlights this conclusion."]'
            ;

            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $post_content], $system_prompt, $model, true);
            $result = json_decode($raw_response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                error_log('ATM Takeaways - Invalid JSON from AI: ' . $raw_response);
                throw new Exception('The AI returned an invalid response. Please try again.');
            }

            update_post_meta($post_id, '_atm_key_takeaways', $result);
            wp_send_json_success(['takeaways' => $result]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function save_takeaways() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $takeaways_text = sanitize_textarea_field(stripslashes($_POST['takeaways']));
            $takeaways_array = array_filter(array_map('trim', explode("\n", $takeaways_text)));

            if (empty($takeaways_array)) {
                delete_post_meta($post_id, '_atm_key_takeaways');
                wp_send_json_success(['message' => 'Takeaways cleared.']);
            } else {
                update_post_meta($post_id, '_atm_key_takeaways', $takeaways_array);
                wp_send_json_success(['message' => 'Takeaways saved successfully.']);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function save_atm_chart() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');

        try {
            $chart_title = sanitize_text_field($_POST['title']);
            $chart_config = wp_kses_post(stripslashes($_POST['chart_config']));
            $chart_id = isset($_POST['chart_id']) ? intval($_POST['chart_id']) : 0;

            if (empty($chart_title) || empty($chart_config)) {
                throw new Exception('Chart title and configuration are required.');
            }

            $post_data = array(
                'post_title'  => $chart_title,
                'post_type'   => 'atm_chart',
                'post_status' => 'publish',
            );

            if ($chart_id > 0) {
                $post_data['ID'] = $chart_id;
                $new_chart_id = wp_update_post($post_data);
            } else {
                $new_chart_id = wp_insert_post($post_data);
            }

            if (is_wp_error($new_chart_id)) {
                throw new Exception($new_chart_id->get_error_message());
            }

            update_post_meta($new_chart_id, '_atm_chart_config', $chart_config);

            wp_send_json_success(['chart_id' => $new_chart_id]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_chart_from_ai() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);

        try {
            $prompt = sanitize_textarea_field(stripslashes($_POST['prompt']));
            if (empty($prompt)) {
                throw new Exception('Prompt cannot be empty.');
            }
            
            $chart_config_json = ATM_API::generate_chart_config_from_prompt($prompt);
            
            wp_send_json_success(['chart_config' => $chart_config_json]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }    

    public function get_youtube_suggestions() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $query = sanitize_text_field($_POST['query']);
            $suggestions = ATM_API::get_youtube_autocomplete_suggestions($query);
            wp_send_json_success($suggestions);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_youtube() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $query = sanitize_text_field($_POST['query']);
            $filters = isset($_POST['filters']) ? (array) $_POST['filters'] : [];
            $results = ATM_API::search_youtube_videos($query);
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }


    public function translate_editor_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        
        // --- ADD THIS LINE ---
        @ini_set('max_execution_time', 300); // Allow up to 5 minutes for translation

        try {
            if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['target_language'])) {
                throw new Exception('Title, content, and target language are required.');
            }

            $title = sanitize_text_field(stripslashes($_POST['title']));
            // Use a more permissive sanitization for content to preserve HTML/Markdown
            $content = wp_kses_post(stripslashes($_POST['content']));
            $target_language = sanitize_text_field($_POST['target_language']);

            $translation_result = ATM_API::translate_document($title, $content, $target_language);

            wp_send_json_success([
                'translated_title' => $translation_result['translated_title'],
                'translated_content' => $translation_result['translated_content']
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function transcribe_audio() {
    if (!ATM_Licensing::is_license_active()) {
        wp_send_json_error('Please activate your license key to use this feature.');
    }

    check_ajax_referer('atm_nonce', 'nonce');

    try {
        if (!isset($_FILES['audio_file'])) {
            throw new Exception('No audio file was received.');
        }

        $file = $_FILES['audio_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Prompt logic has been removed.
        
        // Pass only the file to the API method.
        $transcript = ATM_API::transcribe_audio_with_whisper($file['tmp_name']);
        wp_send_json_success(['transcript' => $transcript]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

public function translate_text() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');

        try {
            if (empty($_POST['source_text']) || empty($_POST['target_language'])) {
                throw new Exception('Source text and target language are required.');
            }

            $source_text = sanitize_textarea_field(stripslashes($_POST['source_text']));
            $target_language = sanitize_text_field($_POST['target_language']);

            $translated_text = ATM_API::translate_text($source_text, $target_language);

            wp_send_json_success(['translated_text' => $translated_text]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function upload_podcast_image() {
        check_ajax_referer('atm_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $image_url = sanitize_url($_POST['image_url']);
        if ($image_url) {
            update_post_meta($post_id, '_atm_podcast_image', $image_url);
            wp_send_json_success(['image_url' => $image_url]);
        } else {
            wp_send_json_error('Invalid image URL');
        }
    }
    
    public function __construct() {
        // Core Actions for the React App & other features
        add_action('wp_ajax_generate_article_title', array($this, 'generate_article_title'));
        add_action('wp_ajax_generate_article_content', array($this, 'generate_article_content'));
        add_action('wp_ajax_generate_news_article', array($this, 'generate_news_article'));
        add_action('wp_ajax_fetch_rss_articles', array($this, 'fetch_rss_articles'));
        add_action('wp_ajax_generate_article_from_rss', array($this, 'generate_article_from_rss'));
        add_action('wp_ajax_generate_featured_image', array($this, 'generate_featured_image'));
        add_action('wp_ajax_generate_podcast_script', array($this, 'generate_podcast_script'));
        add_action('wp_ajax_generate_podcast', array($this, 'generate_podcast'));
        add_action('wp_ajax_upload_podcast_image', array($this, 'upload_podcast_image'));
        add_action('wp_ajax_transcribe_audio', array($this, 'transcribe_audio'));
        add_action('wp_ajax_translate_text', array($this, 'translate_text'));
        add_action('wp_ajax_translate_editor_content', array($this, 'translate_editor_content'));
        add_action('wp_ajax_get_youtube_suggestions', array($this, 'get_youtube_suggestions'));
        add_action('wp_ajax_search_youtube', array($this, 'search_youtube'));
        add_action('wp_ajax_generate_chart_from_ai', array($this, 'generate_chart_from_ai'));
        add_action('wp_ajax_save_atm_chart', array($this, 'save_atm_chart'));

        // Helper/Legacy Actions
        add_action('wp_ajax_test_rss_feed', array($this, 'test_rss_feed'));
        add_action('wp_ajax_generate_inline_image', array($this, 'generate_inline_image'));
    }

    public function generate_podcast_script() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $article_content = wp_strip_all_tags(stripslashes($_POST['content']));
            $language = sanitize_text_field($_POST['language']);

            if (empty($article_content)) {
                throw new Exception("Article content is empty. Please write your article first.");
            }

            // Fetches the master prompt from the central API class, avoiding duplication.
            $system_prompt = ATM_API::get_default_master_prompt();

            // Inject the language instruction into the master prompt
            $system_prompt = "**CRITICAL INSTRUCTION: You MUST write the entire podcast script in " . $language . ".**\n\n" . $system_prompt;

            $post = get_post(intval($_POST['post_id']));
            $final_prompt = ATM_API::replace_prompt_shortcodes($system_prompt, $post);
$final_prompt .= ' Use your web search ability to verify facts and add any recent developments to make the podcast as up-to-date as possible.';

            $generated_script = ATM_API::enhance_content_with_openrouter(
                ['content' => $article_content],
                $final_prompt,
                get_option('atm_content_model', 'anthropic/claude-3-haiku')
            );

            wp_send_json_success(['script' => $generated_script]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_podcast() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }

        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 600);

        try {
            $post_id = intval($_POST['post_id']);
            $script = isset($_POST['script']) ? wp_kses_post(stripslashes($_POST['script'])) : '';
            $voice = sanitize_text_field($_POST['voice']);
            $provider = sanitize_text_field($_POST['provider']);
            
            if (empty($script)) throw new Exception('The Podcast Script cannot be empty.');

            $max_chunk_size = ($provider === 'elevenlabs') ? 4500 : 4000;
            $script_chunks = [];
            $current_chunk = '';

            $sentences = preg_split('/(?<=[.?!])\s+/', $script, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($sentences as $sentence) {
                if (strlen($current_chunk) + strlen($sentence) + 1 > $max_chunk_size) {
                    $script_chunks[] = $current_chunk;
                    $current_chunk = $sentence;
                } else {
                    $current_chunk .= ' ' . $sentence;
                }
            }
            if (!empty($current_chunk)) {
                $script_chunks[] = trim($current_chunk);
            }

            $final_audio_content = '';
            $final_audio_content .= base64_decode('SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGllbmRhcmQgTG9wZXogaW4gT25lVHJpY2sBTQuelleAAAAANFaAAAAAAAAAAAAAAAAAAAAD/8AAAAAAAADw==');

            foreach ($script_chunks as $chunk) {
                $audio_chunk_content = null;
                if ($provider === 'elevenlabs') {
                    $audio_chunk_content = ATM_API::generate_audio_with_elevenlabs(trim($chunk), $voice);
                } else { // Default to OpenAI
                    $audio_chunk_content = ATM_API::generate_audio_with_openai_tts(trim($chunk), $voice);
                }
                $final_audio_content .= $audio_chunk_content;
            }

            $upload_dir = wp_upload_dir();
            $podcast_dir = $upload_dir['basedir'] . '/podcasts';
            if (!file_exists($podcast_dir)) wp_mkdir_p($podcast_dir);
            $filename = 'podcast-' . $post_id . '-' . time() . '.mp3';
            $filepath = $podcast_dir . '/' . $filename;
            if (!file_put_contents($filepath, $final_audio_content)) {
                throw new Exception('Failed to save the final audio file.');
            }
            $podcast_url = $upload_dir['baseurl'] . '/podcasts/' . $filename;
            update_post_meta($post_id, '_atm_podcast_url', $podcast_url);
            update_post_meta($post_id, '_atm_podcast_script', $script);
            
            wp_send_json_success(['message' => 'Podcast generated successfully!', 'podcast_url' => $podcast_url]);
            
        } catch (Exception $e) {
            error_log('Content AI Studio Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_article_title() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $keyword = sanitize_text_field($_POST['keyword']);
            $title_input = sanitize_text_field($_POST['title']);
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
            $topic = !empty($title_input) ? 'the article title: "' . $title_input . '"' : 'the keyword: "' . $keyword . '"';
            if (empty($topic)) {
                throw new Exception("Please provide a keyword or title.");
            }
            $system_prompt = 'You are an expert SEO content writer. Use your web search ability to understand the current context and popular phrasing for the given topic. Your task is to generate a single, compelling, SEO-friendly title. Return only the title itself, with no extra text or quotation marks.';
            $generated_title = ATM_API::enhance_content_with_openrouter(['content' => $topic], $system_prompt, $model_override ?: get_option('atm_article_model'));
            $cleaned_title = trim($generated_title, " \t\n\r\0\x0B\"");
            wp_send_json_success(['article_title' => $cleaned_title]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_article_content() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $post = get_post($post_id);
            $article_title = isset($_POST['article_title']) ? sanitize_text_field($_POST['article_title']) : '';
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
            $style_key = isset($_POST['writing_style']) ? sanitize_key($_POST['writing_style']) : 'default_seo';
            $custom_prompt = isset($_POST['custom_prompt']) ? wp_kses_post(stripslashes($_POST['custom_prompt'])) : '';
            $word_count = isset($_POST['word_count']) ? intval($_POST['word_count']) : 0;
            if (empty($article_title) && empty($keyword)) {
                throw new Exception("Please provide a keyword or an article title.");
            }
            if (empty($article_title) && !empty($keyword)) {
                $article_title = ATM_API::generate_title_from_keyword($keyword, $model_override);
            }
            $writing_styles = ATM_API::get_writing_styles();
            $base_prompt = isset($writing_styles[$style_key]) ? $writing_styles[$style_key]['prompt'] : $writing_styles['default_seo']['prompt'];
            if (!empty($custom_prompt)) {
                $base_prompt = $custom_prompt;
            }
            $output_instructions = '
            **Final Output Format:**
            Your entire output MUST be a single, valid JSON object with two keys:
            1. "subheadline": A creative and engaging one-sentence subtitle that complements the main title.
            2. "content": The full article text, formatted using Markdown. 
            
            **IMPORTANT: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.**
            
            **Content Rules:**
            - The `content` field must NOT start with a title or any heading (like `# Heading`). It must begin directly with the first paragraph of the introduction.
            - Do NOT include a final heading titled "Conclusion". The article should end naturally with the concluding paragraph itself.';
            $system_prompt = $base_prompt . "\n\n" . $output_instructions;
            if ($post) {
                $system_prompt = ATM_API::replace_prompt_shortcodes($system_prompt, $post);
            }
            if ($word_count > 0) {
                $system_prompt .= " The final article should be approximately " . $word_count . " words long.";
            }
            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $article_title], $system_prompt, $model_override ?: get_option('atm_article_model'), true);
            $result = json_decode($raw_response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
                error_log('Content AI Studio - Invalid JSON from Creative AI: ' . $raw_response);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }
            $subtitle = isset($result['subheadline']) ? trim($result['subheadline']) : (isset($result['subtitle']) ? trim($result['subtitle']) : '');
            $original_content = trim($result['content']);
            $final_content = $original_content;
            if ($post_id > 0 && !empty($subtitle)) {
                $final_content = ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $subtitle, $original_content);
            }
            wp_send_json_success(['article_title' => $article_title, 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_news_article() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $topic = sanitize_text_field($_POST['topic']);
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : get_option('atm_article_model');
            $force_fresh = isset($_POST['force_fresh']) && $_POST['force_fresh'] === 'true';
            $news_source = isset($_POST['news_source']) ? sanitize_key($_POST['news_source']) : 'newsapi';
            if (empty($topic)) {
                throw new Exception("Please provide a topic for the news article.");
            }
            $news_context = ATM_API::fetch_news($topic, $news_source, $force_fresh);
            if (empty($news_context)) {
                throw new Exception("No recent news found for the topic: '" . esc_html($topic) . "'. Please try a different keyword or source.");
            }
            $system_prompt = 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English. **Use your web search ability to verify the information and add any missing context.**

                Follow these strict guidelines:
                - **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human.
                - **Originality**: Do not copy verbatim from the source. You must rewrite, summarize, and humanize the content.
                - **Length**: Aim for 800–1200 words.
                - **IMPORTANT**: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.
                - The `content` field must NOT start with a title. It must begin directly with the introductory paragraph in a news article style.
                - Do NOT include a final heading titled "Conclusion". The article should end naturally with the concluding paragraph itself.

                **Final Output Format:**
                Your entire output MUST be a single, valid JSON object with three keys:
                1. "headline": A concise, factual, and compelling headline for the new article.
                2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
                3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.'; // The long prompt text remains the same
            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $news_context], $system_prompt, $model_override, true);
            $json_string = '';
            if (preg_match('/\{.*?\}/s', $raw_response, $matches)) {
                $json_string = $matches[0];
            } else {
                throw new Exception('The AI returned a non-JSON response. Please try again.');
            }
            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['headline']) || !isset($result['content'])) {
                error_log('ATM Plugin - Invalid JSON from AI: ' . $json_string);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }
            $subtitle = isset($result['subheadline']) ? trim($result['subheadline']) : '';
            $original_content = trim($result['content']);
            $final_content = $original_content;
            if ($post_id > 0 && !empty($subtitle)) {
                $final_content = ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $subtitle, $original_content);
            }
            wp_send_json_success(['article_title' => $result['headline'], 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function fetch_rss_articles() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
            $use_scraping = isset($_POST['use_scraping']) && $_POST['use_scraping'] === 'true';
            $rss_feeds_string = get_option('atm_rss_feeds', '');
            if (empty($rss_feeds_string)) {
                throw new Exception('No RSS feeds configured in settings.');
            }
            if ($use_scraping) {
                $articles = ATM_API::search_rss_feeds($rss_feeds_string, $keyword, true);
            } else {
                $articles = ATM_API::parse_rss_feeds($rss_feeds_string, $post_id, $keyword);
            }
            if (empty($articles) && !empty($keyword)) {
                error_log('ATM RSS: No articles found for keyword "' . $keyword . '" in ' . count(explode("\n", $rss_feeds_string)) . ' feeds');
            }
            wp_send_json_success($articles);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function test_rss_feed() {
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $feed_url = esc_url_raw($_POST['feed_url']);
            $keyword = sanitize_text_field($_POST['keyword'] ?? '');
            if (empty($feed_url)) {
                throw new Exception('Feed URL is required.');
            }
            $articles = ATM_RSS_Parser::parse_rss_feeds_advanced($feed_url, 0, $keyword);
            wp_send_json_success(['articles' => $articles, 'total_found' => count($articles), 'feed_url' => $feed_url, 'keyword' => $keyword]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_article_from_rss() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $url = esc_url_raw($_POST['article_url']);
            $guid = sanitize_text_field($_POST['article_guid']);
            $use_full_content = isset($_POST['use_full_content']) && $_POST['use_full_content'] === 'true';
            // ... (rest of the content fetching logic remains the same)
            $final_url = ATM_API::resolve_redirect_url($url);
            $source_content = '';
            if ($use_full_content) {
                try {
                    $source_content = ATM_API::fetch_full_article_content($final_url);
                } catch (Exception $e) {
                    error_log('ATM RSS: Scraping failed, falling back to RSS content: ' . $e->getMessage());
                }
            }
            if (empty($source_content) && isset($_POST['rss_content'])) {
                $source_content = wp_kses_post(stripslashes($_POST['rss_content']));
            }
            if (empty($source_content)) {
                throw new Exception('Could not extract sufficient content from the source article.');
            }
            if (strlen($source_content) > 4000) {
                $source_content = ATM_API::summarize_content_for_rewrite($source_content);
            }
            $system_prompt = 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English. **Use your web search ability to verify the information and add any missing context.**

                Follow these strict guidelines:
                - **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human.
                - **Originality**: Do not copy verbatim from the source. You must rewrite, summarize, and humanize the content.
                - **Length**: Aim for 800–1200 words.
                - **IMPORTANT**: The `content` field must NOT contain any top-level H1 headings (formatted as `# Heading`). Use H2 (`##`) for all main section headings.
                - The `content` field must NOT start with a title. It must begin directly with the introductory paragraph in a news article style.
                - Do NOT include a final heading titled "Conclusion". The article should end naturally with the concluding paragraph itself.

                **Final Output Format:**
                Your entire output MUST be a single, valid JSON object with three keys:
                1. "headline": A concise, factual, and compelling headline for the new article.
                2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
                3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.'; // The long prompt text remains the same
            $raw_response = ATM_API::enhance_content_with_openrouter(['content' => $source_content], $system_prompt, get_option('atm_article_model', 'openai/gpt-4o'), true);
            $json_string = '';
            if (preg_match('/\{.*?\}/s', $raw_response, $matches)) {
                $json_string = $matches[0];
            } else {
                throw new Exception('The AI returned a non-JSON response. Please try again.');
            }
            $result = json_decode($json_string, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['headline']) || !isset($result['content'])) {
                error_log('ATM Plugin - Invalid JSON from AI: ' . $json_string);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }
            $headline = trim($result['headline']);
            $subtitle = isset($result['subheadline']) ? trim($result['subheadline']) : '';
            $original_content = trim($result['content']);
            $final_content = $original_content;
            if ($post_id > 0 && !empty($subtitle)) {
                $final_content = ATM_Theme_Subtitle_Manager::save_subtitle($post_id, $subtitle, $original_content);
            }
            if (empty($headline) || empty($final_content)) {
                throw new Exception('Generated title or content is empty.');
            }
            if ($post_id > 0) {
                $used_guids = get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [];
                $used_guids[] = $guid;
                update_post_meta($post_id, '_atm_used_rss_guids', array_unique($used_guids));
            }
            wp_send_json_success(['article_title' => $headline, 'article_content' => $final_content, 'subtitle' => $subtitle]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_featured_image() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        @ini_set('max_execution_time', 300);
        try {
            $post_id = intval($_POST['post_id']);
            $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(stripslashes($_POST['prompt'])) : ''; // This now comes directly from the user
            $size_override = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
            $quality_override = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : '';
            $provider_override = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';

            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found.");
            }

            if (empty(trim($prompt))) {
                throw new Exception('Image prompt cannot be empty.');
            }

            // We use the prompt directly, replacing any shortcodes it might contain
            $final_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);

            $provider = !empty($provider_override) ? $provider_override : get_option('atm_image_provider', 'openai');
            $image_data = null;
            $is_url = false;

            switch ($provider) {
                case 'google':
                    $image_data = ATM_API::generate_image_with_google_imagen($final_prompt, $size_override);
                    $is_url = false;
                    break;
                case 'blockflow':
                    $image_data = ATM_API::generate_image_with_blockflow($final_prompt, '', $size_override);
                    $is_url = false;
                    break;
                case 'openai':
                default:
                    $image_data = ATM_API::generate_image_with_openai($final_prompt, $size_override, $quality_override);
                    $is_url = true;
                    break;
            }

            if ($is_url) {
                $attachment_id = $this->set_image_from_url($image_data, $post_id);
            } else {
                $attachment_id = $this->set_image_from_data($image_data, $post_id, $final_prompt);
            }

            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }

            set_post_thumbnail($post_id, $attachment_id);
            $thumbnail_html = _wp_post_thumbnail_html($attachment_id, $post_id);

            // We no longer need to send back the prompt since the user wrote it.
            wp_send_json_success(['attachment_id' => $attachment_id, 'html' => $thumbnail_html]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_inline_image() {
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $prompt = sanitize_textarea_field($_POST['prompt']);
            if (empty($prompt)) {
                throw new Exception("Prompt cannot be empty.");
            }
            $post = get_post($post_id);
            $processed_prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);
            $image_url = ATM_API::generate_image_with_openai($processed_prompt);
            $attachment_id = $this->set_image_from_url($image_url, $post_id);
            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }
            $image_data = wp_get_attachment_image_src($attachment_id, 'large');
            wp_send_json_success(['url' => $image_data[0], 'alt' => $prompt]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function set_image_from_url($url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        $file_array = array();
        preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }
        return $id;
    }

    public function set_image_from_data($image_data, $post_id, $prompt) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $upload_dir = wp_upload_dir();
        $filename = 'ai-image-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;
        file_put_contents($filepath, $image_data);
        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename($filepath),
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field($prompt),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}