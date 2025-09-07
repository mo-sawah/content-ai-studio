(function ($) {
  function initColorPickers(scope) {
    $(scope)
      .find(".atm-color-picker")
      .wpColorPicker({
        clear: function () {},
        change: function () {},
      });
  }

  function initTabs() {
    $(".atm-tabs").each(function () {
      const $wrap = $(this);
      const $navBtns = $wrap.find(".atm-tab");
      const $panels = $wrap.find(".atm-tabs__panel");

      $navBtns.on("click", function () {
        const tab = $(this).data("tab");
        $navBtns.removeClass("is-active");
        $(this).addClass("is-active");
        $panels
          .removeClass("is-active")
          .filter('[data-tab-panel="' + tab + '"]')
          .addClass("is-active");
      });
    });
  }

  function initAdvancedToggle() {
    const $toggle = $("#atm_colors_advanced_toggle");
    if (!$toggle.length) return;

    function apply(open) {
      $("details.atm-advanced").each(function () {
        this.open = !!open;
      });
    }

    $toggle.on("change", function () {
      apply(this.checked);
    });
  }

  $(function () {
    initColorPickers(document);
    initTabs();
    initAdvancedToggle();
  });
})(jQuery);
