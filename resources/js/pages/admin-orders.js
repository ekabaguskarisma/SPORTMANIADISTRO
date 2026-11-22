window.adminOrdersPage = function () {
    return {
        statusFilter: "semua",
        searchQuery: "",

        matchesRow(status, searchBlob) {
            const matchStatus =
                this.statusFilter === "semua" || this.statusFilter === status;
            const q = this.searchQuery.toLowerCase().trim();
            const matchSearch = !q || (searchBlob || "").includes(q);
            return matchStatus && matchSearch;
        },

        handleStatusChange(selectEl, orderId) {
            if (
                selectEl.value === "cancelled" &&
                !confirm(`Yakin ingin membatalkan pesanan #ORD-${orderId}?`)
            ) {
                selectEl.value = selectEl.dataset.current;
                return;
            }
            selectEl.disabled = true;
            selectEl.form.submit();
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("adminOrdersPage", window.adminOrdersPage);
});
