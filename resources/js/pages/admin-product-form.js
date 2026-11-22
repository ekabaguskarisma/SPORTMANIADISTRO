window.adminProductForm = function () {
    const config = window.__SM_PRODUCT_FORM_CONFIG__ || {};
    const initialVariants =
        Array.isArray(config.variants) && config.variants.length > 0
            ? config.variants
            : [{ id: Date.now(), size: "", color: "", stock: 0 }];

    return {
        variants: initialVariants,
        defaultColor: config.defaultColor || "Hitam",
        price: Number(config.price || 0),
        hpp: Number(config.hpp || 0),
        discountPrice: Number(config.discountPrice || 0),
        coverPreview: null,
        galleryPreviews: [],
        videoPreviews: [],
        removeVideo: false,
        deleteImageId: null,
        deleteVideoId: null,

        get totalStock() {
            return this.variants.reduce(
                (sum, v) => sum + (parseInt(v.stock, 10) || 0),
                0,
            );
        },

        get effectivePrice() {
            const p = parseFloat(this.price) || 0;
            const d = parseFloat(this.discountPrice) || 0;
            return d > 0 && d < p ? d : p;
        },

        get estimatedProfitPerUnit() {
            const h = parseFloat(this.hpp) || 0;
            if (h <= 0) return 0;
            return this.effectivePrice - h;
        },

        get estimatedMarginPercent() {
            const h = parseFloat(this.hpp) || 0;
            if (h <= 0 || this.effectivePrice <= 0) return null;
            return Math.round(
                ((this.effectivePrice - h) / this.effectivePrice) * 100,
            );
        },

        get discountPercent() {
            const p = parseFloat(this.price) || 0;
            const d = parseFloat(this.discountPrice) || 0;
            if (p > 0 && d > 0 && d < p) {
                return Math.round(((p - d) / p) * 100);
            }
            return 0;
        },

        formatRupiah(num) {
            return "Rp " + (parseInt(num, 10) || 0).toLocaleString("id-ID");
        },

        addVariantRow() {
            this.variants.push({
                id: Date.now() + Math.floor(Math.random() * 1000),
                size: "",
                color: this.defaultColor || "",
                stock: 0,
            });
        },

        removeVariantRow(index) {
            if (this.variants.length > 1) {
                this.variants.splice(index, 1);
            }
        },

        applyPreset(type) {
            const presets = {
                clothing: ["S", "M", "L", "XL", "XXL"],
                shoes: ["39", "40", "41", "42", "43", "44"],
                accessory: ["All Size"],
            };
            const sizes = presets[type] || [];
            const baseTime = Date.now();
            this.variants = sizes.map((sz, idx) => ({
                id: baseTime + idx,
                size: sz,
                color: this.defaultColor || "Hitam",
                stock: 5,
            }));
        },

        appendPreset(type) {
            const presets = {
                clothing: ["S", "M", "L", "XL", "XXL"],
                shoes: ["39", "40", "41", "42", "43", "44"],
                accessory: ["All Size"],
            };
            const sizes = presets[type] || [];
            const existingSizes = this.variants.map((v) =>
                (v.size || "").toUpperCase(),
            );
            const baseTime = Date.now();

            sizes.forEach((sz, idx) => {
                if (!existingSizes.includes(sz.toUpperCase())) {
                    this.variants.push({
                        id: baseTime + idx,
                        size: sz,
                        color: this.defaultColor || "Hitam",
                        stock: 0,
                    });
                }
            });
        },

        previewCover(event) {
            if (this.coverPreview) {
                URL.revokeObjectURL(this.coverPreview);
            }
            const file = event.target.files[0];
            this.coverPreview = file ? URL.createObjectURL(file) : null;
        },

        previewGallery(event) {
            this.galleryPreviews.forEach((url) => URL.revokeObjectURL(url));
            this.galleryPreviews = Array.from(event.target.files).map((file) =>
                URL.createObjectURL(file),
            );
        },

        previewVideos(event) {
            this.videoPreviews.forEach((item) => URL.revokeObjectURL(item.url));
            this.videoPreviews = Array.from(event.target.files).map((file) => ({
                file: file,
                name: file.name,
                sizeMB: (file.size / (1024 * 1024)).toFixed(1),
                url: URL.createObjectURL(file),
            }));
        },

        removeVideoPreview(idx) {
            if (this.videoPreviews[idx]) {
                URL.revokeObjectURL(this.videoPreviews[idx].url);
            }
            this.videoPreviews.splice(idx, 1);
            if (this.$refs.videoInput) {
                const dt = new DataTransfer();
                this.videoPreviews.forEach((item) => dt.items.add(item.file));
                this.$refs.videoInput.files = dt.files;
            }
        },

        clearAllVideoPreviews() {
            this.videoPreviews.forEach((item) => URL.revokeObjectURL(item.url));
            this.videoPreviews = [];
            if (this.$refs.videoInput) {
                this.$refs.videoInput.value = "";
            }
        },

        confirmDeleteImage() {
            if (!this.deleteImageId) return;
            const form = document.getElementById(
                "delete-img-" + this.deleteImageId,
            );
            this.deleteImageId = null;
            if (form) form.submit();
        },

        confirmDeleteVideo() {
            if (!this.deleteVideoId) return;
            const form = document.getElementById(
                "delete-vid-" + this.deleteVideoId,
            );
            this.deleteVideoId = null;
            if (form) form.submit();
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("adminProductForm", window.adminProductForm);
});
