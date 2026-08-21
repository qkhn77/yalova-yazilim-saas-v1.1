/**
 * Deep Navigation — mobile accordion + drawer
 */
(function () {
  const isElementorEditMode = () => {
    if (document.body?.classList.contains("elementor-editor-active")) {
      return true;
    }
    if (typeof elementorFrontend !== "undefined" && typeof elementorFrontend.isEditMode === "function") {
      try {
        return !!elementorFrontend.isEditMode();
      } catch {
        return false;
      }
    }
    return false;
  };

  const isDeepNavMenuLink = (anchor) => {
    if (!anchor || anchor.tagName !== "A") return false;
    if (anchor.classList.contains("cd-nav-trigger")) return false;

    return !!anchor.closest(
      ".cd-primary-nav, .treethemes-nav-menu-nav, .deep-nav-menu-container, .treethemes-nav-menu-container"
    );
  };

  const toggleAccordionItem = (navMenu, menuItem) => {
    if (!menuItem) return;

    const willOpen = !menuItem.classList.contains("is-accordion-open");
    const siblings = menuItem.parentElement?.querySelectorAll(
      ":scope > .menu-item-has-children.is-accordion-open, :scope > .deep-mega-menu.is-accordion-open"
    );
    siblings?.forEach((el) => {
      if (el !== menuItem) el.classList.remove("is-accordion-open");
    });

    menuItem.classList.toggle("is-accordion-open", willOpen);
  };

  const getInteraction = (navMenu, target) => {
    if (!navMenu || !target || !navMenu.classList.contains("deep-nav-is-toggled")) return null;
    if (!navMenu.classList.contains("mobile-menu-active")) return null;

    const container = navMenu.querySelector(".treethemes-nav-menu-container, .deep-nav-menu-container");
    if (!container || !container.contains(target)) return null;

    const indicator = target.closest?.(".deep-submenu-indicator");
    if (indicator && container.contains(indicator)) {
      return {
        type: "toggle",
        menuItem: indicator.closest(".menu-item-has-children, .deep-mega-menu"),
      };
    }

    const link = target.closest?.("a");
    if (!link || !container.contains(link)) return null;

    if (link.closest(".sub-menu, .deep-mega-menu-panel")) {
      return { type: "navigate", link };
    }

    const parentLi = link.closest("li.menu-item, li.deep-mega-menu");
    if (!parentLi) return { type: "navigate", link };

    const directLink = parentLi.querySelector(":scope > a");
    const isDirectParentLink = directLink && (link === directLink || directLink.contains(link));

    const hasSubmenu =
      parentLi.classList.contains("menu-item-has-children") ||
      parentLi.classList.contains("deep-mega-menu") ||
      !!parentLi.querySelector(":scope > .sub-menu") ||
      !!parentLi.querySelector(":scope > .deep-mega-menu-panel");

    if (isDirectParentLink && hasSubmenu) {
      return { type: "toggle", menuItem: parentLi };
    }

    return { type: "navigate", link };
  };

  /** Runs before other document capture listeners (smooth scroll, theme). */
  if (!window.__deepNavCaptureBound) {
    window.__deepNavCaptureBound = true;

    document.addEventListener(
      "click",
      (e) => {
        if (!isElementorEditMode()) return;

        const anchor = e.target?.closest?.("a");
        if (!isDeepNavMenuLink(anchor)) return;

        e.preventDefault();
      },
      true
    );

    document.addEventListener(
      "click",
      (e) => {
        const target = e.target;
        if (!target) return;

        const navMenu =
          target.closest?.(".deep__nav-menu.deep-nav-is-toggled") ||
          target.closest?.(".wcf__nav-menu.wcf-nav-is-toggled");
        if (!navMenu) return;

        const interaction = getInteraction(navMenu, target);
        if (!interaction || interaction.type !== "toggle") return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        toggleAccordionItem(navMenu, interaction.menuItem);
      },
      true
    );
  }

  const initDeepNavigation = ($scope) => {
    const root = $scope[0];
    if (!root) return;

    const navMenu = root.querySelector(".deep__nav-menu");
    if (!navMenu) return;

    // Overlay Style 6 is the only nav on all screen sizes (not a mobile drawer).
    if (navMenu.classList.contains("treethemes-nav-menu-style-6")) {
      return;
    }

    const getBreakpoint = () => {
      const bpSetting = navMenu.dataset.mobileBreakpoint;
      if (!bpSetting) return null;
      if (bpSetting === "all") return "all";
      const bpConfig = window.elementorFrontend?.config?.responsive?.activeBreakpoints;
      return bpConfig?.[bpSetting]?.value ?? 767;
    };

    const isMobileMode = () => {
      const bp = getBreakpoint();
      if (bp === null) return false;
      if (bp === "all") return true;
      return window.innerWidth <= bp;
    };

    const isDrawerMode = () => navMenu.classList.contains("mobile-menu-active");

    const setMenuMode = () => {
      const mobile = isMobileMode();
      navMenu.classList.toggle("desktop-menu-active", !mobile);
      navMenu.classList.toggle("mobile-menu-active", mobile);

      const adminbar = document.querySelector("#wpadminbar");
      const container = navMenu.querySelector(".treethemes-nav-menu-container, .deep-nav-menu-container");
      if (container && mobile) {
        container.style.top = adminbar ? `${adminbar.offsetHeight}px` : "";
      }
    };

    const closeAllAccordions = () => {
      navMenu
        .querySelectorAll(".menu-item-has-children.is-accordion-open, .deep-mega-menu.is-accordion-open")
        .forEach((item) => {
          item.classList.remove("is-accordion-open");
        });
    };

    const setHamburgerOpen = (isOpen) => {
      const hamburger = navMenu.querySelector(".treethemes-menu-hamburger.treethemes-menu-theme-trigger, .deep-menu-hamburger.deep-menu-theme-trigger");
      if (!hamburger) return;
      hamburger.classList.toggle("opened", isOpen);
      hamburger.setAttribute("aria-expanded", isOpen ? "true" : "false");
    };

    const bindDrawer = () => {
      const hamburger = navMenu.querySelector(".treethemes-menu-hamburger, .deep-menu-hamburger");
      const closeBtn = navMenu.querySelector(".treethemes-menu-close, .deep-menu-close");
      const overlay = navMenu.querySelector(".treethemes-menu-overlay, .deep-menu-overlay");
      const container = navMenu.querySelector(".treethemes-nav-menu-container, .deep-nav-menu-container");

      const openMenu = () => {
        navMenu.classList.add("deep-nav-is-toggled");
        document.body.style.overflow = "hidden";
        setHamburgerOpen(true);
      };

      const closeMenu = () => {
        navMenu.classList.remove("deep-nav-is-toggled");
        document.body.style.overflow = "";
        closeAllAccordions();
        setHamburgerOpen(false);
      };

      hamburger?.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (navMenu.classList.contains("deep-nav-is-toggled")) {
          closeMenu();
        } else {
          openMenu();
        }
      });

      closeBtn?.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
      });

      // Backdrop: close only when tapping outside the drawer panel (not via full-screen overlay hits).
      if (!navMenu.dataset.deepOutsideBound) {
        navMenu.dataset.deepOutsideBound = "1";
        document.addEventListener("click", (e) => {
          if (!navMenu.classList.contains("deep-nav-is-toggled")) return;
          if (!isDrawerMode()) return;
          if (container?.contains(e.target) || hamburger?.contains(e.target)) return;
          closeMenu();
        });
      }

      if (container && !container.dataset.deepContainerClickBound) {
        container.dataset.deepContainerClickBound = "1";

        container.addEventListener("click", (e) => {
          if (!isDrawerMode() || !navMenu.classList.contains("deep-nav-is-toggled")) return;

          const interaction = getInteraction(navMenu, e.target);
          if (!interaction) return;

          if (interaction.type === "toggle") {
            e.preventDefault();
            e.stopPropagation();
            toggleAccordionItem(navMenu, interaction.menuItem);
            return;
          }

          closeMenu();
        });
      }
    };

    setMenuMode();
    bindDrawer();

    if (!navMenu.dataset.deepResizeBound) {
      navMenu.dataset.deepResizeBound = "1";
      window.addEventListener("resize", () => {
        setMenuMode();
        if (!isMobileMode()) {
          closeAllAccordions();
          navMenu.classList.remove("deep-nav-is-toggled");
          document.body.style.overflow = "";
        }
      });
    }
  };

  const hookHandler = () => {
    if (!window.elementorFrontend?.hooks) return;

    elementorFrontend.hooks.addAction("frontend/element_ready/deep--navigation.default", ($scope) =>
      initDeepNavigation($scope)
    );

    elementorFrontend.hooks.addAction("frontend/element_ready/treethemes--navigation.default", ($scope) =>
      initDeepNavigation($scope)
    );
  };

  if (window.elementorFrontend?.hooks) {
    hookHandler();
  } else {
    window.addEventListener("elementor/frontend/init", hookHandler);
  }
})();
