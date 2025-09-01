jQuery(document).ready(function ($) {
  /**
   * Helper function to update the editor content in either the Block or Classic editor.
   * @param {string} title - The new post title.
   * @param {string} markdownContent - The new post content in Markdown format.
   */
  function updateEditorContent(title, markdownContent) {
    // Check if the block editor is active by looking for its data store.
    const isBlockEditor = document.body.classList.contains("block-editor-page");

    // --- Update Post Title ---
    if (isBlockEditor) {
      wp.data.dispatch("core/editor").editPost({ title: title });
    } else {
      // Fallback for Classic Editor
      $("#title").val(title);
      $("#title-prompt-text").hide(); // Manually hide the "Add title" label
      $("#title").trigger("blur"); // This tells WordPress to update the slug preview
    }

    // --- Update Post Content ---
    const htmlContent = marked.parse(markdownContent);
    if (isBlockEditor) {
      const blocks = wp.blocks.parse(htmlContent);
      // Clear existing content before inserting new blocks
      const currentBlocks = wp.data.select("core/block-editor").getBlocks();
      if (
        currentBlocks.length > 0 &&
        !(
          currentBlocks.length === 1 &&
          currentBlocks[0].name === "core/paragraph" &&
          currentBlocks[0].attributes.content === ""
        )
      ) {
        const clientIds = currentBlocks.map((block) => block.clientId);
        wp.data.dispatch("core/block-editor").removeBlocks(clientIds);
      }
      wp.data.dispatch("core/block-editor").insertBlocks(blocks);
    } else {
      // Fallback for Classic Editor
      if (typeof tinymce !== "undefined" && tinymce.get("content")) {
        // Set content in the Visual (TinyMCE) editor
        tinymce.get("content").setContent(htmlContent);
      } else {
        // Fallback for the plain Text editor
        $("#content").val(htmlContent);
      }
    }
  }

  /**
   * Helper function to get content from the active editor.
   * @returns {string} The content from either the Block or Classic editor.
   */
  function getEditorContent() {
    const isBlockEditor = document.body.classList.contains("block-editor-page");

    if (isBlockEditor) {
      return wp.data.select("core/editor").getEditedPostContent();
    }

    // For Classic Editor
    if (
      typeof tinymce !== "undefined" &&
      tinymce.get("content") &&
      !tinymce.get("content").isHidden()
    ) {
      // If the Visual tab is active, get content directly from it
      return tinymce.get("content").getContent();
    } else {
      // If the Text tab is active or TinyMCE isn't ready, get from the textarea
      return $("#content").val();
    }
  }

  function checkAndGenerateImage(button, postId) {
    if ($("#atm-generate-image-with-article").is(":checked")) {
      button.html('<div class="atm-spinner"></div> Generating Image...');
      // A small delay to ensure the post might save, especially in Gutenberg
      setTimeout(function () {
        $.ajax({
          url: atm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "generate_featured_image",
            post_id: postId,
            prompt: "", // Send empty prompt to use automatic prompt on the backend
            nonce: atm_ajax.nonce,
          },
          success: function (imgResponse) {
            if (imgResponse.success) {
              // Display the prompt in an alert box if it exists
              if (imgResponse.data.generated_prompt) {
                alert(
                  "AI-Generated Image Prompt:\n\n" +
                    imgResponse.data.generated_prompt
                );
              }

              button.html("✅ All Done! Refreshing...");
              setTimeout(() => window.location.reload(), 2000);
            } else {
              alert("Article created, but image failed: " + imgResponse.data);
              resetButton(button, "Generate Article");
            }
          },
          error: function () {
            alert(
              "Article created, but an error occurred during image generation."
            );
            resetButton(button, "Generate Article");
          },
          complete: function () {
            isGenerating = false;
          },
        });
      }, 1500);
    } else {
      button.html("✅ Article Inserted!");
      setTimeout(() => resetButton(button, "Generate Article"), 2000);
      isGenerating = false;
    }
  }
  // Handle the new "Generate Script" button
  $("#atm-generate-script-btn").on("click", function () {
    const button = $(this);
    const textSpan = button.find(".atm-btn-text");
    const spinner = button.find(".atm-spinner");
    const originalText = textSpan.text();
    const editorContent = getEditorContent();
    const postId = $("#post_ID").val();
    const language = $("#atm-language-select").val();

    if (!editorContent.trim()) {
      alert(
        "Please write some content in the editor before generating a script."
      );
      return;
    }

    if (button.prop("disabled")) return;
    button.prop("disabled", true);
    textSpan.text("Generating...");
    spinner.show();
    button.css("display", "inline-flex"); // Ensure flex alignment for spinner

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_podcast_script",
        content: editorContent,
        post_id: postId,
        language: language,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          $("#atm-podcast-script").val(response.data.script).height(250);
        } else {
          alert("Error: ".response.data);
        }
      },
      error: function () {
        alert("An unknown error occurred while generating the script.");
      },
      complete: function () {
        button.prop("disabled", false);
        textSpan.text(originalText);
        spinner.hide();
      },
    });
  });

  // --- Update the main "Generate Podcast" button handler ---
  // Remove the old one and replace it with this:
  $("#atm-generate-podcast-btn").on("click", function () {
    const button = $(this);
    if (button.prop("disabled")) return;

    const postId = button.data("post-id");
    const script = $("#atm-podcast-script").val();
    if (!script.trim()) {
      alert("Please generate a script before creating the podcast.");
      return;
    }

    button
      .prop("disabled", true)
      .html('<div class="atm-spinner"></div> Creating podcast...');
    const voice = $("#atm-voice-select").val();
    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_podcast",
        post_id: postId,
        script: script, // Send the script from the textarea
        voice: voice,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          button.html("✅ Success! Refreshing...");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          alert("Error: ".response.data);
          resetButton(button, "Generate Podcast");
        }
      },
      error: function () {
        alert("An error occurred.");
        resetButton(button, "Generate Podcast");
      },
    });
  });

  // In admin.js, the existing 'change' handler will be updated
  $("#atm-article-type-select")
    .on("change", function () {
      const selectedType = $(this).val();

      if (selectedType === "rss_feed") {
        $("#atm-rss-feed-wrapper").slideDown();
        $("#atm-standard-article-wrapper").slideUp();
      } else {
        $("#atm-standard-article-wrapper").slideDown();
        $("#atm-rss-feed-wrapper").slideUp();

        // This is the existing logic for the "Latest News" sub-options
        if (selectedType === "news") {
          $("#atm-news-source-wrapper").slideDown();
          $("#atm-force-fresh-wrapper").slideDown();
        } else {
          $("#atm-news-source-wrapper").slideUp();
          $("#atm-force-fresh-wrapper").slideUp();
        }
      }
    })
    .trigger("change"); // Trigger on page load to set the correct initial state

  // Enhanced RSS handling functions

  // Function to handle both fetch and search with enhanced options
  function fetchRSSArticles(button, keyword = "", useScraping = false) {
    const resultsContainer = $("#atm-rss-results");
    const originalText = button.html();
    const postId = button.closest(".postbox").find("#post_ID").val();

    button
      .prop("disabled", true)
      .html(
        '<div class="atm-spinner"></div> ' +
          (useScraping ? "Deep searching..." : "Fetching...")
      );
    resultsContainer.html("");

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "fetch_rss_articles",
        keyword: keyword,
        post_id: postId,
        use_scraping: useScraping,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.data.length > 0) {
          let html = '<div class="atm-rss-results-header">';
          html +=
            '<p style="margin: 0 0 15px 0; color: #a0aec0; font-size: 13px;">';
          html += "Found " + response.data.length + " articles";
          if (keyword) html += ' matching "' + keyword + '"';
          html += "</p></div>";

          html +=
            '<ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 15px;">';
          response.data.forEach(function (article) {
            const relevanceClass =
              article.relevance === "high" ? "atm-high-relevance" : "";
            const relevanceBadge =
              article.relevance === "high"
                ? '<span class="atm-relevance-badge">High Match</span>'
                : "";

            html += `<li class="atm-rss-item ${relevanceClass}" style="padding: 15px; background: #2d3748; border-radius: 6px; border: 1px solid #4a5568; position: relative;">
                                ${relevanceBadge}
                                <div class="atm-article-title" style="font-weight: 600; margin-bottom: 8px; line-height: 1.3;">${article.title}</div>
                                <div class="atm-article-meta" style="font-size: 12px; color:#a0aec0; margin-bottom: 8px;">${article.source} | ${article.date}</div>
                                <div class="atm-article-excerpt" style="font-size: 13px; color: #cbd5e1; margin-bottom: 12px; line-height: 1.4;">${(article.description || "").substring(0, 150)}...</div>
                                <div class="atm-article-actions">
                                    <button class="atm-button atm-button-small atm-primary generate-from-rss-btn" 
                                            data-url="${article.link}" 
                                            data-title="${escapeHtml(article.title)}" 
                                            data-guid="${article.guid}"
                                            data-content="${escapeHtml(article.content || article.description || "")}">
                                        Generate Article
                                    </button>
                                    <a href="${article.link}" target="_blank" rel="noopener" class="atm-button atm-button-small" style="margin-left: 8px; text-decoration: none; color: inherit;">
                                        View Original
                                    </a>
                                </div>
                             </li>`;
          });
          html += "</ul>";
          resultsContainer.html(html);
        } else {
          let errorMsg = "No articles found.";
          if (keyword) {
            errorMsg +=
              ' Try different keywords or check if your RSS feeds contain recent content about "' +
              keyword +
              '".';
          }
          resultsContainer.html(
            '<div class="atm-error">' + errorMsg + "</div>"
          );
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = "An error occurred while fetching articles.";
        if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMsg = xhr.responseJSON.data;
        }
        resultsContainer.html('<div class="atm-error">' + errorMsg + "</div>");
      },
      complete: function () {
        button.prop("disabled", false).html(originalText);
      },
    });
  }

  // Enhanced RSS search with deep content analysis
  $("#atm-search-rss-btn").on("click", function () {
    const keyword = $("#atm-rss-keyword").val().trim();
    if (!keyword) {
      alert("Please enter a keyword.");
      return;
    }

    // Check if deep search is enabled
    const useDeepSearch = $("#atm-rss-deep-search").is(":checked");
    fetchRSSArticles($(this), keyword, useDeepSearch);
  });

  // Regular fetch without keyword
  $("#atm-fetch-rss-btn").on("click", function () {
    fetchRSSArticles($(this));
  });

  // Enhanced article generation with full content option
  $("#atm-rss-results").on("click", ".generate-from-rss-btn", function () {
    const button = $(this);
    const originalText = button.html();
    button
      .prop("disabled", true)
      .html('<div class="atm-spinner"></div> Generating...');
    const postId = button.closest(".postbox").find("#post_ID").val();

    // Check if full content scraping should be used
    const useFullContent = $("#atm-rss-use-full-content").is(":checked");

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_article_from_rss",
        article_url: button.data("url"),
        article_title: button.data("title"),
        article_guid: button.data("guid"),
        rss_content: button.data("content"),
        use_full_content: useFullContent,
        post_id: postId,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          updateEditorContent(
            response.data.article_title,
            response.data.article_content
          );
          button.html("✅ Article Generated").css("background", "#2f855a");
          setTimeout(() => {
            button.closest("li").fadeOut(1000);
          }, 2000);
        } else {
          alert("Error: " + response.data);
          button.prop("disabled", false).html(originalText);
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = "An error occurred during article generation.";
        if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMsg = "Error: " + xhr.responseJSON.data;
        }
        alert(errorMsg);
        button.prop("disabled", false).html(originalText);
      },
    });
  });

  // Helper function to escape HTML
  function escapeHtml(text) {
    if (!text) return "";
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  function fetchHeadlines(button, keyword = "") {
    const resultsContainer = $("#atm-google-news-results");
    const country = $("#atm-google-news-country").val();
    const language = $("#atm-google-news-language").val();
    const originalText = button.html();

    button
      .prop("disabled", true)
      .html('<div class="atm-spinner"></div> Fetching...');
    resultsContainer.html("");

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "fetch_google_news_headlines",
        country: country,
        language: language,
        keyword: keyword,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.data.length > 0) {
          let html =
            '<ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 15px;">';
          response.data.forEach(function (article) {
            // THE FIX: Display the date next to the source
            html += `<li style="padding: 15px; background: #1a202c; border-radius: 6px;">
                                    <div style="margin-bottom: 10px; font-weight: 600;">${article.title} - <em style="color:#a0aec0; font-weight: 400;">(${article.source} | ${article.date})</em></div>
                                    <button class="atm-button atm-button-small atm-primary generate-from-headline-btn" data-url="${article.link}" data-title="${escape(article.title)}">Generate Article</button>
                                 </li>`;
          });
          html += "</ul>";
          resultsContainer.html(html);
        } else {
          resultsContainer.html(
            '<div class="atm-error">No high-quality headlines found. Please try a different keyword.</div>'
          );
        }
      },
      error: function () {
        resultsContainer.html(
          '<div class="atm-error">An error occurred while fetching news.</div>'
        );
      },
      complete: function () {
        button.prop("disabled", false).html(originalText);
      },
    });
  }

  // 1. Fetch Top 5 Headlines
  $("#atm-fetch-headlines-btn").on("click", function () {
    fetchHeadlines($(this));
  });

  // 2. Fetch News by Keyword
  $("#atm-fetch-keyword-news-btn").on("click", function () {
    const keyword = $("#atm-google-news-keyword").val().trim();
    if (!keyword) {
      alert("Please enter a keyword to search for.");
      return;
    }
    fetchHeadlines($(this), keyword);
  });

  // 3. Generate Article from a specific headline
  $("#atm-google-news-results").on(
    "click",
    ".generate-from-headline-btn",
    function () {
      const button = $(this);
      const articleUrl = button.data("url");
      const articleTitle = unescape(button.data("title"));
      const language = $("#atm-google-news-language").val(); // Get selected language

      button
        .prop("disabled", true)
        .html('<div class="atm-spinner"></div> Generating...');

      $.ajax({
        url: atm_ajax.ajax_url,
        type: "POST",
        data: {
          action: "generate_article_from_url",
          article_url: articleUrl,
          article_title: articleTitle,
          language: language, // Send language
          nonce: atm_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            updateEditorContent(
              response.data.article_title,
              response.data.article_content
            );
            button.html("✅ Inserted").css("background", "#2f855a");
          } else {
            alert("Error: " + response.data);
            button.prop("disabled", false).html("Generate Article");
          }
        },
        error: function () {
          alert("An unknown error occurred during article generation.");
          button.prop("disabled", false).html("Generate Article");
        },
      });
    }
  );

  // --- Generate Article Button ---
  $("#atm-generate-article-btn").on("click", function () {
    const button = $(this);
    if (button.prop("disabled")) return;
    const postId = button.data("post-id");
    generateArticle(button, postId);
  });

  // Show/hide the News options based on the Article Type
  // This handler seems duplicated, the one at the top of the file handles all cases.
  // $('#atm-article-type-select').on('change', function() {
  //     if ($(this).val() === 'news') {
  //         $('#atm-news-source-wrapper').slideDown();
  //         $('#atm-force-fresh-wrapper').slideDown();
  //     } else {
  //         $('#atm-news-source-wrapper').slideUp();
  //         $('#atm-force-fresh-wrapper').slideUp();
  //     }
  // }).trigger('change'); // Trigger on page load

  // --- Generate Image Button ---
  $("#atm-generate-image-btn").on("click", function () {
    const button = $(this);
    if (button.prop("disabled")) return;
    const postId = button.data("post-id");
    generateImage(button, postId);
  });

  // --- Generate Podcast Button ---
  $("#atm-generate-podcast-btn").on("click", function () {
    const button = $(this);
    if (button.prop("disabled")) return;
    const postId = button.data("post-id");
    generatePodcast(button, postId);
  });

  // Regenerate button
  $(".atm-regenerate").on("click", function () {
    const button = $(this);
    if (button.prop("disabled")) return;
    const postId = button.data("post-id");
    generatePodcast(button, postId);
  });

  // Image uploader for podcast cover
  $(document).on("click", ".atm-upload-image, .atm-change-image", function (e) {
    e.preventDefault();
    const button = $(this);
    const postId = button.data("post-id");
    const mediaUploader = wp.media({
      title: "Select Podcast Cover Image",
      button: { text: "Use This Image" },
      multiple: false,
      library: { type: "image" },
    });
    mediaUploader.on("select", function () {
      const attachment = mediaUploader
        .state()
        .get("selection")
        .first()
        .toJSON();
      $.ajax({
        url: atm_ajax.ajax_url,
        type: "POST",
        data: {
          action: "upload_podcast_image",
          post_id: postId,
          image_url: attachment.url,
          nonce: atm_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            location.reload();
          }
        },
      });
    });
    mediaUploader.open();
  });

  /**
   * Article Generation with conditional logic for user-provided titles and article types.
   */
  function generateArticle(button, postId) {
    button.prop("disabled", true);

    const articleType = $("#atm-article-type-select").val();
    const newsSource = $("#atm-news-source-select").val(); // Get the selected news source
    const forceFresh = $("#atm-force-fresh-search").is(":checked");
    const keyword = $("#atm-article-keyword").val().trim();
    const title = $("#atm-article-title-input").val().trim();
    const model = $("#atm-article-model-select").val();
    const writingStyle = $("#atm-writing-style-select").val();
    const customPrompt = $("#atm-custom-article-prompt").val().trim();
    const wordCount = $("#atm-word-count-select").val();

    const topic = title || keyword;
    if (!topic) {
      alert("Please provide a keyword or an article title.");
      resetButton(button, "Generate Article");
      return;
    }

    if (articleType === "news") {
      button.html('<div class="atm-spinner"></div> Searching for news...');

      $.ajax({
        url: atm_ajax.ajax_url,
        type: "POST",
        data: {
          action: "generate_news_article",
          topic: topic,
          model: model,
          force_fresh: forceFresh,
          news_source: newsSource, // Send the selected news source
          nonce: atm_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            button.html('<div class="atm-spinner"></div> Writing article...');
            updateEditorContent(
              response.data.article_title,
              response.data.article_content
            );
            checkAndGenerateImage(button, $("#post_ID").val()); // This calls your image logic
          } else {
            alert("Error: " + response.data);
            resetButton(button, "Generate Article");
          }
        },
        error: function () {
          alert("An error occurred during news generation.");
          resetButton(button, "Generate Article");
        },
      });
    } else {
      // ... (creative article logic remains the same)
      if (title) {
        button.html('<div class="atm-spinner"></div> Generating Content...');
        generateContentForTitle(
          title,
          postId,
          button,
          model,
          writingStyle,
          customPrompt,
          wordCount,
          articleType
        );
      } else if (keyword) {
        button.html('<div class="atm-spinner"></div> Generating Title...');
        generateTitleFromKeyword(
          keyword,
          postId,
          button,
          model,
          writingStyle,
          customPrompt,
          wordCount,
          articleType
        );
      }
    }
  }

  function generateTitleFromKeyword(
    keyword,
    postId,
    button,
    model,
    writingStyle,
    customPrompt,
    wordCount,
    articleType
  ) {
    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_article_title",
        post_id: postId,
        keyword: keyword,
        title: "",
        model: model,
        article_type: articleType, // Add article type here
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          const generatedTitle = response.data.article_title;
          button.html('<div class="atm-spinner"></div> Generating Content...');
          generateContentForTitle(
            generatedTitle,
            postId,
            button,
            model,
            writingStyle,
            customPrompt,
            wordCount,
            articleType
          );
        } else {
          alert("Error generating title: " + response.data);
          resetButton(button, "Generate Article");
        }
      },
      error: function () {
        alert("An error occurred during title generation.");
        resetButton(button, "Generate Article");
      },
    });
  }

  function generateContentForTitle(
    finalTitle,
    postId,
    button,
    model,
    writingStyle,
    customPrompt,
    wordCount,
    articleType
  ) {
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
        nonce: atm_ajax.nonce,
      },
      success: function (contentResponse) {
        if (contentResponse.success) {
          updateEditorContent(finalTitle, contentResponse.data.article_content);
          checkAndGenerateImage(button, postId); // This calls your image logic
        } else {
          alert("Error generating content: " + contentResponse.data);
          resetButton(button, "Generate Article");
        }
      },
      error: function () {
        alert("An error occurred during content generation.");
        resetButton(button, "Generate Article");
      },
    });
  }

  function generateImage(button, postId) {
    button
      .prop("disabled", true)
      .html('<div class="atm-spinner"></div> Generating Image...');
    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_featured_image",
        post_id: postId,
        prompt: $("#atm-image-prompt").val(),
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Update the featured image UI in both editors
          const isBlockEditor =
            document.body.classList.contains("block-editor-page");

          if (isBlockEditor) {
            wp.data
              .dispatch("core/editor")
              .editPost({ featured_media: response.data.attachment_id });
          } else {
            // For the Classic Editor
            $("#postimagediv .inside").html(response.data.html);
          }
          button.html("✅ Image Set!");
          setTimeout(
            () => resetButton(button, "Generate & Set Featured Image"),
            2000
          );
        } else {
          alert("Error: " + response.data);
          resetButton(button, "Generate & Set Featured Image");
        }
      },
      error: function () {
        alert("An error occurred during image generation.");
        resetButton(button, "Generate & Set Featured Image");
      },
    });
  }

  function generatePodcast(button, postId) {
    button
      .prop("disabled", true)
      .html('<div class="atm-spinner"></div> Creating podcast...');
    const section = button.closest(".atm-section");
    if (!section.find(".atm-progress-bar").length) {
      section.append(
        '<div class="atm-progress-bar"><div class="atm-progress-fill"></div></div>'
      );
    }

    const language = $("#atm-language-select").val();
    const voice = $("#atm-voice-select").val();
    const model = $("#atm-content-model-select").val();
    const customPrompt = $("#atm-custom-prompt").val();

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "generate_podcast",
        post_id: postId,
        language: language,
        voice: voice,
        model: model,
        custom_prompt: customPrompt,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          button.html("✅ Success! Refreshing...");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          alert("Error: " + response.data);
          resetButton(button, "Generate Podcast");
        }
      },
      error: function () {
        alert("An error occurred.");
        resetButton(button, "Generate Podcast");
      },
    });
  }

  function resetButton(button, text) {
    button.prop("disabled", false).html(text);
    button.closest(".atm-section").find(".atm-progress-bar").remove();
  }
});

