window.customerOrdersPage = function () {
    return {
        statusFilter: "semua",
        counts: window.__SM_STATUS_COUNTS__ || {},
        copiedResiId: null,
        copiedRekening: "",
        confirmModal: null,
        reviewModal: null,
        paymentModal: null,
        printOrder: null,
        submitting: false,
        cartItems: JSON.parse(localStorage.getItem("cart_items")) || [],

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

        copyResi(text, orderId) {
            if (!text) return;
            navigator.clipboard.writeText(text);
            this.copiedResiId = orderId;
            setTimeout(() => {
                this.copiedResiId = null;
            }, 1800);
        },

        copyRekening(nomor) {
            if (!nomor) return;
            navigator.clipboard.writeText(nomor);
            this.copiedRekening = nomor;
            setTimeout(() => {
                this.copiedRekening = "";
            }, 1800);
        },

        openPaymentModal(data) {
            this.submitting = false;
            this.copiedRekening = "";
            this.paymentModal = {
                orderId: data.orderId,
                orderCode: data.orderCode,
                paymentMethod: data.paymentMethod,
                totalFormatted: data.totalFormatted,
                actionUrl: data.actionUrl,
                existingProofUrl: data.existingProofUrl || null,
                paymentStatus: data.paymentStatus || "unpaid",
                rejectedReason: data.rejectedReason || null,
                canUpload: data.canUpload !== false,
                previewUrl: null,
                fileName: "",
                fileError: "",
            };
        },

        handlePaymentFileChange(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.paymentModal.fileError = "";

            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/jpg",
                "image/webp",
            ];
            if (!allowedTypes.includes(file.type)) {
                this.paymentModal.fileError =
                    "Format file harus JPG, JPEG, PNG, atau WEBP.";
                event.target.value = "";
                return;
            }

            if (file.size > 4 * 1024 * 1024) {
                this.paymentModal.fileError = "Ukuran foto maksimal 4 MB.";
                event.target.value = "";
                return;
            }

            if (this.paymentModal.previewUrl) {
                URL.revokeObjectURL(this.paymentModal.previewUrl);
            }

            this.paymentModal.fileName = file.name;
            this.paymentModal.previewUrl = URL.createObjectURL(file);
        },

        submitPaymentForm(event) {
            if (!this.paymentModal || !this.paymentModal.fileName) {
                event.preventDefault();
                if (this.paymentModal) {
                    this.paymentModal.fileError =
                        "Silakan pilih foto bukti pembayaran terlebih dahulu.";
                }
                return;
            }
            this.submitting = true;
        },

        closePaymentModal() {
            if (this.submitting) return;
            if (this.paymentModal && this.paymentModal.previewUrl) {
                URL.revokeObjectURL(this.paymentModal.previewUrl);
            }
            this.paymentModal = null;
        },

        openConfirmModal(type, orderCode, actionUrl) {
            this.submitting = false;
            if (type === "cancel") {
                this.confirmModal = {
                    type: "cancel",
                    badge: "Pembatalan Pesanan",
                    title: "Batalkan #" + orderCode + "?",
                    message:
                        "Yakin ingin membatalkan pesanan #" +
                        orderCode +
                        "? Stok varian produk akan otomatis dikembalikan ke etalase.",
                    confirmText: "Ya, Batalkan Pesanan",
                    actionUrl: actionUrl,
                };
            } else {
                this.confirmModal = {
                    type: "complete",
                    badge: "Konfirmasi Penerimaan",
                    title: "Pesanan #" + orderCode + " Sudah Diterima?",
                    message:
                        "Pastikan paket #" +
                        orderCode +
                        " sudah diterima dengan baik. Setelah dikonfirmasi selesai, kamu langsung bisa memberi rating & ulasan produk.",
                    confirmText: "Ya, Pesanan Diterima",
                    actionUrl: actionUrl,
                };
            }
        },

        openReviewModal(productId, productName, orderId) {
            this.submitting = false;
            this.reviewModal = {
                product_id: productId,
                product_name: productName,
                order_id: orderId,
                rating: 5,
                comment: "",
            };
        },

        submitReviewForm(event) {
            if (!this.reviewModal || !this.reviewModal.comment.trim()) {
                event.preventDefault();
                return;
            }
            this.reviewModal.comment = this.reviewModal.comment.trim();
            this.submitting = true;
        },

        printInvoice(orderId) {
            const data = (window.__SM_CUSTOMER_ORDERS__ || {})[orderId];
            if (!data) return;
            this.printOrder = data;
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    window.print();
                });
            });
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("customerOrdersPage", window.customerOrdersPage);
});
