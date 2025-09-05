jQuery(document).ready(function ($) {
  function initializeMultipageContainers() {
    $(".atm-multipage-container").each(function () {
      const container = $(this);
      const contentArea = container.find(".atm-multipage-content");
      const navContainer = container.find(".atm-post-navigation");
      const postId = container.data("post-id");
      let isLoading = false;
      let totalPages = navContainer.find(".atm-page-number").length;
      let page_data = {}; // Cache for loaded pages

      // Pre-load the first page's content into the cache
      page_data[0] = contentArea.html();

      const loadPage = function (pageIndex) {
        if (isLoading) return;
        isLoading = true;

        window.location.hash = "page=" + (pageIndex + 1);

        navContainer.find(".atm-page-number").removeClass("active");
        navContainer
          .find('.atm-page-number[data-page-index="' + pageIndex + '"]')
          .addClass("active");
        navContainer
          .find(".atm-nav-item.prev")
          .prop("disabled", pageIndex === 0);
        navContainer
          .find(".atm-nav-item.next")
          .prop("disabled", pageIndex === totalPages - 1);

        // Start the fade-out transition
        contentArea.addClass("atm-loading-content");

        // After fade out, load new content
        setTimeout(function () {
          // Check cache first
          if (page_data[pageIndex]) {
            contentArea.html(page_data[pageIndex]);
            finishLoading();
            return;
          }

          // If not in cache, show loader and fetch
          contentArea.html('<div class="atm-multipage-loader"></div>');

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
                page_data[pageIndex] = response.data.html_content; // Cache the new content
                contentArea.html(response.data.html_content);
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
              finishLoading();
            },
          });
        }, 200); // This timeout matches the CSS transition duration
      };

      const finishLoading = function () {
        // Scroll to the top of the article container
        $("html, body").animate(
          {
            scrollTop: container.offset().top - 50, // 50px offset for admin bar etc.
          },
          300,
          function () {
            // Fade the new content back in
            contentArea.removeClass("atm-loading-content");
            isLoading = false;
          }
        );
      };

      navContainer.on("click", "button", function () {
        const button = $(this);
        if (button.is(":disabled")) return;

        let currentPageIndex = parseInt(
          navContainer.find(".atm-page-number.active").data("page-index"),
          10
        );
        let newPageIndex;

        if (button.hasClass("prev")) {
          newPageIndex = currentPageIndex - 1;
        } else if (button.hasClass("next")) {
          newPageIndex = currentPageIndex + 1;
        } else {
          newPageIndex = parseInt(button.data("page-index"), 10);
        }

        if (
          newPageIndex >= 0 &&
          newPageIndex < totalPages &&
          newPageIndex !== currentPageIndex
        ) {
          loadPage(newPageIndex);
        }
      });

      const initialHash = window.location.hash;
      if (initialHash && initialHash.startsWith("#page=")) {
        const pageNum = parseInt(initialHash.replace("#page=", ""), 10);
        if (!isNaN(pageNum) && pageNum > 1 && pageNum <= totalPages) {
          loadPage(pageNum - 1);
        }
      }
    });
  }
  initializeMultipageContainers();
});