// Make testRSSFeed globally available
window.testRSSFeed = function (feedUrl, keyword = "") {
  if (!feedUrl.trim()) {
    alert("Please enter a valid RSS feed URL.");
    return;
  }

  jQuery("#atm-rss-test-results").html(
    '<div class="atm-spinner"></div> Testing feed...'
  );

  jQuery.ajax({
    url: atm_ajax.ajax_url,
    type: "POST",
    data: {
      action: "test_rss_feed",
      feed_url: feedUrl,
      keyword: keyword,
      nonce: atm_ajax.nonce,
    },
    success: function (response) {
      if (response.success) {
        const data = response.data;
        let html = '<div class="atm-success">✅ Feed is working!</div>';
        html +=
          "<p><strong>Total articles found:</strong> " +
          data.total_found +
          "</p>";

        if (data.articles.length > 0) {
          html += "<h4>Sample Articles:</h4>";
          html += "<ul>";
          data.articles.slice(0, 3).forEach(function (article) {
            html += "<li><strong>" + article.title + "</strong><br>";
            html +=
              "<small>" +
              article.source +
              " | " +
              article.date +
              "</small></li>";
          });
          html += "</ul>";
        }

        jQuery("#atm-rss-test-results").html(html);
      } else {
        jQuery("#atm-rss-test-results").html(
          '<div class="atm-error">❌ ' + response.data + "</div>"
        );
      }
    },
    error: function () {
      jQuery("#atm-rss-test-results").html(
        '<div class="atm-error">❌ Connection error while testing feed.</div>'
      );
    },
  });
};

