(() => {
    function openModal(id) {
        const modal = document.querySelector(`[data-modal="${CSS.escape(id)}"]`);
        if (!modal) {
            return;
        }

        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        const firstInput = modal.querySelector("input:not([type='hidden']), select, textarea, button");
        setTimeout(() => firstInput?.focus(), 80);
    }

    function closeModal(modal) {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
    }

    document.addEventListener("click", (event) => {
        const modalTarget = event.target.closest("[data-modal-target]");
        if (modalTarget) {
            event.preventDefault();
            openModal(modalTarget.dataset.modalTarget);
        }

        const modalClose = event.target.closest("[data-modal-close]");
        if (modalClose) {
            const modal = modalClose.closest("[data-modal]");
            if (modal) {
                closeModal(modal);
            }
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        document.querySelectorAll("[data-modal].is-open").forEach(closeModal);
    });
})();
