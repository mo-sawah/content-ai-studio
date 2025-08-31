jQuery(document).ready(function ($) {
  $(".atm-takeaways-container").each(function () {
    var $container = $(this);
    var $header = $container.find(".atm-takeaways-header");
    var $content = $container.find(".atm-takeaways-content");
    var $toggle = $container.find(".atm-takeaways-toggle");

    // Hide content by default
    $content.hide();

    $header.on("click", function () {
      $content.slideToggle(300);
      $container.toggleClass("expanded");
      if ($container.hasClass("expanded")) {
        $toggle.html('Hide <span class="atm-arrow">▲</span>');
      } else {
        $toggle.html('Show Key Takeaways <span class="atm-arrow">▼</span>');
      }
    });
  });
});
