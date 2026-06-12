(() => {
    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-tab-target]");
        if (!button) {
            return;
        }

        const container = button.closest("[data-popover]") || document;
        const target = button.dataset.tabTarget;
        container.querySelectorAll("[data-tab-target]").forEach((item) => item.classList.toggle("active", item === button));
        container.querySelectorAll("[data-tab-panel]").forEach((panel) => {
            panel.classList.toggle("hidden", panel.dataset.tabPanel !== target);
        });
    });
})();
