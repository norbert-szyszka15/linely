(() => {
    function normalize(value) {
        return String(value || "").trim().toLocaleLowerCase("pl-PL");
    }

    function personMeta(node) {
        return {
            id: node.dataset.personId,
            name: node.dataset.personName || "",
            search: normalize(node.dataset.personSearch || node.textContent),
            node,
        };
    }

    document.querySelectorAll("[data-tree-root]").forEach((treeRoot) => {
        const searchRoot = document.querySelector("[data-tree-search]");
        const input = searchRoot?.querySelector("[data-tree-search-input]");
        const results = searchRoot?.querySelector("[data-tree-search-results]");
        const viewport = treeRoot.querySelector("[data-tree-viewport]");
        const transform = treeRoot.querySelector("[data-tree-transform]");
        if (!input || !results || !viewport || !transform) {
            return;
        }

        const people = [...treeRoot.querySelectorAll("[data-node][data-person-id]")].map(personMeta);
        let highlightTimeout = 0;

        function currentZoom() {
            const value = Number.parseFloat(getComputedStyle(transform).getPropertyValue("--tree-zoom"));
            return Number.isFinite(value) && value > 0 ? value : 1;
        }

        function closeResults() {
            results.hidden = true;
            results.replaceChildren();
        }

        function focusPerson(person) {
            const zoom = currentZoom();
            const left = Number.parseFloat(person.node.style.left) || 0;
            const top = Number.parseFloat(person.node.style.top) || 0;

            viewport.scrollTo({
                left: Math.max(0, (left + person.node.offsetWidth / 2) * zoom - viewport.clientWidth / 2),
                top: Math.max(0, (top + person.node.offsetHeight / 2) * zoom - viewport.clientHeight / 2),
                behavior: "smooth",
            });

            window.clearTimeout(highlightTimeout);
            treeRoot.querySelectorAll(".person-node.is-search-highlighted").forEach((node) => {
                node.classList.remove("is-search-highlighted");
            });
            person.node.classList.add("is-search-highlighted");
            highlightTimeout = window.setTimeout(() => {
                person.node.classList.remove("is-search-highlighted");
            }, 3000);
            closeResults();
            input.value = person.name;
        }

        function renderResults() {
            const query = normalize(input.value);
            if (!query) {
                closeResults();
                return;
            }

            const matches = people
                .filter((person) => person.search.includes(query))
                .slice(0, 12);

            results.replaceChildren();
            results.hidden = false;

            if (!matches.length) {
                const empty = document.createElement("div");
                empty.className = "tree-search-empty";
                empty.textContent = "Brak wyników";
                results.append(empty);
                return;
            }

            matches.forEach((person) => {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = person.name;
                button.addEventListener("click", () => focusPerson(person));
                results.append(button);
            });
        }

        input.addEventListener("input", renderResults);
        input.addEventListener("focus", renderResults);
        input.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeResults();
                input.blur();
            }
        });

        document.addEventListener("click", (event) => {
            if (!searchRoot.contains(event.target)) {
                closeResults();
            }
        });
    });
})();
