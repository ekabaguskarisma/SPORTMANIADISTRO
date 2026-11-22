window.productDetail = function (config = {}) {
    return {
        product: config.product || {
            id: null,
            name: "",
            category: "Sport Apparel",
            image: "",
            price: 0,
        },
        variants: Array.isArray(config.variants) ? config.variants : [],
        hasColors: Boolean(config.hasColors),
        hasSizes: Boolean(config.hasSizes),
        selectedColor: config.selectedColor || "",
        selectedSize: config.selectedSize || "",
        fallbackStock: Number(config.fallbackStock || 0),
        qty: 1,
        sizeGuideOpen: false,
        toast: { show: false, message: "", type: "success" },

        triggerToast(message, type = "success") {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            window.dispatchEvent(
                new CustomEvent("show-toast", { detail: message }),
            );
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 3500);
        },

        get isSelectionComplete() {
            return (
                (!this.hasColors || this.selectedColor !== "") &&
                (!this.hasSizes || this.selectedSize !== "")
            );
        },

        get matchedVariant() {
            if (this.variants.length === 0) return null;
            return (
                this.variants.find((v) => {
                    const colorMatch =
                        !this.hasColors || v.color === this.selectedColor;
                    const sizeMatch =
                        !this.hasSizes || v.size === this.selectedSize;
                    return colorMatch && sizeMatch;
                }) || null
            );
        },

        get currentStock() {
            if (!this.isSelectionComplete) return this.fallbackStock;
            if (this.variants.length > 0) {
                return this.matchedVariant
                    ? Number(this.matchedVariant.stock)
                    : 0;
            }
            return this.fallbackStock;
        },

        isColorAvailable(color) {
            if (this.variants.length === 0) return this.fallbackStock > 0;
            return this.variants.some(
                (v) => v.color === color && Number(v.stock) > 0,
            );
        },

        isSizeAvailable(size) {
            if (this.variants.length === 0) return this.fallbackStock > 0;
            return this.variants.some((v) => {
                const colorOk =
                    !this.hasColors ||
                    !this.selectedColor ||
                    v.color === this.selectedColor;
                return v.size === size && colorOk && Number(v.stock) > 0;
            });
        },

        selectColor(color) {
            this.selectedColor = color;
            if (this.isSelectionComplete && this.qty > this.currentStock) {
                this.qty = Math.max(1, this.currentStock);
            }
        },

        selectSize(size) {
            this.selectedSize = size;
            if (this.isSelectionComplete && this.qty > this.currentStock) {
                this.qty = Math.max(1, this.currentStock);
            }
        },

        incrementQty() {
            if (this.qty < Math.max(1, this.currentStock)) {
                this.qty++;
            } else {
                this.triggerToast(
                    "Jumlah mencapai batas stok tersedia.",
                    "error",
                );
            }
        },

        decrementQty() {
            if (this.qty > 1) {
                this.qty--;
            }
        },

        get whatsappUrl() {
            const details = [];
            if (this.selectedColor)
                details.push("Warna: " + this.selectedColor);
            if (this.selectedSize) details.push("Ukuran: " + this.selectedSize);
            const variantText = details.length
                ? " (" + details.join(", ") + ")"
                : "";
            const msg = `Halo Admin Sport Mania Distro, saya ingin memesan *${this.product.name}*${variantText} sebanyak ${this.qty} pcs (Harga satuan: Rp ${Number(this.product.price).toLocaleString("id-ID")}). Mohon info ketersediaannya.`;
            return (
                "https://wa.me/6282234548092?text=" + encodeURIComponent(msg)
            );
        },

        addToCart() {
            if (this.hasColors && !this.selectedColor) {
                this.triggerToast(
                    "Silakan pilih variasi warna terlebih dahulu.",
                    "error",
                );
                return;
            }
            if (this.hasSizes && !this.selectedSize) {
                this.triggerToast(
                    "Silakan pilih ukuran (size) terlebih dahulu.",
                    "error",
                );
                return;
            }
            if (this.currentStock <= 0) {
                this.triggerToast(
                    "Maaf, stok varian yang dipilih sedang habis.",
                    "error",
                );
                return;
            }

            const sizeVal = this.matchedVariant
                ? this.matchedVariant.size
                : this.selectedSize || "All Size";
            const colorVal = this.matchedVariant
                ? this.matchedVariant.color
                : this.selectedColor || "-";

            const cartItems =
                JSON.parse(localStorage.getItem("cart_items")) || [];
            const existingItem = cartItems.find(
                (i) =>
                    i.id === this.product.id &&
                    i.size === sizeVal &&
                    i.color === colorVal,
            );

            const requestedQty = parseInt(this.qty, 10) || 1;
            const currentInCart = existingItem ? Number(existingItem.qty) : 0;

            if (currentInCart + requestedQty > this.currentStock) {
                this.triggerToast(
                    `Stok tidak mencukupi. Maksimal tersedia: ${this.currentStock} pcs.`,
                    "error",
                );
                return;
            }

            if (existingItem) {
                existingItem.qty += requestedQty;
            } else {
                cartItems.push({
                    id: this.product.id,
                    name: this.product.name,
                    category: this.product.category,
                    image: this.product.image,
                    size: sizeVal,
                    color: colorVal,
                    price: Number(this.product.price),
                    qty: requestedQty,
                });
            }

            localStorage.setItem("cart_items", JSON.stringify(cartItems));
            window.dispatchEvent(
                new CustomEvent("cart-updated", { detail: cartItems }),
            );
            this.triggerToast(
                "Produk berhasil ditambahkan ke keranjang!",
                "success",
            );
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("productDetail", window.productDetail);
});
