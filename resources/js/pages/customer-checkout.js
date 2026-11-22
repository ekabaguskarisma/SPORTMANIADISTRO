window.customerCheckout = function (config = {}) {
    return {
        voucherApplyUrl: config.voucherApplyUrl || "",
        checkoutProcessUrl: config.checkoutProcessUrl || "",
        csrfToken: config.csrfToken || "",

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
            if (this.subtotal >= 250000) return 0;
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

                    const rawTextWA =
                        `*HALO ADMIN SPORT MANIA DISTRO*\nSaya ingin mengkonfirmasi pesanan baru dengan detail berikut:\n\n` +
                        `🔖 *ORDER ID:* *${result.order_number}*\n` +
                        `👤 *Nama:* ${this.form.name.trim()}\n` +
                        `📞 *No. HP:* ${this.form.phone.trim()}\n` +
                        `📍 *Pengiriman:* ${this.form.shippingMethod.toUpperCase()}\n` +
                        (this.form.shippingMethod !== "pickup"
                            ? `🏠 *Alamat:* ${this.form.address.trim()}\n`
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
                        `📦 *Ongkir:* ${shippingSnapshot === 0 ? "GRATIS" : "Rp " + Number(shippingSnapshot).toLocaleString("id-ID")}\n` +
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
