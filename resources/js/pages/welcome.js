window.welcomePage = function () {
    const config = window.__SM_WELCOME_CONFIG__ || {};
    return {
        mobileMenuOpen: false,
        openKategori: false,
        activeFilter: config.initialFilter || "semua",
        sortBy: "terbaru",
        searchQuery: config.initialSearch || "",
        selectedProduct: null,
        selectedVariantIndex: 0,
        qty: 1,
        productsMeta: window.__SM_PRODUCTS_META__ || [],
        toastTimer: null,

        toast: {
            show: false,
            message: "",
            type: "success",
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

        cartItems: (() => {
            try {
                const raw = JSON.parse(localStorage.getItem("cart_items"));
                return Array.isArray(raw) ? raw : [];
            } catch (e) {
                return [];
            }
        })(),

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
    };
};

window.welcomePromoCarousel = function () {
    return {
        slides: window.__SM_PROMO_SLIDES__ || [],
        current: 0,
        paused: false,
        timer: null,
        touchStartX: 0,
        get total() {
            return this.slides.length;
        },
        get prevIndex() {
            return (this.current - 1 + this.total) % this.total;
        },
        get nextIndex() {
            return (this.current + 1) % this.total;
        },
        next() {
            if (this.total <= 1) return;
            this.current = (this.current + 1) % this.total;
        },
        prev() {
            if (this.total <= 1) return;
            this.current = (this.current - 1 + this.total) % this.total;
        },
        goTo(idx) {
            this.current = idx;
        },
        startAuto() {
            if (this.total <= 1) return;
            this.stopAuto();
            this.timer = setInterval(() => {
                if (!this.paused) this.next();
            }, 5000);
        },
        stopAuto() {
            if (this.timer) clearInterval(this.timer);
        },
        handleTouchStart(e) {
            this.touchStartX = e.changedTouches[0].screenX;
        },
        handleTouchEnd(e) {
            const diff = e.changedTouches[0].screenX - this.touchStartX;
            if (Math.abs(diff) > 45) {
                diff < 0 ? this.next() : this.prev();
            }
        },
        init() {
            this.startAuto();
        },
    };
};

window.welcomeLookbookCarousel = function () {
    return {
        activeSlide: 0,
        paused: false,
        slides: [
            {
                title: "PRO RUNNING 2026",
                subtitle: "PERFORMANCE APPAREL",
                desc: "Dirancang dengan teknologi aerodinamis ringan untuk memaksimalkan kecepatan lari tanpa kompromi.",
                bgImage:
                    "https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1600&auto=format&fit=crop",
                tag: "01 / BEST SELLER",
            },
            {
                title: "STREET GYM EDIT",
                subtitle: "URBAN SPORTSWEAR",
                desc: "Kombinasi bahan dry-fit premium yang menyerap keringat dengan gaya urban streetwear yang trendi.",
                bgImage:
                    "https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=1600&auto=format&fit=crop",
                tag: "02 / NEW COLLECTION",
            },
            {
                title: "ELITE TRAINING",
                subtitle: "MAXIMUM STRENGTH",
                desc: "Dukungan perlengkapan angkat beban profesional untuk menjaga stabilitas dan performa latihan maksimal.",
                bgImage:
                    "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=1600&auto=format&fit=crop",
                tag: "03 / HIGH PERFORMANCE",
            },
        ],
        next() {
            this.activeSlide = (this.activeSlide + 1) % this.slides.length;
        },
        prev() {
            this.activeSlide =
                (this.activeSlide - 1 + this.slides.length) %
                this.slides.length;
        },
        init() {
            setInterval(() => {
                if (!this.paused) this.next();
            }, 6500);
        },
    };
};

window.welcomeEditorialCategories = function () {
    return {
        activeCategory: "women",
        categories: [
            {
                id: "women",
                name: "WOMEN",
                subtitle: "Elegance & Active",
                image: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=1000&auto=format&fit=crop",
                tagline: "Eksklusif Koleksi Wanita Aktif & Stylish",
            },
            {
                id: "men",
                name: "MEN",
                subtitle: "Streetwear & Sport",
                image: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=1000&auto=format&fit=crop",
                tagline: "Performa Maskulin & Urban Fashion",
            },
            {
                id: "sports",
                name: "SPORTS",
                subtitle: "High Performance",
                image: "https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1000&auto=format&fit=crop",
                tagline: "Gear Profesional untuk Atlet Juara",
            },
            {
                id: "lifestyle",
                name: "LIFESTYLE",
                subtitle: "Daily Comfort",
                image: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=1000&auto=format&fit=crop",
                tagline: "Kenyamanan Maksimal Setiap Hari",
            },
        ],
    };
};

window.welcomeFooter = function () {
    return {
        newsletterEmail: "",
        newsletterPref: "",
        newsletterSuccess: false,
        infoModal: {
            open: false,
            badge: "",
            title: "",
            content: "",
        },
        pages: {
            faq: {
                badge: "Pusat Bantuan",
                title: "Bantuan & Pertanyaan Umum (FAQ)",
                content:
                    "1. Semua produk di Sport Mania Distro dijamin 100% original & lokal pilihan.\n2. Pemesanan diproses setiap hari pukul 09.00 – 20.00 WIB.\n3. Setelah checkout, silakan unggah bukti pembayaran di menu Pesanan Saya atau klik Konfirmasi WhatsApp agar admin segera memverifikasi pembayaran dan menginput nomor resi pengiriman.",
            },
            retur: {
                badge: "Garansi Layanan",
                title: "Kebijakan Pengembalian & Tukar Ukuran",
                content:
                    "Pelanggan dapat mengajukan penukaran ukuran (size) atau pengembalian barang maksimal 3x24 jam setelah paket diterima dengan syarat: label/hangtag masih terpasang utuh, produk belum dicuci, dan menyertakan video unboxing.",
            },
            ongkir: {
                badge: "Informasi Pengiriman",
                title: "Pengiriman & Promo Gratis Ongkir",
                content:
                    "Kami mendukung pengiriman reguler melalui JNE REG dan SiCepat ke seluruh Indonesia, serta layanan Ambil di Store (Gratis) untuk area Kota Malang. Dapatkan GRATIS ONGKIR seluruh Pulau Jawa untuk minimal belanja Rp 250.000.",
            },
            tentang: {
                badge: "Profil Toko",
                title: "Tentang Sport Mania Distro",
                content:
                    "Sport Mania Distro adalah pusat Sport Fashion & Apparel berbasis di Malang, Jawa Timur. Kami menghadirkan koleksi jersey, sepatu lari, tas olahraga, dan aksesori latihan berkualitas tinggi untuk menunjang performa maupun gaya harian Anda.",
            },
            syarat: {
                badge: "Ketentuan Layanan",
                title: "Persyaratan & Ketentuan",
                content:
                    "Stok produk diperbarui secara real-time berdasarkan varian ukuran dan warna. Pesanan berstatus Pending yang belum dikonfirmasi pembayarannya dalam 1x24 jam dapat dibatalkan secara otomatis oleh sistem atau admin.",
            },
            privasi: {
                badge: "Keamanan Data",
                title: "Kebijakan Privasi Pelanggan",
                content:
                    "Data pribadi seperti nama, nomor WhatsApp, email, dan alamat pengiriman hanya digunakan untuk keperluan pemrosesan transaksi, pengiriman paket, dan layanan pelanggan Sport Mania Distro.",
            },
            app: {
                badge: "Segera Hadir",
                title: "Aplikasi Mobile Sport Mania",
                content:
                    "Aplikasi Android & iOS Sport Mania Distro sedang dalam tahap pengembangan. Untuk saat ini, nikmati pengalaman belanja responsif langsung melalui browser perangkat seluler maupun desktop Anda.",
            },
        },
        openInfo(key) {
            if (this.pages[key]) {
                this.infoModal = {
                    open: true,
                    badge: this.pages[key].badge,
                    title: this.pages[key].title,
                    content: this.pages[key].content,
                };
            }
        },
        submitNewsletter(pref = "Umum") {
            if (!this.newsletterEmail.trim()) return;
            this.newsletterPref = pref;
            this.newsletterSuccess = true;
            this.newsletterEmail = "";
            setTimeout(() => {
                this.newsletterSuccess = false;
            }, 4000);
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("welcomePage", window.welcomePage);
    window.Alpine.data("welcomePromoCarousel", window.welcomePromoCarousel);
    window.Alpine.data(
        "welcomeLookbookCarousel",
        window.welcomeLookbookCarousel,
    );
    window.Alpine.data(
        "welcomeEditorialCategories",
        window.welcomeEditorialCategories,
    );
    window.Alpine.data("welcomeFooter", window.welcomeFooter);
});
