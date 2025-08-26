<?php

if (!defined('ABSPATH')) {
    exit;
}

class ATM_Ajax {

    public function generate_podcast_script() {
    check_ajax_referer('atm_nonce', 'nonce');
    try {
        $article_content = wp_strip_all_tags(stripslashes($_POST['content']));
        $language = sanitize_text_field($_POST['language']);

        if (empty($article_content)) {
            throw new Exception("Article content is empty. Please write your article first.");
        }

        // A much more detailed, language-specific prompt to ensure a longer, higher-quality script.
        $system_prompt = "You are an expert podcast host creating a script for an episode of '[podcast_name]'.

        **CRITICAL INSTRUCTION: You MUST write the entire podcast script in " . $language . ".**

        Your task is to transform the provided article into a dynamic, engaging, and conversational podcast script that is at least 3 minutes long, but ideally 4-5 minutes.

        **Persona & Style:**
        - You are insightful, curious, and knowledgeable.
        - Your tone should be conversational and authoritative, like a professional host who connects deeply with the audience.
        - Speak in a natural, human way — not like you’re reading a formal document.

        **Required Script Structure (Follow this flow precisely):**
        1.  **Hook (20-30 seconds):** Start with a compelling question, a surprising fact, or a bold statement from the article to instantly grab the listener's attention.
        2.  **Introduction (45-60 seconds):** Welcome listeners to '[podcast_name]'. Briefly introduce the topic using the article title: '[article_title]'. Elaborate on why this topic is important and relevant to the listener right now.
        3.  **Main Discussion (At least 3 minutes):** Break the article's core message into 3-4 distinct talking points. For each point:
            - First, clearly summarize the idea from the article.
            - **Then, you MUST add your own detailed analysis and expansion.** This is crucial for meeting the length requirement. Discuss the implications, offer a fresh perspective, provide real-world examples, or connect it to broader trends.
            - Use conversational transitions like 'Now, let's dive into...', 'What's fascinating about this is...', or 'But if we consider the bigger picture...'.
        4.  **Conclusion (30-45 seconds):** Recap the key takeaways in a concise, engaging way. End with a final, thought-provoking insight that leaves the listener with something valuable.
        5.  **Closing (20 seconds — use this exact wording, translated into " . $language . "):** 'That’s all the time we have for today on ''[podcast_name]''. Thanks for tuning in. For more content, visit [site_url].'

        **Final Output Rules:**
        - **NO** headings, labels (like 'Hook:' or 'Host:'), or stage directions.
        - Provide **ONLY** the raw, speakable script.
        - Ensure the script is long enough to naturally last for at least 3 minutes of speaking time.

        Now, transform the following article into your complete podcast script.";

        // We need to replace shortcodes *after* generating the script, so we pass the template.
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

    public function generate_default_podcast_prompt() {
    check_ajax_referer('atm_nonce', 'nonce');
    try {
        $article_content = wp_strip_all_tags(stripslashes($_POST['content']));

        if (empty($article_content)) {
            throw new Exception("Article content is empty. Please write some content first.");
        }

        // A prompt to the AI to detect the language and return the default prompt in that language.
        $system_prompt = "Analyze the following article content to determine its primary language. Then, return the complete default podcast prompt provided below, translated into that detected language. Return ONLY the translated prompt text, with no extra commentary or explanations.\n\n---DEFAULT PROMPT START---\n" . $_POST['default_prompt'] . "\n---DEFAULT PROMPT END---";

        $generated_prompt = ATM_API::enhance_content_with_openrouter(
            ['content' => $article_content], 
            $system_prompt, 
            'anthropic/claude-3-haiku' // Use a fast and cheap model for this task
        );

        wp_send_json_success(['prompt' => $generated_prompt]);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
    
    public function __construct() {
        add_action('wp_ajax_generate_podcast', array($this, 'generate_podcast'));
        add_action('wp_ajax_preview_tts_voice', array($this, 'preview_tts_voice'));
        add_action('wp_ajax_upload_podcast_image', array($this, 'upload_podcast_image'));
        add_action('wp_ajax_generate_default_podcast_prompt', array($this, 'generate_default_podcast_prompt'));
        add_action('wp_ajax_generate_podcast_script', array($this, 'generate_podcast_script'));
        
        // Actions for the "Creative Article" workflow
        add_action('wp_ajax_generate_article_title', array($this, 'generate_article_title'));
        add_action('wp_ajax_generate_article_content', array($this, 'generate_article_content'));

        add_action('wp_ajax_generate_featured_image', array($this, 'generate_featured_image'));
        
        // Action for the "Latest News Article" workflow
        add_action('wp_ajax_generate_news_article', array($this, 'generate_news_article'));

        // Enhanced RSS actions
        add_action('wp_ajax_fetch_rss_articles', array($this, 'fetch_rss_articles'));
        add_action('wp_ajax_generate_article_from_rss', array($this, 'generate_article_from_rss'));
        add_action('wp_ajax_test_rss_feed', array($this, 'test_rss_feed'));
        
        // New action for inline image generation
        add_action('wp_ajax_generate_inline_image', array($this, 'generate_inline_image'));
    }

    private function get_language_name($code) {
        $languages = [
            'en' => 'English', 'ar' => 'Arabic', 'zh' => 'Chinese', 'hi' => 'Hindi',
            'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'ru' => 'Russian',
            'pt' => 'Portuguese', 'ja' => 'Japanese'
        ];
        return $languages[$code] ?? 'English';
    }

    /**
     * Handles the "Creative Article" title generation.
     */
    public function generate_article_title() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

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

            $generated_title = ATM_API::enhance_content_with_openrouter(
                ['content' => $topic], 
                $system_prompt, 
                $model_override ?: get_option('atm_article_model')
            );

            $cleaned_title = trim($generated_title, " \t\n\r\0\x0B\"");

            wp_send_json_success(['article_title' => $cleaned_title]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handles the "Creative Article" content generation.
     */
    public function generate_article_content() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

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
                $system_prompt = isset($writing_styles[$style_key]) 
                    ? $writing_styles[$style_key]['prompt'] 
                    : $writing_styles['default_seo']['prompt'];
            }

            if ($word_count > 0) {
                $system_prompt .= " The final article should be approximately " . $word_count . " words long. Ensure the content is detailed and comprehensive enough to meet this length requirement.";
            }

            $generated_content = ATM_API::enhance_content_with_openrouter(
                ['content' => $article_title], 
                $system_prompt, 
                $model_override ?: get_option('atm_article_model')
            );

            wp_send_json_success(['article_content' => $generated_content]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handles the "Latest News Article" generation.
     */
    public function generate_news_article() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

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
                throw new Exception("No recent news found for the topic: '" . $topic . "'. Please try a different keyword or source.");
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

            $json_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $news_context], 
                $system_prompt, 
                $model_override, 
                true
            );

            $json_start = strpos($json_response, '{');
            $json_end = strrpos($json_response, '}');
            
            if ($json_start === false || $json_end === false) {
                error_log('ATM Plugin - AI response did not contain a valid JSON object: ' . $json_response);
                throw new Exception('The AI returned a non-JSON response. Please try again.');
            }

            $json_only_string = substr($json_response, $json_start, ($json_end - $json_start + 1));
            $result = json_decode($json_only_string, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['headline']) || !isset($result['content'])) {
                error_log('ATM Plugin - Invalid JSON from AI: ' . $json_only_string);
                throw new Exception('The AI returned an invalid response structure. Please try again.');
            }

            $final_content = $result['content'];
            if (!empty($result['subheadline'])) {
                $final_content = '### ' . trim($result['subheadline']) . "\n\n" . $final_content;
            }

            wp_send_json_success([
                'article_title' => $result['headline'],
                'article_content' => $final_content
            ]);

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
            
            wp_send_json_success([
                'articles' => $articles,
                'total_found' => count($articles),
                'feed_url' => $feed_url,
                'keyword' => $keyword
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_article_from_rss() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

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
                    error_log('ATM RSS: Scraping failed, using RSS content: ' . $e->getMessage());
                }
            }
            
            if (empty($source_content) && isset($_POST['rss_content'])) {
                $source_content = wp_kses_post(stripslashes($_POST['rss_content']));
            }
            
            if (empty($source_content)) {
                throw new Exception('Could not extract sufficient content from the source article. Try enabling "Use Full Content" option.');
            }

            $system_prompt = 'You are a seasoned news reporter and journalist working for a major news organization. Transform the following source material into a professional breaking news article.

CRITICAL INSTRUCTIONS FOR NEWS ARTICLE:
- Write in AP Style with inverted pyramid structure (most important info first)
- Use active voice and short, punchy sentences
- Include proper news lede in first paragraph (who, what, when, where, why)
- Use objective, fact-based reporting tone - no opinions or editorializing
- Include quotes or attributed statements when possible
- Use present tense for ongoing situations, past tense for completed events
- Write compelling but factual headline that could appear on CNN, Reuters, or AP News
- Structure: Lede → Key details → Background → Additional context
- Word count: 400-800 words for breaking news format

JSON FORMAT REQUIRED:
{"headline":"Breaking: [Professional News Headline]","subheadline":"[Optional brief context - can be empty]","content":"[Full news article in markdown]"}

Source material to transform into breaking news:';
            
            $json_response = ATM_API::enhance_content_with_openrouter(
                ['content' => $source_content], 
                $system_prompt, 
                '',
                true
            );

            $result = null;
            $json_only_string = '';
            
            $result = json_decode($json_response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($result['headline']) && isset($result['content'])) {
            } else {
                $json_start = strpos($json_response, '{');
                $json_end = strrpos($json_response, '}');
                
                if ($json_start !== false && $json_end !== false) {
                    $json_only_string = substr($json_response, $json_start, ($json_end - $json_start + 1));
                    $result = json_decode($json_only_string, true);
                }
                
                if (json_last_error() !== JSON_ERROR_NONE || !isset($result['headline']) || !isset($result['content'])) {
                    $cleaned_response = $json_only_string ?: $json_response;
                    $cleaned_response = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $cleaned_response);
                    $cleaned_response = str_replace(['\n', '\r', '\t'], ['\\n', '\\r', '\\t'], $cleaned_response);
                    $cleaned_response = preg_replace('/,\s*}/', '}', $cleaned_response);
                    $result = json_decode($cleaned_response, true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['headline']) || !isset($result['content'])) {
                error_log('ATM Plugin - JSON parsing failed. Original response: ' . substr($json_response, 0, 500));
                error_log('ATM Plugin - JSON error: ' . json_last_error_msg());
                
                $simple_content = ATM_API::enhance_content_with_openrouter(
                    ['content' => $source_content], 
                    'Rewrite the following content into a clear, engaging article. Provide only the article text, no JSON formatting. Use markdown headings and formatting.', 
                    '',
                    false
                );
                
                $simple_title = 'Article: ' . parse_url($url, PHP_URL_HOST);
                
                wp_send_json_success([
                    'article_title'   => $simple_title,
                    'article_content' => $simple_content
                ]);
                return;
            }

            $headline = trim($result['headline']);
            $final_content = trim($result['content']);

            if (empty($headline) || $headline === 'Title:' || strlen($headline) < 5) {
                $domain = parse_url($url, PHP_URL_HOST);
                $headline = 'Breaking News from ' . ucfirst(str_replace('www.', '', $domain));
            }

            if (empty($final_content) || strlen($final_content) < 50) {
                throw new Exception('Generated content is too short or empty. Please try again.');
            }

            $final_content = preg_replace('/\*\*(.*?)\*\*/s', '**$1**', $final_content);
            $final_content = preg_replace('/^[\s\*\#]+/', '', $final_content);

            if (!empty($result['subheadline'])) {
                $final_content = '### ' . trim($result['subheadline']) . "\n\n" . $final_content;
            }

            if ($post_id > 0) {
                $used_guids = get_post_meta($post_id, '_atm_used_rss_guids', true) ?: [];
                $used_guids[] = $guid;
                update_post_meta($post_id, '_atm_used_rss_guids', array_unique($used_guids));
            }

            wp_send_json_success([
                'article_title'   => $headline,
                'article_content' => $final_content
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_featured_image() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

        check_ajax_referer('atm_nonce', 'nonce');
        try {
            $post_id = intval($_POST['post_id']);
            $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(stripslashes($_POST['prompt'])) : '';

            if (empty($prompt)) {
                $post = get_post($post_id);
                if (!$post) {
                    throw new Exception("Post not found for generating a default image prompt.");
                }
                $title = $post->post_title;
                $excerpt = wp_strip_all_tags($post->post_content);
                $excerpt = mb_substr($excerpt, 0, 300) . '...';

                $prompt = "A high-resolution, photorealistic featured image for a blog post titled \"{$title}\". The article discusses: \"{$excerpt}\". The image should be professional, visually compelling, and directly relevant to the main subject of the article. Use cinematic lighting and a 16:9 aspect ratio.";
            } else {
                $post = get_post($post_id);
                $prompt = ATM_API::replace_prompt_shortcodes($prompt, $post);
            }

            $image_url = ATM_API::generate_image_with_openai($prompt);
            
            $attachment_id = $this->set_image_from_url($image_url, $post_id);

            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }

            set_post_thumbnail($post_id, $attachment_id);

            wp_send_json_success(['image_url' => wp_get_attachment_url($attachment_id)]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function generate_inline_image() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

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

            wp_send_json_success([
                'url' => $image_data[0],
                'alt' => $prompt
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function generate_podcast() {
        // --- LICENSE CHECK ---
        if (!ATM_Licensing::is_license_active()) {
            wp_send_json_error('Please activate your license key to use this feature.');
        }
        // --- END CHECK ---

        check_ajax_referer('atm_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $script = isset($_POST['script']) ? wp_kses_post(stripslashes($_POST['script'])) : '';
        
        if (empty($script)) {
            throw new Exception('The Podcast Script cannot be empty. Please generate a script first.');
        }
        
        update_post_meta($post_id, '_atm_podcast_status', 'generating');
        
        try {
            $per_post_voice = sanitize_text_field($_POST['voice']);
            $available_voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
            $final_voice = '';

            if (!empty($per_post_voice)) {
                $final_voice = ($per_post_voice === 'random') ? $available_voices[array_rand($available_voices)] : $per_post_voice;
            } else {
                $default_voice = get_option('atm_voice_selection', 'alloy');
                $final_voice = ($default_voice === 'random') ? $available_voices[array_rand($available_voices)] : $default_voice;
            }
            
            $podcast_url = ATM_API::generate_audio_with_openai_tts($script, $post_id, $final_voice);
            
            update_post_meta($post_id, '_atm_podcast_url', $podcast_url);
            update_post_meta($post_id, '_atm_podcast_status', 'completed');
            
            wp_send_json_success(['message' => 'Podcast generated successfully!', 'podcast_url' => $podcast_url]);
            
        } catch (Exception $e) {
            update_post_meta($post_id, '_atm_podcast_status', 'failed');
            error_log('Content AI Studio Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    public function preview_tts_voice() {
        check_ajax_referer('atm_nonce', 'nonce');
        $voice = sanitize_text_field($_POST['voice']);
        $sample_text = "Hello, this is a preview of the selected voice.";

        try {
            $audio_content = ATM_API::generate_audio_with_openai_tts($sample_text, 0, $voice, true);
            wp_send_json_success(['audio_data' => base64_encode($audio_content)]);
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
}