jQuery(document).ready(function ($) {
  // Only run this script on the main settings page
  if (!$("#atm-detect-subtitle-key-btn").length) {
    return;
  }

  $("#atm-detect-subtitle-key-btn").on("click", function () {
    const button = $(this);
    const statusEl = $("#atm-scan-status");
    const inputEl = $("#atm_theme_subtitle_key_field");

    button.prop("disabled", true).text("Scanning...");
    statusEl.text("Loading editor in the background...");
    inputEl.val("");

    // Create a hidden iframe to load the post editor
    const iframe = $("<iframe />", {
      src: "post-new.php",
      style:
        "width:0; height:0; border:0; position:absolute; top: -9999px; left: -9999px;",
    }).appendTo("body");

    iframe.on("load", function () {
      try {
        const iframeDoc = iframe.contents();
        let foundKey = "";

        // --- MODIFIED: Expanded keyword list for better detection ---
        const keywords = [
          "subtitle",
          "sub heading",
          "sub-heading",
          "sub_heading",
          "subheading",
          "tagline",
          "secondary title",
          "alt title",
          "alternative title",
          "sub title", // spaced version
          "small title",
          "headline",
          "subheadline",
          "sub-headline",
          "short description",
          "lead", // journalism/press style
          "intro", // intro line under main title
          "kicker", // used in news/blog themes
        ];

        // Find all labels and check them
        iframeDoc.find("label").each(function () {
          const labelText = $(this).text().toLowerCase().trim();

          // Check if any keyword exists in the label text
          if (keywords.some((keyword) => labelText.includes(keyword))) {
            const inputId = $(this).attr("for");
            if (inputId) {
              const inputField = iframeDoc.find("#" + inputId);
              if (inputField.length) {
                foundKey = inputField.attr("name");
                return false; // Exit the loop once found
              }
            }
          }
        });

        if (foundKey) {
          // Sometimes the name is in a format like "kadence_custom_meta[_kadence_post_subtitle]"
          // We need to extract the actual key.
          const match = foundKey.match(/\[([^\]]+)\]/);
          if (match && match[1]) {
            foundKey = match[1];
          }

          inputEl.val(foundKey);
          statusEl
            .text("✅ Found potential key: " + foundKey)
            .css("color", "#2f855a");
        } else {
          statusEl
            .text(
              "❌ No common subtitle field was found. Please check your theme's documentation and enter the key manually."
            )
            .css("color", "#9b2c2c");
        }
      } catch (e) {
        console.error("Content AI Studio Scan Error:", e);
        statusEl
          .text(
            "Error during scan. Please check the console and enter the key manually."
          )
          .css("color", "#9b2c2c");
      } finally {
        // Clean up
        iframe.remove();
        button.prop("disabled", false).text("Smart Scan");
      }
    });
  });
});

