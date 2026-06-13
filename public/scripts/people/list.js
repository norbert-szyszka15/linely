(() => {
    function normalize(value) {
        return String(value || "").trim().toLocaleLowerCase("pl-PL");
    }

    document.querySelectorAll("[data-people-list]").forEach((listRoot) => {
        const input = listRoot.querySelector("[data-people-search]");
        const count = listRoot.querySelector("[data-people-count]");
        const items = [...listRoot.querySelectorAll("[data-people-item]")];
        const empty = listRoot.querySelector("[data-people-empty]");
        const pagination = listRoot.querySelector("[data-people-pagination]");
        const pageSize = Number.parseInt(listRoot.dataset.pageSize || "40", 10);
        let currentPage = 1;
        let filtered = items;

        function pageCount() {
            return Math.max(1, Math.ceil(filtered.length / pageSize));
        }

        function updateCount() {
            if (!count) {
                return;
            }

            const total = items.length;
            count.textContent = filtered.length === total
                ? `${total} osób`
                : `${filtered.length} z ${total} osób`;
        }

        function renderPagination() {
            if (!pagination) {
                return;
            }

            pagination.replaceChildren();
            const totalPages = pageCount();
            if (totalPages <= 1) {
                return;
            }

            const previous = document.createElement("button");
            previous.type = "button";
            previous.className = "button secondary";
            previous.textContent = "Poprzednia";
            previous.disabled = currentPage === 1;
            previous.addEventListener("click", () => {
                currentPage = Math.max(1, currentPage - 1);
                render();
            });

            const label = document.createElement("span");
            label.textContent = `Strona ${currentPage} z ${totalPages}`;

            const next = document.createElement("button");
            next.type = "button";
            next.className = "button secondary";
            next.textContent = "Następna";
            next.disabled = currentPage === totalPages;
            next.addEventListener("click", () => {
                currentPage = Math.min(totalPages, currentPage + 1);
                render();
            });

            pagination.append(previous, label, next);
        }

        function render() {
            const start = (currentPage - 1) * pageSize;
            const visible = new Set(filtered.slice(start, start + pageSize));

            items.forEach((item) => {
                item.classList.toggle("hidden", !visible.has(item));
            });

            empty?.classList.toggle("hidden", filtered.length > 0);
            updateCount();
            renderPagination();
        }

        function applyFilter() {
            const query = normalize(input?.value);
            filtered = query
                ? items.filter((item) => normalize(item.dataset.personSearch).includes(query))
                : items;
            currentPage = 1;
            render();
        }

        listRoot.querySelectorAll("[data-delete-person-form]").forEach((form) => {
            form.addEventListener("submit", (event) => {
                const name = form.closest("[data-people-item]")?.querySelector("strong")?.textContent?.trim() || "tę osobę";
                if (!window.confirm(`Usunąć ${name} z drzewa?`)) {
                    event.preventDefault();
                }
            });
        });

        input?.addEventListener("input", applyFilter);
        render();
    });
})();
