const root = document.documentElement;
const savedTheme = localStorage.getItem("linely-theme");

if (savedTheme) {
    root.dataset.theme = savedTheme;
} else if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
    root.dataset.theme = "dark";
}

document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
        const nextTheme = root.dataset.theme === "dark" ? "light" : "dark";
        root.dataset.theme = nextTheme;
        localStorage.setItem("linely-theme", nextTheme);
    });
});

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
    document.querySelectorAll("[data-popover].is-open").forEach((popover) => popover.classList.remove("is-open"));
});

document.addEventListener("click", (event) => {
    const target = event.target.closest("[data-popover-target]");
    if (target) {
        event.preventDefault();
        const node = target.closest("[data-node]");
        const popover = node?.querySelector(`[data-popover="${CSS.escape(target.dataset.popoverTarget)}"]`);
        document.querySelectorAll("[data-popover].is-open").forEach((openPopover) => {
            if (openPopover !== popover) {
                openPopover.classList.remove("is-open");
            }
        });
        popover?.classList.toggle("is-open");
        return;
    }

    if (event.target.closest("[data-popover-close]")) {
        event.target.closest("[data-popover]")?.classList.remove("is-open");
        return;
    }

    if (!event.target.closest("[data-popover]") && !event.target.closest("[data-popover-target]")) {
        document.querySelectorAll("[data-popover].is-open").forEach((popover) => popover.classList.remove("is-open"));
    }
});

document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-tab-target]");
    if (!button) {
        return;
    }

    const popover = button.closest("[data-popover]");
    const target = button.dataset.tabTarget;
    popover.querySelectorAll("[data-tab-target]").forEach((item) => item.classList.toggle("active", item === button));
    popover.querySelectorAll("[data-tab-panel]").forEach((panel) => {
        panel.classList.toggle("hidden", panel.dataset.tabPanel !== target);
    });
});