jQuery(document).ready(function ($) {
  if (!$("#atm-detect-subtitle-key-btn").length) {
    return;
  }
  $("#atm-detect-subtitle-key-btn").on("click", function () {
    const button = $(this);
    const statusEl = $("#atm-scan-status");
    const inputEl = $("#atm_theme_subtitle_key_field");
    button.prop("disabled", true).text("Scanning...");
    statusEl.text("Loading editor in the background...");
    inputEl.val("");
    const iframe = $("<iframe />", {
      src: "post-new.php",
      style:
        "width:0; height:0; border:0; position:absolute; top: -9999px; left: -9999px;",
    }).appendTo("body");
    iframe.on("load", function () {
      try {
        const iframeDoc = iframe.contents();
        let foundKey = "";
        const keywords = [
          "subtitle",
          "sub heading",
          "sub-heading",
          "sub_heading",
          "subheading",
          "tagline",
          "secondary title",
          "alt title",
          "alternative title",
          "sub title",
          "small title",
          "headline",
          "subheadline",
          "sub-headline",
          "short description",
          "lead",
          "intro",
          "kicker",
        ];
        iframeDoc.find("label").each(function () {
          const labelText = $(this).text().toLowerCase().trim();
          if (keywords.some((keyword) => labelText.includes(keyword))) {
            const inputId = $(this).attr("for");
            if (inputId) {
              const inputField = iframeDoc.find("#" + inputId);
              if (inputField.length) {
                foundKey = inputField.attr("name");
                return false;
              }
            }
          }
        });
        if (foundKey) {
          const match = foundKey.match(/\[([^\]]+)\]/);
          if (match && match[1]) {
            foundKey = match[1];
          }
          inputEl.val(foundKey);
          statusEl
            .text("✅ Found potential key: " + foundKey)
            .css("color", "#2f855a");
        } else {
          statusEl
            .text(
              "❌ No common subtitle field was found. Please enter the key manually."
            )
            .css("color", "#9b2c2c");
        }
      } catch (e) {
        console.error("Content AI Studio Scan Error:", e);
        statusEl
          .text("Error during scan. Please enter the key manually.")
          .css("color", "#9b2c2c");
      } finally {
        iframe.remove();
        button.prop("disabled", false).text("Smart Scan");
      }
    });
  });
});

