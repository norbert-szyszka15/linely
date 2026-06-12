(() => {
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
})();