document.querySelectorAll("[data-tree-root]").forEach((treeRoot) => {
    const viewport = treeRoot.querySelector("[data-tree-viewport]");
    const stage = treeRoot.querySelector("[data-stage]");
    const transform = treeRoot.querySelector("[data-tree-transform]");
    const zoomLabel = treeRoot.querySelector("[data-zoom-label]");
    const baseWidth = Number(stage.dataset.baseWidth);
    const baseHeight = Number(stage.dataset.baseHeight);
    let zoom = 1;
    const gridSize = 42;
    transform.style.width = `${baseWidth}px`;
    transform.style.height = `${baseHeight}px`;

    function snap(value) {
        return Math.round(value / gridSize) * gridSize;
    }

    function resizeStage() {
        stage.style.width = `${Math.max(baseWidth * zoom, viewport.clientWidth + 720)}px`;
        stage.style.height = `${Math.max(baseHeight * zoom, viewport.clientHeight + 520)}px`;
    }

    function nodeBox(id) {
        const node = treeRoot.querySelector(`[data-person-id="${CSS.escape(String(id))}"]`);
        if (!node) {
            return null;
        }

        return {
            x: Number.parseFloat(node.style.left) || 0,
            y: Number.parseFloat(node.style.top) || 0,
            width: node.offsetWidth,
            height: node.offsetHeight,
        };
    }

    function updateLines() {
        treeRoot.querySelectorAll("[data-line]").forEach((line) => {
            const from = nodeBox(line.dataset.from);
            const to = nodeBox(line.dataset.to);
            if (!from || !to) {
                return;
            }

            if (line.dataset.line === "partner") {
                const fromIsLeft = from.x <= to.x;
                line.setAttribute("x1", fromIsLeft ? from.x + from.width : from.x);
                line.setAttribute("y1", from.y + 64);
                line.setAttribute("x2", fromIsLeft ? to.x : to.x + to.width);
                line.setAttribute("y2", to.y + 64);
                return;
            }

            const x1 = from.x + from.width / 2;
            const y1 = from.y + from.height;
            const x2 = to.x + to.width / 2;
            const y2 = to.y;
            const mid = (y1 + y2) / 2;
            line.setAttribute("d", `M${x1} ${y1} C${x1} ${mid}, ${x2} ${mid}, ${x2} ${y2}`);
        });
    }

    let lineUpdateFrame = 0;
    function scheduleLineUpdate() {
        if (lineUpdateFrame) {
            return;
        }

        lineUpdateFrame = requestAnimationFrame(() => {
            lineUpdateFrame = 0;
            updateLines();
        });
    }

    function pointerToTree(event) {
        const rect = transform.getBoundingClientRect();

        return {
            x: (event.clientX - rect.left) / zoom,
            y: (event.clientY - rect.top) / zoom,
        };
    }

    function setZoom(nextZoom) {
        const previousZoom = zoom;
        zoom = Math.min(1.7, Math.max(0.45, nextZoom));
        transform.style.setProperty("--tree-zoom", zoom);
        resizeStage();
        zoomLabel.textContent = `${Math.round(zoom * 100)}%`;

        const factor = zoom / previousZoom;
        viewport.scrollLeft = (viewport.scrollLeft + viewport.clientWidth / 2) * factor - viewport.clientWidth / 2;
        viewport.scrollTop = (viewport.scrollTop + viewport.clientHeight / 2) * factor - viewport.clientHeight / 2;
    }

    treeRoot.querySelector("[data-zoom-in]")?.addEventListener("click", () => setZoom(zoom + 0.12));
    treeRoot.querySelector("[data-zoom-out]")?.addEventListener("click", () => setZoom(zoom - 0.12));
    treeRoot.querySelector("[data-zoom-reset]")?.addEventListener("click", () => setZoom(1));

    viewport.addEventListener("wheel", (event) => {
        if (!event.ctrlKey && !event.metaKey) {
            return;
        }

        event.preventDefault();
        setZoom(zoom + (event.deltaY < 0 ? 0.08 : -0.08));
    }, { passive: false });

    let pan = null;
    viewport.addEventListener("pointerdown", (event) => {
        if (event.button !== 1) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        pan = {
            x: event.clientX,
            y: event.clientY,
            left: viewport.scrollLeft,
            top: viewport.scrollTop,
        };
        viewport.classList.add("is-panning");
        viewport.setPointerCapture(event.pointerId);
    });

    viewport.addEventListener("pointermove", (event) => {
        if (!pan) {
            return;
        }

        event.preventDefault();
        viewport.scrollLeft = pan.left - (event.clientX - pan.x);
        viewport.scrollTop = pan.top - (event.clientY - pan.y);
    });

    function stopPanning(event) {
        if (!pan) {
            return;
        }

        pan = null;
        viewport.classList.remove("is-panning");
        viewport.releasePointerCapture(event.pointerId);
    }

    viewport.addEventListener("pointerup", stopPanning);
    viewport.addEventListener("pointercancel", stopPanning);

    viewport.addEventListener("auxclick", (event) => {
        if (event.button === 1) {
            event.preventDefault();
        }
    });

    function savePosition(node) {
        const data = new URLSearchParams();
        data.set("action", "update_position");
        data.set("csrf", treeRoot.dataset.csrf);
        data.set("tree_id", treeRoot.dataset.treeId);
        data.set("person_id", node.dataset.personId);
        data.set("x_position", String(Math.round(Number.parseFloat(node.style.left) || 0)));
        data.set("y_position", String(Math.round(Number.parseFloat(node.style.top) || 0)));

        fetch("/", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: data.toString(),
        }).catch(() => {});
    }

    treeRoot.querySelectorAll("[data-node][data-draggable='true']").forEach((node) => {
        const handle = node.querySelector("[data-drag-handle]");
        let drag = null;

        handle?.setAttribute("draggable", "false");
        handle?.addEventListener("dragstart", (event) => event.preventDefault());

        handle?.addEventListener("pointerdown", (event) => {
            if (event.button !== 0) {
                return;
            }

            if (event.target.closest("a, button, input, select, textarea")) {
                return;
            }

            event.stopPropagation();

            const left = Number.parseFloat(node.style.left) || 0;
            const top = Number.parseFloat(node.style.top) || 0;
            const pointer = pointerToTree(event);
            drag = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                offsetX: pointer.x - left,
                offsetY: pointer.y - top,
                active: false,
                moved: false,
            };
            node.setPointerCapture?.(event.pointerId);
        });

        node.addEventListener("pointermove", (event) => {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }

            const pointer = pointerToTree(event);
            const rawLeft = Math.max(0, pointer.x - drag.offsetX);
            const rawTop = Math.max(0, pointer.y - drag.offsetY);
            drag.moved = drag.moved || Math.abs(event.clientX - drag.startX) > 4 || Math.abs(event.clientY - drag.startY) > 4;
            if (!drag.moved) {
                return;
            }

            event.preventDefault();

            if (!drag.active) {
                drag.active = true;
                node.classList.add("is-dragging");
            }

            const nextLeft = snap(rawLeft);
            const nextTop = snap(rawTop);
            if (node.style.left === `${nextLeft}px` && node.style.top === `${nextTop}px`) {
                return;
            }

            node.style.left = `${nextLeft}px`;
            node.style.top = `${nextTop}px`;
            scheduleLineUpdate();
        });

        function stopDragging(event) {
            if (!drag || event.pointerId !== drag.pointerId) {
                return;
            }

            const didMove = drag.moved;
            drag = null;
            node.classList.remove("is-dragging");
            if (node.hasPointerCapture?.(event.pointerId)) {
                node.releasePointerCapture(event.pointerId);
            }
            node.style.left = `${snap(Number.parseFloat(node.style.left) || 0)}px`;
            node.style.top = `${snap(Number.parseFloat(node.style.top) || 0)}px`;
            updateLines();
            if (didMove) {
                savePosition(node);
            }
        }

        node.addEventListener("pointerup", stopDragging);
        node.addEventListener("pointercancel", stopDragging);
    });

    new ResizeObserver(() => {
        resizeStage();
        updateLines();
    }).observe(viewport);

    requestAnimationFrame(() => {
        resizeStage();
        updateLines();
        viewport.scrollLeft = Math.max(0, (stage.offsetWidth - viewport.clientWidth) / 2);
    });
});
