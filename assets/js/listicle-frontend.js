document.addEventListener("DOMContentLoaded", function () {
  // Find all listicle items
  const items = document.querySelectorAll(".atm-listicle-item");
  const progressBar = document.querySelector(".atm-listicle-progress-bar");

  // Scroll animation for items
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
        }
      });
    },
    {
      threshold: 0.15,
    }
  );

  items.forEach((item) => {
    observer.observe(item);
  });

  // Update progress bar
  function updateProgress() {
    if (!progressBar) return;

    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight;
    const winHeight = window.innerHeight;
    const scrollPercent = scrollTop / (docHeight - winHeight);
    const progressPercent = Math.max(0, Math.min(100, scrollPercent * 100));

    progressBar.style.height = progressPercent + "%";
  }

  window.addEventListener("scroll", updateProgress);
  updateProgress();

  // Add print functionality
  const printButton = document.querySelector(".atm-listicle-print-button");
  if (printButton) {
    printButton.addEventListener("click", function () {
      window.print();
    });
  }
});
