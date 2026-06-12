(() => {
    document.querySelectorAll("form").forEach((form) => {
        const childSelect = form.querySelector('select[name="child_id"]');
        const coParentSelect = form.querySelector('select[name="co_parent_id"]');
        if (!childSelect || !coParentSelect) {
            return;
        }

        function syncCoParentOptions() {
            coParentSelect.querySelectorAll("option").forEach((option) => {
                option.disabled = option.value !== "0" && option.value === childSelect.value;
            });

            if (coParentSelect.value === childSelect.value) {
                coParentSelect.value = "0";
            }
        }

        childSelect.addEventListener("change", syncCoParentOptions);
        syncCoParentOptions();
    });
})();
