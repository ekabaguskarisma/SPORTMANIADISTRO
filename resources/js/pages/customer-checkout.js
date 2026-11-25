window.customerCheckout = function (config = {}) {
    const storeConfig = config.storeLocation || {};
    const freeShippingEnabled = Boolean(
        config.freeShippingEnabled ?? storeConfig.free_shipping_enabled ?? true,
    );
    const minOrderFreeShippingEnabled = Boolean(
        config.minOrderFreeShippingEnabled ??
        storeConfig.min_order_free_shipping_enabled ??
        true,
    );
    const minOrderFreeShippingAmount = Number(
        config.minOrderFreeShippingAmount ??
            storeConfig.min_order_free_shipping_amount ??
            250000,
    );
    const storeLat = Number(storeConfig.store_lat ?? -7.9398);
    const storeLng = Number(storeConfig.store_lng ?? 112.6083);
    const freeRadiusKm = freeShippingEnabled
        ? Number(storeConfig.free_shipping_radius_km ?? 10)
        : 0;

    return {
        voucherApplyUrl: config.voucherApplyUrl || "",
        checkoutProcessUrl: config.checkoutProcessUrl || "",
        csrfToken: config.csrfToken || "",

        freeShippingEnabled,
        minOrderFreeShippingEnabled,
        minOrderFreeShippingAmount,
        storeLat,
        storeLng,
        freeRadiusKm,
        mapInstance: null,
        customerMarker: null,
        isLocating: false,

        cartItems: (JSON.parse(localStorage.getItem("cart_items")) || []).map(
            (item) => ({
                ...item,
                size: item.size || "All Size",
                color: item.color || "-",
                qty: Math.max(1, Number(item.qty || 1)),
            }),
        ),
        isLoading: false,
        qrisZoom: false,
        proofFile: null,
        proofPreview: null,

        // State Voucher Promo
        voucherInput: "",
        appliedVoucher: null,
        voucherDiscount: 0,
        voucherMessage: "",
        voucherError: "",
        isCheckingVoucher: false,

        // State Modal Sukses Order
        orderSuccess: {
            show: false,
            orderNumber: "",
            waUrl: "",
            totalFormatted: "",
            popupBlocked: false,
        },

        // Toast Notification State
        toast: {
            show: false,
            message: "",
            type: "error",
        },

        form: {
            name: config.defaultName || "",
            phone: config.defaultPhone || "",
            email: config.defaultEmail || "",
            address: config.defaultAddress || "",
            lat: null,
            lng: null,
            shippingMethod: (() => {
                const saved = localStorage.getItem("selected_courier") || "jne";
                if (saved === "SiCepat BEST" || saved === "sicepat")
                    return "sicepat";
                if (saved === "Ambil di Store" || saved === "pickup")
                    return "pickup";
                return "jne";
            })(),
            paymentMethod: "transfer",
            notes: "",
        },

        init() {
            if (!this.freeShippingEnabled) return;
            this.$nextTick(() => {
                this.initCheckoutMap();
            });
        },

        initCheckoutMap() {
            if (!this.freeShippingEnabled) return;
            const mapEl = document.getElementById("checkout-delivery-map");
            if (!mapEl || typeof window.L === "undefined") return;
            if (this.mapInstance) {
                this.mapInstance.invalidateSize();
                return;
            }

            const L = window.L;
            const map = L.map("checkout-delivery-map").setView(
                [this.storeLat, this.storeLng],
                12,
            );

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: "© OpenStreetMap contributors",
            }).addTo(map);

            // Marker Toko Sport Mania Distro
            L.marker([this.storeLat, this.storeLng])
                .addTo(map)
                .bindPopup(
                    "<strong>Sport Mania Distro</strong><br>Titik Pusat Pengiriman (Malang)",
                );

            // Lingkaran Zona Gratis Ongkir Malang Kota
            if (this.freeRadiusKm > 0) {
                L.circle([this.storeLat, this.storeLng], {
                    color: "#10b981",
                    fillColor: "#10b981",
                    fillOpacity: 0.12,
                    radius: this.freeRadiusKm * 1000,
                }).addTo(map);
            }

            // Klik di peta untuk menaruh / memindahkan pin alamat pembeli
            map.on("click", (e) => {
                this.setCustomerPin(e.latlng.lat, e.latlng.lng, true);
            });

            this.mapInstance = map;
        },

        setCustomerPin(lat, lng, fetchAddress = false) {
            if (!this.freeShippingEnabled) return;
            const L = window.L;
            if (!this.mapInstance || !L) return;

            const parsedLat = Number(Number(lat).toFixed(7));
            const parsedLng = Number(Number(lng).toFixed(7));
            this.form.lat = parsedLat;
            this.form.lng = parsedLng;

            if (!this.customerMarker) {
                const redIcon = L.divIcon({
                    className: "custom-customer-pin",
                    html: `<div style="background:#dc2626;width:22px;height:22px;border-radius:50%;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.35);"></div>`,
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                });

                this.customerMarker = L.marker([parsedLat, parsedLng], {
                    draggable: true,
                    icon: redIcon,
                }).addTo(this.mapInstance);

                this.customerMarker.on("dragend", (event) => {
                    const pos = event.target.getLatLng();
                    this.setCustomerPin(pos.lat, pos.lng, true);
                });
            } else {
                this.customerMarker.setLatLng([parsedLat, parsedLng]);
            }

            this.customerMarker
                .bindPopup(
                    `<strong>Titik Pengiriman Anda</strong><br>Jarak: ${this.distanceKm} km`,
                )
                .openPopup();

            if (fetchAddress) {
                this.reverseGeocode(parsedLat, parsedLng);
            }
        },

        useCurrentLocation() {
            if (!this.freeShippingEnabled) return;
            if (!navigator.geolocation) {
                this.showToast(
                    "Browser Anda tidak mendukung deteksi lokasi GPS.",
                    "error",
                );
                return;
            }

            this.isLocating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.isLocating = false;
                    const { latitude, longitude } = pos.coords;
                    if (this.mapInstance) {
                        this.mapInstance.setView([latitude, longitude], 15);
                    }
                    this.setCustomerPin(latitude, longitude, true);
                    this.showToast(
                        "Titik lokasi GPS berhasil ditemukan!",
                        "success",
                    );
                },
                () => {
                    this.isLocating = false;
                    this.showToast(
                        "Gagal mengakses GPS. Silakan klik langsung titik rumah Anda di peta.",
                        "error",
                    );
                },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },

        async reverseGeocode(lat, lng) {
            try {
                const res = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`,
                    { headers: { Accept: "application/json" } },
                );
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.display_name && !this.form.address.trim()) {
                    this.form.address = data.display_name;
                }
            } catch (_) {
                // Abaikan jika gagal reverse geocode; user tetap bisa mengetik alamat manual
            }
        },

        get distanceKm() {
            if (
                !this.freeShippingEnabled ||
                this.form.lat === null ||
                this.form.lng === null
            )
                return null;
            const toRad = (deg) => (deg * Math.PI) / 180;
            const R = 6371; // Radius bumi (km)
            const dLat = toRad(this.form.lat - this.storeLat);
            const dLng = toRad(this.form.lng - this.storeLng);
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRad(this.storeLat)) *
                    Math.cos(toRad(this.form.lat)) *
                    Math.sin(dLng / 2) *
                    Math.sin(dLng / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return Number((R * c).toFixed(2));
        },

        get isWithinFreeRadius() {
            if (!this.freeShippingEnabled) return false;
            return (
                this.distanceKm !== null &&
                this.freeRadiusKm > 0 &&
                this.distanceKm <= this.freeRadiusKm
            );
        },

        get isEligibleMinOrderFreeShipping() {
            if (!this.minOrderFreeShippingEnabled) return false;
            if (this.minOrderFreeShippingAmount <= 0) return true;
            return this.subtotal >= this.minOrderFreeShippingAmount;
        },

        get minOrderLabel() {
            const amt = this.minOrderFreeShippingAmount;
            if (amt >= 1000000) {
                return (amt / 1000000).toLocaleString("id-ID") + "jt";
            }
            if (amt >= 1000) {
                return Math.round(amt / 1000).toLocaleString("id-ID") + "rb";
            }
            return "Rp " + amt.toLocaleString("id-ID");
        },

        syncCart() {
            const raw = JSON.parse(localStorage.getItem("cart_items")) || [];
            this.cartItems = raw.map((item) => ({
                ...item,
                size: item.size || "All Size",
                color: item.color || "-",
                qty: Math.max(1, Number(item.qty || 1)),
            }));
            if (this.appliedVoucher) {
                this.applyVoucher(true);
            }
        },

        showToast(message, type = "error") {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 4500);
        },

        async applyVoucher(silent = false) {
            const code = (
                silent && this.appliedVoucher
                    ? this.appliedVoucher
                    : this.voucherInput
            )
                .trim()
                .toUpperCase();
            if (!code) {
                this.voucherError = "Masukkan kode voucher terlebih dahulu.";
                return;
            }
            if (this.subtotal <= 0) {
                this.voucherError = "Keranjang belanja masih kosong.";
                return;
            }

            this.isCheckingVoucher = true;
            this.voucherError = "";
            if (!silent) this.voucherMessage = "";

            try {
                const res = await fetch(this.voucherApplyUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrfToken,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        code: code,
                        subtotal: this.subtotal,
                    }),
                });

                const data = await res.json();

                if (res.ok && (data.valid || data.success)) {
                    this.appliedVoucher = data.code || code;
                    this.voucherInput = this.appliedVoucher;
                    this.voucherDiscount = Math.min(
                        this.subtotal,
                        Number(data.discount || data.discount_amount || 0),
                    );
                    this.voucherMessage =
                        data.message ||
                        `Voucher ${this.appliedVoucher} berhasil diterapkan!`;
                    this.voucherError = "";
                    if (!silent) {
                        this.showToast(this.voucherMessage, "success");
                    }
                } else {
                    this.appliedVoucher = null;
                    this.voucherDiscount = 0;
                    this.voucherMessage = "";
                    this.voucherError =
                        data.message ||
                        "Kode voucher tidak valid atau tidak memenuhi syarat.";
                    if (!silent) {
                        this.showToast(this.voucherError, "error");
                    }
                }
            } catch (err) {
                this.voucherError =
                    "Gagal mengecek kode voucher. Periksa koneksi internet.";
                if (!silent) {
                    this.showToast(this.voucherError, "error");
                }
            } finally {
                this.isCheckingVoucher = false;
            }
        },

        removeVoucher() {
            this.appliedVoucher = null;
            this.voucherInput = "";
            this.voucherDiscount = 0;
            this.voucherMessage = "";
            this.voucherError = "";
            this.showToast("Kode voucher dihapus.", "success");
        },

        handleProofUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            if (!allowedTypes.includes(file.type)) {
                this.showToast(
                    "Format foto harus JPG, PNG, atau WEBP.",
                    "error",
                );
                event.target.value = "";
                return;
            }
            if (file.size > 4 * 1024 * 1024) {
                this.showToast(
                    "Ukuran foto bukti bayar maksimal 4MB.",
                    "error",
                );
                event.target.value = "";
                return;
            }
            if (this.proofPreview) {
                URL.revokeObjectURL(this.proofPreview);
            }
            this.proofFile = file;
            this.proofPreview = URL.createObjectURL(file);
        },

        removeProof() {
            if (this.proofPreview) {
                URL.revokeObjectURL(this.proofPreview);
            }
            this.proofFile = null;
            this.proofPreview = null;
            if (this.$refs.proofInput) {
                this.$refs.proofInput.value = "";
            }
        },

        copyRekening(text) {
            navigator.clipboard.writeText(text);
            this.showToast(
                "Nomor rekening berhasil disalin: " + text,
                "success",
            );
        },

        setShippingMethod(method) {
            this.form.shippingMethod = method;
            localStorage.setItem("selected_courier", method);
            if (method !== "pickup" && this.freeShippingEnabled) {
                this.$nextTick(() => {
                    if (this.mapInstance) {
                        this.mapInstance.invalidateSize();
                    } else {
                        this.initCheckoutMap();
                    }
                });
            }
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

        get effectiveDiscount() {
            return Math.min(this.subtotal, Number(this.voucherDiscount || 0));
        },

        get shippingCost() {
            if (this.form.shippingMethod === "pickup" || this.subtotal === 0)
                return 0;
            if (this.freeShippingEnabled && this.isWithinFreeRadius) return 0;
            if (this.isEligibleMinOrderFreeShipping) return 0;
            return this.form.shippingMethod === "sicepat" ? 25000 : 20000;
        },

        get total() {
            return Math.max(
                0,
                this.subtotal - this.effectiveDiscount + this.shippingCost,
            );
        },

        async processCheckout() {
            if (this.isLoading) return;

            if (this.cartItems.length === 0) {
                this.showToast("Keranjang belanja Anda masih kosong!", "error");
                return;
            }
            if (!this.form.name.trim()) {
                this.showToast("Nama lengkap penerima wajib diisi.", "error");
                return;
            }

            const phoneRegex = /^\+?[0-9\s\-\(\)]{8,20}$/;
            if (!phoneRegex.test(this.form.phone.trim())) {
                this.showToast(
                    "Format nomor WhatsApp tidak valid! Masukkan 8 hingga 20 digit angka (boleh diawali tanda +).",
                    "error",
                );
                return;
            }

            if (
                this.form.shippingMethod !== "pickup" &&
                !this.form.address.trim()
            ) {
                this.showToast(
                    "Alamat lengkap pengiriman wajib diisi.",
                    "error",
                );
                return;
            }

            this.isLoading = true;

            const itemsSnapshot = this.cartItems.map((item) => ({
                ...item,
                size: item.size || "All Size",
                color: item.color || "-",
                qty: Math.max(1, Number(item.qty || 1)),
            }));
            const subtotalSnapshot = this.subtotal;
            const discountSnapshot = this.effectiveDiscount;
            const voucherCodeSnapshot = this.appliedVoucher || "";
            const shippingSnapshot = this.shippingCost;
            const totalSnapshot = this.total;

            try {
                const formData = new FormData();
                formData.append("name", this.form.name.trim());
                formData.append("phone", this.form.phone.trim());
                formData.append("email", this.form.email.trim());
                formData.append(
                    "address",
                    this.form.shippingMethod === "pickup"
                        ? "Ambil di Store Malang"
                        : this.form.address.trim(),
                );
                if (
                    this.freeShippingEnabled &&
                    this.form.shippingMethod !== "pickup" &&
                    this.form.lat !== null &&
                    this.form.lng !== null
                ) {
                    formData.append("shipping_lat", this.form.lat);
                    formData.append("shipping_lng", this.form.lng);
                }
                formData.append("shipping_method", this.form.shippingMethod);
                formData.append("payment_method", this.form.paymentMethod);
                formData.append("notes", this.form.notes.trim());
                formData.append("subtotal", subtotalSnapshot);
                formData.append("discount_amount", discountSnapshot);
                if (voucherCodeSnapshot) {
                    formData.append("voucher_code", voucherCodeSnapshot);
                }
                formData.append("shipping_cost", shippingSnapshot);
                formData.append("total", totalSnapshot);
                formData.append("cart_items", JSON.stringify(itemsSnapshot));

                if (this.proofFile && this.form.paymentMethod !== "cod") {
                    formData.append("payment_proof", this.proofFile);
                }

                const response = await fetch(this.checkoutProcessUrl, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": this.csrfToken,
                        Accept: "application/json",
                    },
                    body: formData,
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    const finalTotal = Number(result.total ?? totalSnapshot);
                    const finalDiscount = Number(
                        result.discount_amount ?? discountSnapshot,
                    );
                    const finalShipping = Number(
                        result.shipping_cost ?? shippingSnapshot,
                    );

                    const detailPesanan = itemsSnapshot
                        .map(
                            (item, index) =>
                                `*${index + 1}. ${item.name}* (${item.size}, ${item.color}) - ${item.qty}x @ Rp ${Number(item.price).toLocaleString("id-ID")}`,
                        )
                        .join("\n");

                    const statusBukti =
                        this.proofFile && this.form.paymentMethod !== "cod"
                            ? "✅ Sudah diunggah saat checkout"
                            : this.form.paymentMethod === "cod"
                              ? "💵 Bayar di Tempat (COD)"
                              : "⏳ Menyusul di Riwayat Pesanan";

                    const mapsPinLine =
                        this.freeShippingEnabled &&
                        this.form.shippingMethod !== "pickup" &&
                        this.form.lat !== null &&
                        this.form.lng !== null
                            ? `🗺️ *Titik Peta (${this.distanceKm} km):* https://maps.google.com/?q=${this.form.lat},${this.form.lng}\n`
                            : "";

                    const rawTextWA =
                        `*HALO ADMIN SPORT MANIA DISTRO*\nSaya ingin mengkonfirmasi pesanan baru dengan detail berikut:\n\n` +
                        `🔖 *ORDER ID:* *${result.order_number}*\n` +
                        `👤 *Nama:* ${this.form.name.trim()}\n` +
                        `📞 *No. HP:* ${this.form.phone.trim()}\n` +
                        `📍 *Pengiriman:* ${this.form.shippingMethod.toUpperCase()}\n` +
                        (this.form.shippingMethod !== "pickup"
                            ? `🏠 *Alamat:* ${this.form.address.trim()}\n${mapsPinLine}`
                            : `🏢 *Catatan:* Ambil langsung di Store Malang\n`) +
                        `💳 *Pembayaran:* ${this.form.paymentMethod.toUpperCase()} (${statusBukti})\n` +
                        (this.form.notes.trim()
                            ? `📝 *Catatan Pembeli:* ${this.form.notes.trim()}\n`
                            : "") +
                        `\n🛒 *Detail Produk:*\n${detailPesanan}\n\n` +
                        `💰 *Subtotal:* Rp ${Number(subtotalSnapshot).toLocaleString("id-ID")}\n` +
                        (finalDiscount > 0
                            ? `🎟️ *Voucher (${voucherCodeSnapshot}):* -Rp ${Number(finalDiscount).toLocaleString("id-ID")}\n`
                            : "") +
                        `📦 *Ongkir:* ${finalShipping === 0 ? "GRATIS" : "Rp " + Number(finalShipping).toLocaleString("id-ID")}\n` +
                        `🔥 *Total Tagihan:* *Rp ${Number(finalTotal).toLocaleString("id-ID")}*`;

                    const waLink = `https://wa.me/6282234548092?text=${encodeURIComponent(rawTextWA)}`;

                    localStorage.removeItem("cart_items");
                    this.cartItems = [];
                    this.appliedVoucher = null;
                    this.voucherInput = "";
                    this.voucherDiscount = 0;
                    window.dispatchEvent(
                        new CustomEvent("cart-updated", { detail: [] }),
                    );

                    const waWin = window.open(waLink, "_blank");

                    this.orderSuccess = {
                        show: true,
                        orderNumber: result.order_number,
                        waUrl: waLink,
                        totalFormatted:
                            "Rp " + Number(finalTotal).toLocaleString("id-ID"),
                        popupBlocked: !waWin,
                    };

                    this.isLoading = false;
                    this.showToast("Pesanan berhasil disimpan!", "success");
                } else {
                    let errorMsg = result.message || "Gagal memproses pesanan.";
                    if (result.errors) {
                        const firstField = Object.keys(result.errors)[0];
                        if (firstField && result.errors[firstField][0]) {
                            errorMsg = result.errors[firstField][0];
                        }
                    }
                    this.showToast(errorMsg, "error");
                    this.isLoading = false;
                }
            } catch (error) {
                this.showToast(
                    "Terjadi kesalahan jaringan atau server.",
                    "error",
                );
                console.error(error);
                this.isLoading = false;
            }
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("customerCheckout", window.customerCheckout);
});
