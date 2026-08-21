(function () {
  "use strict";

  function markPlayed(el) {
    if (!el || el.__bbPlayed) return;
    el.__bbPlayed = true;
    el.classList.add("bb-3d--play");
  }

  function setup(el) {
    if (!el || el.__bbObs) return;
    el.__bbObs = true;

    const replay = el.getAttribute("data-bb-3d-replay") === "1";

    // add a class so CSS can style replay-enabled elements differently
    if (replay) {
      el.classList.add("bb-3d--replay");
    } else {
      el.classList.remove("bb-3d--replay"); // optional
    }

    // If no IntersectionObserver, just play immediately
    if (!("IntersectionObserver" in window)) {
      markPlayed(el);
      return;
    }

    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          if (replay) el.__bbPlayed = false;
          markPlayed(el);

          if (!replay) io.unobserve(el);
        } else if (replay) {
          el.classList.remove("bb-3d--play");
          void el.offsetHeight;
        }
      });
    }, { threshold: 0.25, rootMargin: "0px 0px 0% 0px" });

    io.observe(el);
  }

  function scan(root) {
    (root || document).querySelectorAll(".text-3d.deep-text-3d").forEach(setup);
  }

  document.addEventListener("DOMContentLoaded", function () {
    scan(document);
  });

  window.addEventListener("elementor/frontend/init", function () {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction("frontend/element_ready/widget", function ($scope) {
        scan($scope[0]);
      });
    }
    scan(document);
  });
})();
