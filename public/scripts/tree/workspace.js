(() => {
    document.querySelectorAll("[data-tree-root]").forEach((treeRoot) => {
        const viewport = treeRoot.querySelector("[data-tree-viewport]");
        const stage = treeRoot.querySelector("[data-stage]");
        const transform = treeRoot.querySelector("[data-tree-transform]");
        const lines = treeRoot.querySelector(".tree-lines");
        const zoomLabel = treeRoot.querySelector("[data-zoom-label]");
        const baseWidth = Number(stage.dataset.baseWidth);
        const baseHeight = Number(stage.dataset.baseHeight);
        let zoom = 1;
        const gridSize = 42;
        transform.style.width = `${baseWidth}px`;
        transform.style.height = `${baseHeight}px`;
        if (lines) {
            lines.style.width = `${baseWidth}px`;
            lines.style.height = `${baseHeight}px`;
        }

        function snap(value) {
            return Math.round(value / gridSize) * gridSize;
        }

        function resizeStage() {
            const stageWidth = Math.max(baseWidth * zoom, viewport.clientWidth + 720);
            const stageHeight = Math.max(baseHeight * zoom, viewport.clientHeight + 520);
            stage.style.width = `${stageWidth}px`;
            stage.style.height = `${stageHeight}px`;
            transform.style.width = `${stageWidth / zoom}px`;
            transform.style.height = `${stageHeight / zoom}px`;
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

        function setBadgePosition(edge, x, y) {
            const badge = edge.querySelector("[data-edge-badge]");
            badge?.setAttribute("transform", `translate(${x} ${y})`);
        }

        function idsFromDataset(value) {
            return String(value || "")
                .split(",")
                .map((id) => id.trim())
                .filter(Boolean);
        }

        function clamp(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function childEdgeParents(edge) {
            return idsFromDataset(edge.dataset.parents);
        }

        function childEdgeChildren(edge) {
            return idsFromDataset(edge.dataset.children);
        }

        function childEdgePeerCenter(edge, parentId) {
            const otherParents = childEdgeParents(edge)
                .filter((id) => id !== parentId)
                .map(nodeBox)
                .filter(Boolean);

            if (otherParents.length) {
                return otherParents.reduce((sum, parent) => sum + parent.x + parent.width / 2, 0) / otherParents.length;
            }

            const children = childEdgeChildren(edge).map(nodeBox).filter(Boolean);
            if (children.length) {
                return children.reduce((sum, child) => sum + child.x + child.width / 2, 0) / children.length;
            }

            const parent = nodeBox(parentId);
            return parent ? parent.x + parent.width / 2 : 0;
        }

        function distributedParentAnchors() {
            const edgesByParent = new Map();
            const anchors = new Map();

            treeRoot.querySelectorAll(".child-edge[data-line='child']").forEach((edge) => {
                childEdgeParents(edge).forEach((parentId) => {
                    if (!edgesByParent.has(parentId)) {
                        edgesByParent.set(parentId, []);
                    }
                    edgesByParent.get(parentId).push(edge);
                });
            });

            edgesByParent.forEach((edges, parentId) => {
                const uniqueEdges = [...new Set(edges)];
                if (uniqueEdges.length < 2) {
                    return;
                }

                const parent = nodeBox(parentId);
                if (!parent) {
                    return;
                }

                uniqueEdges
                    .sort((a, b) => childEdgePeerCenter(a, parentId) - childEdgePeerCenter(b, parentId))
                    .forEach((edge, index) => {
                        if (!anchors.has(edge)) {
                            anchors.set(edge, new Map());
                        }

                        anchors.get(edge).set(
                            parentId,
                            parent.x + (parent.width * 0.1) + ((parent.width * 0.8 * (index + 1)) / (uniqueEdges.length + 1))
                        );
                    });
            });

            return anchors;
        }

        function updateLines() {
            const parentAnchors = distributedParentAnchors();

            treeRoot.querySelectorAll(".tree-edge[data-line]").forEach((edge) => {
                const path = edge.querySelector("path.line");
                if (!path) {
                    return;
                }

                if (edge.dataset.line === "partner") {
                    const from = nodeBox(edge.dataset.from);
                    const to = nodeBox(edge.dataset.to);
                    if (!from || !to) {
                        return;
                    }

                    const fromIsLeft = from.x <= to.x;
                    const x1 = fromIsLeft ? from.x + from.width : from.x;
                    const y1 = from.y + 64;
                    const x2 = fromIsLeft ? to.x : to.x + to.width;
                    const y2 = to.y + 64;
                    const midX = (x1 + x2) / 2;
                    const d = Math.abs(y1 - y2) < 2
                        ? `M${x1} ${y1} L${x2} ${y2}`
                        : `M${x1} ${y1} L${midX} ${y1} L${midX} ${y2} L${x2} ${y2}`;
                    path.setAttribute("d", d);
                    setBadgePosition(edge, midX, (y1 + y2) / 2);
                    return;
                }

                const parents = childEdgeParents(edge)
                    .map((id) => ({ id, box: nodeBox(id) }))
                    .filter((parent) => parent.box);
                const children = childEdgeChildren(edge).map(nodeBox).filter(Boolean);
                if (!parents.length || !children.length) {
                    return;
                }

                const parentPoints = parents.map((parent) => ({
                    x: parentAnchors.get(edge)?.get(parent.id) ?? parent.box.x + parent.box.width / 2,
                    y: parent.box.y + parent.box.height,
                })).sort((a, b) => a.x - b.x);
                const childPoints = children.map((child) => ({
                    x: child.x + child.width / 2,
                    y: child.y,
                })).sort((a, b) => a.x - b.x);

                const parentCenterX = parentPoints.reduce((sum, point) => sum + point.x, 0) / parentPoints.length;
                const parentBottomY = Math.max(...parentPoints.map((point) => point.y));
                const childTopY = Math.min(...childPoints.map((point) => point.y));
                const verticalSpace = childTopY - parentBottomY;
                const junctionY = parentBottomY + clamp(verticalSpace * 0.32, 34, 76);
                let branchY = Math.max(junctionY + 28, childTopY - 36);
                branchY = Math.min(branchY, childTopY - 18);
                if (branchY <= junctionY) {
                    branchY = (junctionY + childTopY) / 2;
                }
                const childMinX = Math.min(...childPoints.map((point) => point.x));
                const childMaxX = Math.max(...childPoints.map((point) => point.x));
                const branchMinX = Math.min(parentCenterX, childMinX);
                const branchMaxX = Math.max(parentCenterX, childMaxX);
                const segments = [];

                parentPoints.forEach((point) => {
                    segments.push(`M${point.x} ${point.y} L${point.x} ${junctionY}`);
                });

                if (parentPoints.length > 1) {
                    segments.push(`M${parentPoints[0].x} ${junctionY} L${parentPoints[parentPoints.length - 1].x} ${junctionY}`);
                }

                segments.push(`M${parentCenterX} ${junctionY} L${parentCenterX} ${branchY}`);
                segments.push(`M${branchMinX} ${branchY} L${branchMaxX} ${branchY}`);
                childPoints.forEach((point) => {
                    segments.push(`M${point.x} ${branchY} L${point.x} ${point.y}`);
                });

                path.setAttribute("d", segments.join(" "));
                setBadgePosition(edge, parentCenterX, (junctionY + branchY) / 2);
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

        treeRoot.querySelector("[data-zoom-in]")?.addEventListener("click", () => setZoom(zoom + 0.08));
        treeRoot.querySelector("[data-zoom-out]")?.addEventListener("click", () => setZoom(zoom - 0.08));
        treeRoot.querySelector("[data-zoom-reset]")?.addEventListener("click", () => setZoom(1));

        viewport.addEventListener("wheel", (event) => {
            if (!event.ctrlKey && !event.metaKey) {
                return;
            }

            event.preventDefault();
            const zoomDelta = Math.max(-0.035, Math.min(0.035, -event.deltaY * 0.001));
            setZoom(zoom + zoomDelta);
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
                keepalive: true,
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
            viewport.scrollTop = Math.max(0, (stage.offsetHeight - viewport.clientHeight) / 2);
        });
    });
})();
