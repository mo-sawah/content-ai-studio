<?php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Ajax {

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
            $system_prompt = 'You are an expert SEO content writer. Your task is to generate a single, compelling, SEO-friendly title for an article based on the provided topic. Do not provide any explanation, quotation marks, or any other text. Just the title itself.';
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
            $article_title = sanitize_text_field($_POST['article_title']);
            $model_override = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
            $style_key = isset($_POST['writing_style']) ? sanitize_key($_POST['writing_style']) : 'default_seo';
            $custom_prompt = isset($_POST['custom_prompt']) ? wp_kses_post(stripslashes($_POST['custom_prompt'])) : '';
            $word_count = isset($_POST['word_count']) ? intval($_POST['word_count']) : 0;
            if (empty($article_title)) {
                throw new Exception("Article title cannot be empty.");
            }
            $system_prompt = '';
            if (!empty($custom_prompt) && $post) {
                $system_prompt = ATM_API::replace_prompt_shortcodes($custom_prompt, $post);
            } else {
                $writing_styles = ATM_API::get_writing_styles();
                $system_prompt = isset($writing_styles[$style_key]) ? $writing_styles[$style_key]['prompt'] : $writing_styles['default_seo']['prompt'];
            }
            if ($word_count > 0) {
                $system_prompt .= " The final article should be approximately " . $word_count . " words long. Ensure the content is detailed and comprehensive enough to meet this length requirement.";
            }
            $generated_content = ATM_API::enhance_content_with_openrouter(['content' => $article_title], $system_prompt, $model_override ?: get_option('atm_article_model'));
            wp_send_json_success(['article_content' => $generated_content]);
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
            $system_prompt = 'You are a professional news reporter and editor. Using the following raw content from news snippets, write a clear, engaging, and well-structured news article in English.

Follow these strict guidelines:
- **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human. Your style should be similar to major news outlets like Reuters, Associated Press, or CBS News.
- **Originality**: Do not copy verbatim from the source snippets. You must rewrite, summarize, and humanize the content.
- **Synthesis**: If multiple sources are provided, synthesize them into one cohesive article. Remove any duplicate or irrelevant details.
- **Objectivity**: Avoid speculation or personal opinions unless they are explicitly cited in the provided data.
- **Length**: Aim for 800–1200 words. If the source material is very limited, write as much as is naturally possible without adding filler or redundant information.
- **SEO**: Naturally integrate relevant keywords from the source material. Avoid keyword stuffing.
- **Readability**: Use short paragraphs, the active voice, and ensure a clear, logical flow.

**Final Output Format:**
Your entire output MUST be a single, valid JSON object with three keys:
1. "headline": A concise, factual, and compelling headline for the new article.
2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.';
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
            $final_content = $result['content'];
            if (!empty($result['subheadline'])) {
                $final_content = '### ' . trim($result['subheadline']) . "\n\n" . $final_content;
            }
            wp_send_json_success(['article_title' => $result['headline'], 'article_content' => $final_content]);
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
            $system_prompt = 'You are a professional news reporter and editor. Using the following source article content, write a clear, engaging, and well-structured new article in English.

Follow these strict guidelines:
- **Style**: Adopt a professional journalistic tone. Be objective, fact-based, and write like a human. Your style should be similar to major news outlets like Reuters, Associated Press, or CBS News.
- **Originality**: Do not copy verbatim from the source. You must rewrite, summarize, and humanize the content.
- **Objectivity**: Avoid speculation or personal opinions unless they are explicitly cited in the provided data.
- **Length**: Aim for 800–1200 words. If the source material is very limited, write as much as is naturally possible without adding filler or redundant information.
- **SEO**: Naturally integrate relevant keywords from the source material. Avoid keyword stuffing.
- **Readability**: Use short paragraphs, the active voice, and ensure a clear, logical flow.

**Final Output Format:**
Your entire output MUST be a single, valid JSON object with three keys:
1. "headline": A concise, factual, and compelling headline for the new article.
2. "subheadline": A brief, one-sentence subheadline that expands on the main headline.
3. "content": The full article text, formatted using Markdown. The content must start with an introduction (lede), be followed by body paragraphs with smooth transitions, and end with a short conclusion.';
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
            $final_content = trim($result['content']);
            if (!empty($result['subheadline'])) {
                $final_content = '### ' . trim($result['subheadline']) . "\n\n" . $final_content;
            }
            if (strpos(strtolower(substr($final_content, 0, 150)), strtolower($headline)) !== false) {
                $final_content = preg_replace('/^#+\s*' . preg_quote($headline, '/') . '\s*/i', '', $final_content, 1);
            }
            if (empty($headline) || empty($final_content)) {
                throw new Exception('Generated title or content is empty.');
            }
            if ($post_id > 0) {
                $used_guids = get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [];
                $used_guids[] = $guid;
                update_post_meta($post_id, '_atm_used_rss_guids', array_unique($used_guids));
            }
            wp_send_json_success(['article_title' => $headline, 'article_content' => $final_content]);
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
            $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(stripslashes($_POST['prompt'])) : '';
            $size_override = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
            $quality_override = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : '';
            $provider_override = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found.");
            }
            if (empty(trim($prompt))) {
                $prompt_template = ATM_API::get_default_image_prompt();
            } else {
                $prompt_template = $prompt;
            }
            $final_prompt = ATM_API::replace_prompt_shortcodes($prompt_template, $post);
            $provider = !empty($provider_override) ? $provider_override : get_option('atm_image_provider', 'openai');
            $image_data = null;
            $is_url = false;
            switch ($provider) {
                case 'google':
                    $image_data = ATM_API::generate_image_with_google_imagen($final_prompt, $size_override);
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