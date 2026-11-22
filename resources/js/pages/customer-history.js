window.customerHistoryPage = function () {
    return {
        mobileMenuOpen: false,
        cartItems: JSON.parse(localStorage.getItem("cart_items")) || [],
        activeTab: "semua",
        showReviewModal: false,
        selectedProduct: "",
        rating: 5,
        hoverRating: 0,
        isSubmitting: false,

        syncCart() {
            this.cartItems =
                JSON.parse(localStorage.getItem("cart_items")) || [];
        },

        get cartCount() {
            return this.cartItems.reduce(
                (acc, item) => acc + Number(item.qty || 0),
                0,
            );
        },

        openReviewModal(productName) {
            this.selectedProduct = productName;
            this.rating = 5;
            this.hoverRating = 0;
            this.showReviewModal = true;
        },

        submitReview() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            setTimeout(() => {
                this.isSubmitting = false;
                this.showReviewModal = false;
                window.dispatchEvent(
                    new CustomEvent("show-toast", {
                        detail: "Ulasan berhasil dikirim! Terima kasih.",
                    }),
                );
            }, 800);
        },

        submitNewsletter(event) {
            event.target.reset();
            window.dispatchEvent(
                new CustomEvent("show-toast", {
                    detail: "Terima kasih telah berlangganan newsletter kami!",
                }),
            );
        },
    };
};

window.historyToast = function () {
    return {
        show: false,
        message: "",
        timeout: null,
        open(detail) {
            this.message =
                typeof detail === "string" ? detail : detail?.message || "";
            this.show = true;
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => {
                this.show = false;
            }, 4000);
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("customerHistoryPage", window.customerHistoryPage);
    window.Alpine.data("historyToast", window.historyToast);
});