// Campaign Code

jQuery(document).ready(function ($) {
  // Only run this code on our campaign page
  if (!$("#atm-campaign-form").length && !$(".atm-delete-campaign").length) {
    return;
  }

  // Handle Campaign Form Submission
  $("#atm-campaign-form").on("submit", function (e) {
    e.preventDefault();

    const button = $("#atm-save-campaign-btn");
    const originalText = button.val();
    button.val("Saving...").prop("disabled", true);

    // Manually gather form data into an object
    const formData = $(this).serializeArray();
    const data = {};
    $.map(formData, function (n, i) {
      data[n["name"]] = n["value"];
    });

    // Manually add the global nonce
    data.nonce = atm_ajax.nonce;

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: data, // Send the data object
      success: function (response) {
        if (response.success) {
          // Redirect to the campaign list on success
          window.location.href = response.data.redirect_url;
        } else {
          alert("Error: " + response.data);
          button.val(originalText).prop("disabled", false);
        }
      },
      error: function () {
        alert("An unknown error occurred.");
        button.val(originalText).prop("disabled", false);
      },
    });
  });

  // Handle "Delete" action from the list table
  $(".atm-delete-campaign").on("click", function (e) {
    e.preventDefault();

    if (
      !confirm(
        "Are you sure you want to delete this campaign? This cannot be undone."
      )
    ) {
      return;
    }

    const link = $(this);
    const campaignId = link.data("id");
    const row = link.closest("tr");

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "delete_campaign",
        id: campaignId,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          row.fadeOut(300, function () {
            $(this).remove();
          });
        } else {
          alert("Error: " + response.data);
        }
      },
    });
  });

  // Handle "Run Now" action from both the list and edit pages
  $(document).on("click", ".atm-run-campaign, #atm-run-now-btn", function (e) {
    e.preventDefault();

    const button = $(this);
    const campaignId = button.data("id");
    const statusEl = $("#atm-run-now-status");

    button.prop("disabled", true);
    statusEl.text("Running...");

    $.ajax({
      url: atm_ajax.ajax_url,
      type: "POST",
      data: {
        action: "run_campaign_now",
        id: campaignId,
        nonce: atm_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          statusEl.text("✅ " + response.data.message);
        } else {
          statusEl.text("❌ Error: " + response.data);
        }
      },
      error: function () {
        statusEl.text("❌ An unknown error occurred.");
      },
      complete: function () {
        setTimeout(function () {
          button.prop("disabled", false);
          statusEl.text("");
        }, 5000);
      },
    });
  });
});
