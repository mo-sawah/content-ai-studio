jQuery(document).ready(function ($) {
  // Find each multipage container on the page (in case of multiple shortcodes)
  $(".atm-multipage-container").each(function () {
    const container = $(this);
    const contentArea = container.find(".atm-multipage-content");
    const navNumbers = container.find(".atm-nav-number");
    const postId = container.data("post-id");
    let isLoading = false;

    navNumbers.on("click", function () {
      const button = $(this);
      const pageIndex = button.data("page-index");

      if (button.hasClass("active") || isLoading) {
        return;
      }

      isLoading = true;
      navNumbers.removeClass("active");
      button.addClass("active");

      // Add a loading indicator
      contentArea
        .css("opacity", 0.5)
        .prepend('<div class="atm-multipage-loader"></div>');

      $.ajax({
        url: atm_multipage_data.ajax_url,
        type: "POST",
        data: {
          action: "get_multipage_page_content",
          nonce: atm_multipage_data.nonce,
          post_id: postId,
          page_index: pageIndex,
        },
        success: function (response) {
          if (response.success) {
            // Fade out, replace content, then fade in
            contentArea.fadeOut(150, function () {
              $(this).html(response.data.html_content).fadeIn(150);
            });
          } else {
            contentArea.html(
              '<p class="atm-multipage-error">Error: Could not load content. Please try again.</p>'
            );
          }
        },
        error: function () {
          contentArea.html(
            '<p class="atm-multipage-error">Error: A network error occurred. Please try again.</p>'
          );
        },
        complete: function () {
          isLoading = false;
          contentArea.css("opacity", 1);
          container.find(".atm-multipage-loader").remove();
        },
      });
    });
  });
});
