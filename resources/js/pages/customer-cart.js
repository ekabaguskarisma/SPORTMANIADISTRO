window.customerCart = function () {
    return {
        mobileMenuOpen: false,
        cartItems: (JSON.parse(localStorage.getItem("cart_items")) || []).map(
            (item) => ({
                ...item,
                size: item.size || "All Size",
                color: item.color || "-",
                qty: Math.max(1, Number(item.qty || 1)),
            }),
        ),

        syncCart() {
            const raw = JSON.parse(localStorage.getItem("cart_items")) || [];
            this.cartItems = raw.map((item) => ({
                ...item,
                size: item.size || "All Size",
                color: item.color || "-",
                qty: Math.max(1, Number(item.qty || 1)),
            }));
        },

        get totalItems() {
            return this.cartItems.reduce(
                (acc, item) => acc + Number(item.qty || 0),
                0,
            );
        },

        get subtotal() {
            return this.cartItems.reduce(
                (acc, item) =>
                    acc + Number(item.price || 0) * Number(item.qty || 0),
                0,
            );
        },

        get shippingCost() {
            return this.subtotal >= 250000 || this.subtotal === 0 ? 0 : 20000;
        },

        get total() {
            return this.subtotal + this.shippingCost;
        },

        getItemMaxStock(item) {
            if (
                item.maxStock !== undefined &&
                item.maxStock !== null &&
                item.maxStock !== ""
            ) {
                return Number(item.maxStock);
            }
            if (
                item.stock !== undefined &&
                item.stock !== null &&
                item.stock !== ""
            ) {
                return Number(item.stock);
            }
            return null;
        },

        updateStorage() {
            localStorage.setItem("cart_items", JSON.stringify(this.cartItems));
            window.dispatchEvent(
                new CustomEvent("cart-updated", { detail: this.cartItems }),
            );
        },

        triggerNotify(type, title, message) {
            window.dispatchEvent(
                new CustomEvent("notify", { detail: { type, title, message } }),
            );
        },

        removeItem(index) {
            const itemName = this.cartItems[index].name;
            this.cartItems.splice(index, 1);
            this.updateStorage();
            this.triggerNotify(
                "warning",
                "Dihapus",
                itemName + " dihapus dari keranjang.",
            );
        },

        clearCart() {
            if (confirm("Kosongkan seluruh isi keranjang belanja?")) {
                this.cartItems = [];
                this.updateStorage();
                this.triggerNotify(
                    "warning",
                    "Keranjang Dikosongkan",
                    "Semua produk telah dihapus dari keranjang.",
                );
            }
        },

        increaseQty(index) {
            const item = this.cartItems[index];
            const currentQty = Number(item.qty);
            const maxLimit = this.getItemMaxStock(item);

            if (
                maxLimit !== null &&
                (maxLimit <= 0 || currentQty >= maxLimit)
            ) {
                this.triggerNotify(
                    "error",
                    "Batas Stok",
                    maxLimit <= 0
                        ? "Stok produk ini sedang habis."
                        : `Stok maksimal tersedia hanya ${maxLimit} pcs.`,
                );
                return;
            }
            item.qty = currentQty + 1;
            this.updateStorage();
        },

        decreaseQty(index) {
            if (Number(this.cartItems[index].qty) > 1) {
                this.cartItems[index].qty =
                    Number(this.cartItems[index].qty) - 1;
                this.updateStorage();
            }
        },
    };
};

window.cartToast = function () {
    return {
        show: false,
        type: "success",
        title: "Berhasil!",
        message: "",
        timeout: null,
        barKey: 0,
        openToast(detail) {
            if (typeof detail === "string") {
                this.type = "success";
                this.title = "Notifikasi";
                this.message = detail;
            } else {
                this.type = detail.type || "success";
                this.title = detail.title || "Notifikasi";
                this.message = detail.message || "";
            }
            this.barKey++;
            this.show = true;
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => {
                this.show = false;
            }, 3000);
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("customerCart", window.customerCart);
    window.Alpine.data("cartToast", window.cartToast);
});
