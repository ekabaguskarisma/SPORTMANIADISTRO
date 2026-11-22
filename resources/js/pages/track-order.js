window.copyTrackingCode = function (code, btnEl) {
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        if (!btnEl) return;
        const originalText = btnEl.textContent;
        btnEl.textContent = "✓ Tersalin";
        btnEl.classList.add("bg-emerald-500/20", "text-emerald-300");
        setTimeout(() => {
            btnEl.textContent = originalText;
            btnEl.classList.remove("bg-emerald-500/20", "text-emerald-300");
        }, 2000);
    });
};
