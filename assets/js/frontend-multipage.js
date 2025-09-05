jQuery(document).ready(function ($) {
  function initializeMultipageContainers() {
    $(".atm-multipage-container").each(function () {
      const container = $(this);
      const contentArea = container.find(".atm-multipage-content");
      const navContainer = container.find(".atm-post-navigation");
      const postId = container.data("post-id");
      let isLoading = false;
      let totalPages = container.find(".atm-page-number").length;

      // Function to load content for a specific page
      const loadPage = function (pageIndex) {
        if (isLoading) return;

        isLoading = true;

        // Update URL hash
        window.location.hash = "page=" + (pageIndex + 1);

        // Update active state on buttons
        navContainer.find(".atm-page-number").removeClass("active");
        navContainer
          .find('.atm-page-number[data-page-index="' + pageIndex + '"]')
          .addClass("active");

        // Update prev/next button states
        navContainer
          .find(".atm-nav-item.prev")
          .prop("disabled", pageIndex === 0);
        navContainer
          .find(".atm-nav-item.next")
          .prop("disabled", pageIndex === totalPages - 1);

        // Add loading indicator
        contentArea
          .css("opacity", 0.5)
          .html('<div class="atm-multipage-loader"></div>');

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
              contentArea.html(response.data.html_content);
              // Scroll to top of the article container
              $("html, body").animate(
                {
                  scrollTop: container.offset().top - 30, // 30px offset for admin bar etc.
                },
                300
              );
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
            // BUG FIX: Ensure opacity is always reset and loader is removed
            isLoading = false;
            contentArea.css("opacity", 1);
            container.find(".atm-multipage-loader").remove();
          },
        });
      };

      // Event handler for all navigation clicks
      navContainer.on("click", "button", function () {
        const button = $(this);
        if (button.is(":disabled")) return;

        const pageIndex = parseInt(button.data("page-index"), 10);
        loadPage(pageIndex);
      });

      // Check URL hash on page load for deep linking
      const initialHash = window.location.hash;
      if (initialHash && initialHash.startsWith("#page=")) {
        const pageNum = parseInt(initialHash.replace("#page=", ""), 10);
        if (!isNaN(pageNum) && pageNum > 1 && pageNum <= totalPages) {
          loadPage(pageNum - 1);
        }
      }
    });
  }

  // Initialize all containers on the page
  initializeMultipageContainers();
});
