(() => {
    function closeOpenPopovers(except = null) {
        document.querySelectorAll("[data-popover].is-open").forEach((popover) => {
            if (popover !== except) {
                popover.classList.remove("is-open");
            }
        });
    }

    document.addEventListener("click", (event) => {
        const target = event.target.closest("[data-popover-target]");
        if (target) {
            event.preventDefault();
            const node = target.closest("[data-node]");
            const popover = node?.querySelector(`[data-popover="${CSS.escape(target.dataset.popoverTarget)}"]`);
            closeOpenPopovers(popover);
            popover?.classList.toggle("is-open");
            return;
        }

        if (event.target.closest("[data-popover-close]")) {
            event.target.closest("[data-popover]")?.classList.remove("is-open");
            return;
        }

        if (!event.target.closest("[data-popover]") && !event.target.closest("[data-popover-target]")) {
            closeOpenPopovers();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeOpenPopovers();
        }
    });
})();
