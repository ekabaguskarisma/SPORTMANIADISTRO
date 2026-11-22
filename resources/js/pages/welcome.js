document.addEventListener("alpine:init", () => {
    Alpine.data("storefrontApp", (config) => ({
        mobileMenuOpen: false,
        openKategori: false,
        activeFilter: config.initialFilter || "semua",
        sortBy: "terbaru",
        searchQuery: config.initialSearch || "",
        selectedProduct: null,
        selectedVariantIndex: 0,
        qty: 1,
        productsMeta: config.productsMeta || [],
        toastTimer: null,

        toast: {
            show: false,
            message: "",
            type: "success",
        },

        cartItems: [],

        init() {
            this.syncCart();
        },

        showToast(message, type = "success") {
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            this.toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 3500);
        },

        syncCart() {
            try {
                const raw = JSON.parse(localStorage.getItem("cart_items"));
                this.cartItems = Array.isArray(raw) ? raw : [];
            } catch (e) {
                this.cartItems = [];
            }
        },

        get cartCount() {
            return this.cartItems.reduce(
                (acc, item) => acc + Math.max(0, Number(item.qty || 0)),
                0,
            );
        },

        get sortedOrderMap() {
            const list = [...this.productsMeta];
            if (this.sortBy === "terlaris") {
                list.sort(
                    (a, b) =>
                        b.sold - a.sold ||
                        b.rating - a.rating ||
                        a.index - b.index,
                );
            } else if (this.sortBy === "rating") {
                list.sort(
                    (a, b) =>
                        b.rating - a.rating ||
                        b.reviews - a.reviews ||
                        b.sold - a.sold ||
                        a.index - b.index,
                );
            } else if (this.sortBy === "harga_asc") {
                list.sort((a, b) => a.price - b.price || a.index - b.index);
            } else if (this.sortBy === "harga_desc") {
                list.sort((a, b) => b.price - a.price || a.index - b.index);
            } else {
                list.sort((a, b) => a.index - b.index);
            }
            const map = {};
            list.forEach((item, pos) => {
                map[item.id] = pos;
            });
            return map;
        },

        getProductOrder(productId) {
            return this.sortedOrderMap[productId] ?? 0;
        },

        matchesCategoryFilter(catSlug, prodText, isPromo) {
            if (this.activeFilter === "semua") return true;
            if (this.activeFilter === "promo") return Boolean(isPromo);
            if (catSlug === this.activeFilter) return true;
            if (
                ["pakaian", "sepatu", "tas", "aksesoris"].includes(
                    this.activeFilter,
                ) &&
                catSlug.includes(this.activeFilter)
            ) {
                return true;
            }
            return this.matchesEditorialFilter(catSlug, prodText);
        },

        get visibleProductsCount() {
            const q = this.searchQuery.toLowerCase().trim();
            return this.productsMeta.filter((p) => {
                const matchCat = this.matchesCategoryFilter(
                    p.cat,
                    p.search,
                    p.promo,
                );
                const matchSearch = !q || p.search.includes(q);
                return matchCat && matchSearch;
            }).length;
        },

        openQuickView(product) {
            this.selectedProduct = product;
            this.qty = 1;
            if (product.variants && product.variants.length > 0) {
                const firstAvailable = product.variants.findIndex(
                    (v) => Number(v.stock) > 0,
                );
                this.selectedVariantIndex =
                    firstAvailable !== -1 ? firstAvailable : 0;
            } else {
                this.selectedVariantIndex = 0;
            }
        },

        selectVariant(idx) {
            this.selectedVariantIndex = idx;
            const max = this.maxAvailableStock;
            if (max <= 0) {
                this.qty = 1;
            } else if (this.qty > max) {
                this.qty = max;
            }
        },

        get activeVariant() {
            if (
                !this.selectedProduct ||
                !this.selectedProduct.variants ||
                this.selectedProduct.variants.length === 0
            ) {
                return null;
            }
            return (
                this.selectedProduct.variants[this.selectedVariantIndex] || null
            );
        },

        get maxAvailableStock() {
            if (!this.selectedProduct) return 0;
            if (this.activeVariant)
                return Math.max(0, Number(this.activeVariant.stock ?? 0));
            return Math.max(0, Number(this.selectedProduct.stock ?? 0));
        },

        matchesEditorialFilter(catSlug, prodText) {
            if (this.activeFilter === "women") {
                return (
                    catSlug === "women" ||
                    /\b(wanita|women|womens|legging|sports bra|hijab)\b/.test(
                        prodText,
                    )
                );
            }
            if (this.activeFilter === "men") {
                return (
                    catSlug === "men" ||
                    /\b(pria|men|mens|jersey|futsal|bola)\b/.test(prodText)
                );
            }
            if (this.activeFilter === "sports") {
                return (
                    catSlug === "sports" ||
                    /\b(lari|running|gym|training|sepatu|futsal|basket)\b/.test(
                        prodText,
                    )
                );
            }
            if (this.activeFilter === "lifestyle") {
                return (
                    catSlug === "lifestyle" ||
                    /\b(hoodie|tas|ransel|casual|lifestyle|jaket)\b/.test(
                        prodText,
                    )
                );
            }
            return false;
        },

        scrollToCatalog() {
            const el = document.getElementById("katalog");
            if (el) el.scrollIntoView({ behavior: "smooth" });
        },

        resetFilters() {
            this.activeFilter = "semua";
            this.searchQuery = "";
        },

        addToCart() {
            if (!this.selectedProduct) return;

            if (this.maxAvailableStock <= 0) {
                this.showToast("Maaf, stok varian ini sedang habis.", "error");
                return;
            }

            const variant = this.activeVariant
                ? this.activeVariant
                : {
                      id: null,
                      size: "All Size",
                      color: "-",
                      stock: this.maxAvailableStock,
                  };

            const variantId = variant.id ?? null;
            const sizeVal = variant.size || "All Size";
            const colorVal = variant.color || "-";
            const unitPrice = Number(
                this.selectedProduct.discount_price ||
                    this.selectedProduct.price,
            );
            const maxStock = Number(variant.stock ?? this.maxAvailableStock);

            const existingItem = this.cartItems.find(
                (i) =>
                    i.id === this.selectedProduct.id &&
                    (i.variant_id ?? null) === variantId &&
                    i.size === sizeVal &&
                    i.color === colorVal,
            );

            const requestedQty = Math.max(1, parseInt(this.qty, 10) || 1);
            const currentInCart = existingItem ? Number(existingItem.qty) : 0;

            if (currentInCart + requestedQty > maxStock) {
                this.showToast(
                    `Stok tidak mencukupi. Maksimal tersedia: ${maxStock} pcs (di keranjang: ${currentInCart} pcs).`,
                    "error",
                );
                return;
            }

            if (existingItem) {
                existingItem.qty += requestedQty;
                existingItem.variant_id = variantId;
                existingItem.image = this.selectedProduct.image;
                existingItem.price = unitPrice;
                existingItem.maxStock = maxStock;
            } else {
                this.cartItems.push({
                    id: this.selectedProduct.id,
                    variant_id: variantId,
                    name: this.selectedProduct.name,
                    category: this.selectedProduct.category || "Umum",
                    image: this.selectedProduct.image,
                    size: sizeVal,
                    color: colorVal,
                    price: unitPrice,
                    qty: requestedQty,
                    maxStock: maxStock,
                });
            }

            localStorage.setItem("cart_items", JSON.stringify(this.cartItems));
            window.dispatchEvent(
                new CustomEvent("cart-updated", { detail: this.cartItems }),
            );
            this.showToast(
                "Produk berhasil ditambahkan ke keranjang!",
                "success",
            );
            this.selectedProduct = null;
        },
    }));
});
