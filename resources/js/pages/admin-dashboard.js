window.__SM_RECAP_CHARTS__ = { daily: null, annual: null, payment: null };

window.adminPanel = function () {
    return {
        sidebarOpen: false,
        activeTab: sessionStorage.getItem("sm_admin_tab") || "dashboard",
        searchProduct: "",
        filterCategory: "semua",
        searchCustomer: "",
        searchOrder: "",
        orderFilter: "semua",
        orderTimeFilter: "semua",
        orderDateFrom: "",
        orderDateUntil: "",
        searchHistory: "",
        historyFilter: "semua",
        historyTimeFilter: "semua",
        historyDateFrom: "",
        historyDateUntil: "",
        selectedOrder: null,
        rejectFormOpen: false,
        rejectReasonInput: "",
        proofLightbox: {
            open: false,
            url: "",
            code: "",
            orderId: null,
        },
        printOrder: null,
        printMode: "invoice",
        toastMessage: "",

        // ============================================================
        // STATE KASIR (POS OFFLINE TOKO)
        // ============================================================
        posSearch: "",
        posCategory: "semua",
        posSelectedVariants: {},
        posCart: [],
        posCustomerName: "Pelanggan Walk-in",
        posCustomerPhone: "",
        posPaymentMethod: "TUNAI",
        posDiscount: 0,
        posCashReceived: 0,
        posNotes: "",

        // ============================================================
        // STATE REKAP BULANAN + MODAL AWAL + HPP + LABA KOTOR
        // ============================================================
        recapMonth: new Date().getMonth() + 1,
        recapYear: new Date().getFullYear(),
        recapBasis: "realized",
        modalAwalOpen: false,
        modalAwalDraft: 0,
        modalAwalByPeriod: JSON.parse(
            localStorage.getItem("sm_modal_awal_v1") || "{}",
        ),
        defaultHppRatio: 0.65,

        // State Filter Bulan & Modal di Ringkasan Panel
        dashPeriodMode: "all",
        dashMonth: new Date().getMonth() + 1,
        dashYear: new Date().getFullYear(),
        dashModal: {
            open: false,
            type: "",
        },

        promoFormOpen: false,
        editingPromo: null,
        openNewPromo() {
            this.editingPromo = null;
            this.promoFormOpen = true;
        },
        openEditPromo(promoObj) {
            this.editingPromo = JSON.parse(JSON.stringify(promoObj));
            this.promoFormOpen = true;
        },

        voucherFormOpen: false,
        voucherType: "percent",

        confirmDialog: {
            open: false,
            title: "",
            message: "",
            confirmText: "Ya, Lanjutkan",
            variant: "danger",
            formId: "",
            onCancel: null,
        },

        init() {
            (window.__SM_POS_PRODUCTS__ || []).forEach((p) => {
                if (p.has_variants && p.variants.length > 0) {
                    const firstInStock =
                        p.variants.find((v) => v.stock > 0) || p.variants[0];
                    this.posSelectedVariants[p.id] = firstInStock.id;
                }
            });

            this.$watch("activeTab", (val) => {
                if (val === "rekap") {
                    this.$nextTick(() =>
                        setTimeout(() => this.renderRecapCharts(), 80),
                    );
                }
            });
            this.$watch("recapMonth", () => {
                this.modalAwalDraft = this.getCurrentModalAwal();
                if (this.activeTab === "rekap")
                    this.$nextTick(() => this.renderRecapCharts());
            });
            this.$watch("recapYear", () => {
                this.modalAwalDraft = this.getCurrentModalAwal();
                if (this.activeTab === "rekap")
                    this.$nextTick(() => this.renderRecapCharts());
            });
            this.$watch("recapBasis", () => {
                if (this.activeTab === "rekap")
                    this.$nextTick(() => this.renderRecapCharts());
            });
        },

        setTab(tab) {
            this.activeTab = tab;
            sessionStorage.setItem("sm_admin_tab", tab);
            this.sidebarOpen = false;
            if (tab === "rekap") {
                this.$nextTick(() =>
                    setTimeout(() => this.renderRecapCharts(), 80),
                );
            }
        },

        showToast(msg) {
            this.toastMessage = msg;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                this.toastMessage = "";
            }, 2600);
        },

        // ============================================================
        // ENGINE KASIR (POS OFFLINE TOKO)
        // ============================================================
        getPosFilteredProducts() {
            const list = Array.isArray(window.__SM_POS_PRODUCTS__)
                ? window.__SM_POS_PRODUCTS__
                : [];
            const q = (this.posSearch || "").toLowerCase().trim();
            return list.filter((p) => {
                const matchCat =
                    this.posCategory === "semua" ||
                    p.category_slug === this.posCategory;
                const matchName = !q || p.name.toLowerCase().includes(q);
                return matchCat && matchName;
            });
        },

        getPosSelectedVariantObj(product) {
            if (!product || !product.has_variants || !product.variants.length)
                return null;
            const selectedId = Number(this.posSelectedVariants[product.id]);
            return (
                product.variants.find((v) => Number(v.id) === selectedId) ||
                product.variants[0]
            );
        },

        getPosAvailableStock(product) {
            if (!product) return 0;
            if (product.has_variants) {
                const v = this.getPosSelectedVariantObj(product);
                return v ? Number(v.stock || 0) : 0;
            }
            return Number(product.stock || 0);
        },

        addToPosCart(product) {
            if (
                !product.has_variants ||
                !product.variants ||
                product.variants.length === 0
            ) {
                this.showToast("Produk ini belum memiliki varian stok!");
                return;
            }

            const variant = this.getPosSelectedVariantObj(product);
            const maxStock = this.getPosAvailableStock(product);

            if (!variant || maxStock <= 0) {
                this.showToast("Stok produk/varian ini habis!");
                return;
            }

            const key = product.id + "_" + variant.id;
            const existing = this.posCart.find((item) => item.key === key);

            if (existing) {
                if (existing.qty >= maxStock) {
                    this.showToast(
                        "Jumlah maksimal sesuai stok tersedia (" +
                            maxStock +
                            " pcs)",
                    );
                    return;
                }
                existing.qty += 1;
            } else {
                this.posCart.push({
                    key,
                    product_id: product.id,
                    variant_id: variant.id,
                    name: product.name,
                    size: variant.size,
                    color: variant.color,
                    price: Number(product.price || 0),
                    hpp: Number(product.hpp || 0),
                    qty: 1,
                    max_stock: maxStock,
                    image: product.image,
                });
            }
            this.showToast("+ " + product.name + " masuk keranjang kasir");
        },

        updatePosCartQty(index, delta) {
            const item = this.posCart[index];
            if (!item) return;
            const nextQty = item.qty + delta;
            if (nextQty <= 0) {
                this.posCart.splice(index, 1);
                return;
            }
            if (nextQty > item.max_stock) {
                this.showToast(
                    "Stok maksimal tersisa " + item.max_stock + " pcs",
                );
                return;
            }
            item.qty = nextQty;
        },

        removePosCartItem(index) {
            this.posCart.splice(index, 1);
        },

        clearPosCart() {
            this.posCart = [];
            this.posDiscount = 0;
            this.posCashReceived = 0;
            this.posNotes = "";
            this.posCustomerName = "Pelanggan Walk-in";
            this.posCustomerPhone = "";
        },

        getPosSubtotal() {
            return this.posCart.reduce(
                (sum, it) => sum + Number(it.price) * Number(it.qty),
                0,
            );
        },

        getPosTotal() {
            const sub = this.getPosSubtotal();
            const disc = Math.max(0, Number(this.posDiscount || 0));
            return Math.max(0, sub - disc);
        },

        getPosChange() {
            if (this.posPaymentMethod !== "TUNAI") return 0;
            const paid = Number(this.posCashReceived || 0);
            const total = this.getPosTotal();
            return Math.max(0, paid - total);
        },

        canSubmitPosCheckout() {
            if (this.posCart.length === 0) return false;
            if (this.posPaymentMethod === "TUNAI") {
                return Number(this.posCashReceived || 0) >= this.getPosTotal();
            }
            return true;
        },

        previewPosReceipt() {
            if (this.posCart.length === 0) {
                this.showToast("Keranjang kasir masih kosong");
                return;
            }
            const now = new Date();
            const dateStr =
                now.toLocaleDateString("id-ID", {
                    day: "2-digit",
                    month: "short",
                    year: "numeric",
                }) +
                ", " +
                now.toTimeString().slice(0, 5);
            const mockOrder = {
                code:
                    "POS-" +
                    now.getFullYear() +
                    String(now.getMonth() + 1).padStart(2, "0") +
                    String(now.getDate()).padStart(2, "0") +
                    "-" +
                    String(now.getHours()).padStart(2, "0") +
                    String(now.getMinutes()).padStart(2, "0"),
                date: dateStr,
                status: "selesai",
                customer: this.posCustomerName || "Pelanggan Walk-in",
                phone: this.posCustomerPhone || "-",
                payment: this.posPaymentMethod,
                shipping_method: "KASIR TOKO (POS)",
                address: "Transaksi Langsung di Store Malang",
                city: "Malang",
                tracking_number: "",
                subtotal: this.getPosSubtotal(),
                shipping_cost: 0,
                total: this.getPosTotal(),
                items: this.posCart.map((it) => ({
                    name: it.name,
                    size: it.size,
                    color: it.color,
                    qty: it.qty,
                    price: it.price,
                    hpp: it.hpp,
                    subtotal: it.price * it.qty,
                })),
            };
            this.triggerPrint(mockOrder, "struk");
        },

        // ============================================================
        // HELPER FILTER BULAN & MODAL RINGKASAN PANEL
        // ============================================================
        getDashboardStats() {
            const all = Object.values(window.__SM_ORDERS__ || {});
            const allCustomers = Array.isArray(window.__SM_CUSTOMERS__)
                ? window.__SM_CUSTOMERS__
                : [];
            const isMonthly = this.dashPeriodMode === "monthly";
            const targetM = Number(this.dashMonth);
            const targetY = Number(this.dashYear);

            const filtered = !isMonthly
                ? all
                : all.filter(
                      (o) =>
                          Number(o.created_month) === targetM &&
                          Number(o.created_year) === targetY,
                  );

            const filteredCustomers = !isMonthly
                ? allCustomers
                : allCustomers.filter(
                      (c) =>
                          Number(c.created_month) === targetM &&
                          Number(c.created_year) === targetY,
                  );

            const pending = filtered.filter(
                (o) => o.status === "pending",
            ).length;
            const diproses = filtered.filter(
                (o) => o.status === "diproses",
            ).length;
            const dikirim = filtered.filter(
                (o) => o.status === "dikirim",
            ).length;
            const selesaiList = filtered.filter((o) => o.status === "selesai");
            const dibatalkan = filtered.filter(
                (o) => o.status === "dibatalkan",
            ).length;
            const waitingVerify = filtered.filter(
                (o) =>
                    o.status !== "dibatalkan" &&
                    o.payment_status === "waiting_verification",
            ).length;

            const realizedRevenue = selesaiList.reduce(
                (s, o) => s + Number(o.total || 0),
                0,
            );
            const paidRevenue = filtered
                .filter(
                    (o) =>
                        o.status !== "dibatalkan" &&
                        o.payment_status === "paid",
                )
                .reduce((s, o) => s + Number(o.total || 0), 0);
            const todayRevenue = filtered
                .filter(
                    (o) =>
                        o.is_today &&
                        o.status !== "dibatalkan" &&
                        o.payment_status === "paid",
                )
                .reduce((s, o) => s + Number(o.total || 0), 0);
            const pipelineRevenue = filtered
                .filter((o) =>
                    ["pending", "diproses", "dikirim"].includes(o.status),
                )
                .reduce((s, o) => s + Number(o.total || 0), 0);

            return {
                filtered,
                filteredCustomers,
                customerCount: filteredCustomers.length,
                totalOrders: filtered.length,
                needProcess: pending + diproses,
                waitingVerify,
                pending,
                diproses,
                dikirim,
                selesai: selesaiList.length,
                dibatalkan,
                realizedRevenue,
                paidRevenue,
                todayRevenue,
                pipelineRevenue,
                periodLabel: !isMonthly
                    ? "Semua Waktu"
                    : `${this.getMonthName(this.dashMonth)} ${this.dashYear}`,
            };
        },

        // ============================================================
        // ENGINE REKAP BULANAN, MODAL AWAL, HPP, LABA KOTOR & GRAFIK
        // ============================================================
        getAvailableRecapYears() {
            const currentY = new Date().getFullYear();
            const years = new Set([currentY, currentY - 1]);
            Object.values(window.__SM_ORDERS__ || {}).forEach((o) => {
                if (o.created_year) years.add(Number(o.created_year));
            });
            return Array.from(years).sort((a, b) => b - a);
        },

        getMonthName(m) {
            const names = [
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ];
            return names[Number(m) - 1] || "-";
        },

        getModalAwalKey(y = this.recapYear, m = this.recapMonth) {
            return `${Number(y)}-${String(Number(m)).padStart(2, "0")}`;
        },

        getCurrentModalAwal(y = this.recapYear, m = this.recapMonth) {
            const key = this.getModalAwalKey(y, m);
            return Math.max(0, Number(this.modalAwalByPeriod?.[key] || 0));
        },

        openModalAwalEditor() {
            this.modalAwalDraft = this.getCurrentModalAwal();
            this.modalAwalOpen = true;
        },

        saveCurrentModalAwal() {
            const key = this.getModalAwalKey();
            const nominal = Math.max(0, Number(this.modalAwalDraft || 0));
            this.modalAwalByPeriod = {
                ...(this.modalAwalByPeriod || {}),
                [key]: nominal,
            };
            localStorage.setItem(
                "sm_modal_awal_v1",
                JSON.stringify(this.modalAwalByPeriod),
            );
            this.modalAwalOpen = false;
            this.showToast(
                `Modal awal ${this.getMonthName(this.recapMonth)} ${this.recapYear} disimpan`,
            );
        },

        estimateItemHpp(item) {
            const qty = Math.max(1, Number(item?.qty || 1));
            const unitPrice = Number(item?.price || 0);
            const subtotal = Number(item?.subtotal ?? unitPrice * qty);

            const rawUnitHpp =
                item?.hpp ?? item?.cost_price ?? item?.unit_cost ?? null;
            if (
                rawUnitHpp !== null &&
                rawUnitHpp !== "" &&
                !Number.isNaN(Number(rawUnitHpp)) &&
                Number(rawUnitHpp) > 0
            ) {
                return Math.max(0, Number(rawUnitHpp) * qty);
            }

            const rawTotalHpp = item?.total_hpp ?? item?.hpp_subtotal ?? null;
            if (
                rawTotalHpp !== null &&
                rawTotalHpp !== "" &&
                !Number.isNaN(Number(rawTotalHpp)) &&
                Number(rawTotalHpp) > 0
            ) {
                return Math.max(0, Number(rawTotalHpp));
            }

            const posList = Array.isArray(window.__SM_POS_PRODUCTS__)
                ? window.__SM_POS_PRODUCTS__
                : [];
            const matchedProd = posList.find(
                (p) =>
                    (item?.product_id &&
                        Number(p.id) === Number(item.product_id)) ||
                    (item?.name &&
                        String(p.name || "").toLowerCase() ===
                            String(item.name).toLowerCase()),
            );
            if (matchedProd && Number(matchedProd.hpp || 0) > 0) {
                return Math.max(0, Number(matchedProd.hpp) * qty);
            }

            return Math.round(subtotal * this.defaultHppRatio);
        },

        estimateOrderHpp(order) {
            const items = Array.isArray(order?.items) ? order.items : [];
            if (items.length > 0) {
                return items.reduce(
                    (sum, it) => sum + this.estimateItemHpp(it),
                    0,
                );
            }
            return Math.round(Number(order?.total || 0) * this.defaultHppRatio);
        },

        isOrderCountedInRecap(o) {
            if (o.status === "dibatalkan") return false;
            if (this.recapBasis === "realized") {
                return o.status === "selesai" || o.payment_status === "paid";
            }
            return true;
        },

        getRecapSummary() {
            const all = Object.values(window.__SM_ORDERS__ || {});
            const m = Number(this.recapMonth);
            const y = Number(this.recapYear);

            const prevM = m === 1 ? 12 : m - 1;
            const prevY = m === 1 ? y - 1 : y;

            const monthOrders = all.filter(
                (o) =>
                    Number(o.created_year) === y &&
                    Number(o.created_month) === m,
            );
            const prevMonthOrders = all.filter(
                (o) =>
                    Number(o.created_year) === prevY &&
                    Number(o.created_month) === prevM,
            );

            const validOrders = monthOrders.filter((o) =>
                this.isOrderCountedInRecap(o),
            );
            const prevValidOrders = prevMonthOrders.filter((o) =>
                this.isOrderCountedInRecap(o),
            );

            const revenue = validOrders.reduce(
                (s, o) => s + Number(o.total || 0),
                0,
            );
            const prevRevenue = prevValidOrders.reduce(
                (s, o) => s + Number(o.total || 0),
                0,
            );

            const totalHpp = validOrders.reduce(
                (s, o) => s + this.estimateOrderHpp(o),
                0,
            );
            const grossProfit = revenue - totalHpp;
            const grossMarginPct =
                revenue > 0
                    ? ((grossProfit / revenue) * 100).toFixed(1)
                    : "0.0";
            const modalAwal = this.getCurrentModalAwal(y, m);
            const modalPlusGrossProfit = modalAwal + grossProfit;

            const growthPct =
                prevRevenue > 0
                    ? Math.round(((revenue - prevRevenue) / prevRevenue) * 100)
                    : revenue > 0
                      ? 100
                      : 0;

            const completedCount = monthOrders.filter(
                (o) => o.status === "selesai",
            ).length;
            const cancelledCount = monthOrders.filter(
                (o) => o.status === "dibatalkan",
            ).length;
            const activeCount = monthOrders.filter((o) =>
                ["pending", "diproses", "dikirim"].includes(o.status),
            ).length;

            let totalItemsSold = 0;
            const productMap = {};
            const paymentMap = {};

            validOrders.forEach((o) => {
                const payKey = o.payment || "LAINNYA";
                paymentMap[payKey] = (paymentMap[payKey] || 0) + 1;

                (o.items || []).forEach((it) => {
                    const q = Number(it.qty || 1);
                    const sub = Number(
                        it.subtotal || Number(it.price || 0) * q,
                    );
                    const itemHpp = this.estimateItemHpp(it);
                    const itemProfit = sub - itemHpp;
                    totalItemsSold += q;

                    if (!productMap[it.name]) {
                        productMap[it.name] = {
                            name: it.name,
                            image: it.image,
                            qty: 0,
                            revenue: 0,
                            hpp: 0,
                            profit: 0,
                        };
                    }
                    productMap[it.name].qty += q;
                    productMap[it.name].revenue += sub;
                    productMap[it.name].hpp += itemHpp;
                    productMap[it.name].profit += itemProfit;
                });
            });

            const topProducts = Object.values(productMap)
                .sort((a, b) => b.qty - a.qty || b.revenue - a.revenue)
                .slice(0, 5);

            const avgOrderValue =
                validOrders.length > 0
                    ? Math.round(revenue / validOrders.length)
                    : 0;

            const daysInMonth = new Date(y, m, 0).getDate();
            const dailyRows = [];
            for (let d = 1; d <= daysInMonth; d++) {
                const dayAll = monthOrders.filter(
                    (o) => Number(o.created_day) === d,
                );
                const dayValid = dayAll.filter((o) =>
                    this.isOrderCountedInRecap(o),
                );
                const dayCancel = dayAll.filter(
                    (o) => o.status === "dibatalkan",
                ).length;
                const dayRev = dayValid.reduce(
                    (s, o) => s + Number(o.total || 0),
                    0,
                );
                const dayHpp = dayValid.reduce(
                    (s, o) => s + this.estimateOrderHpp(o),
                    0,
                );
                const dayGrossProfit = dayRev - dayHpp;
                let dayItems = 0;
                dayValid.forEach((o) =>
                    (o.items || []).forEach(
                        (it) => (dayItems += Number(it.qty || 1)),
                    ),
                );

                if (dayAll.length > 0) {
                    const dateLabel = `${String(d).padStart(2, "0")} ${this.getMonthName(m)} ${y}`;
                    dailyRows.push({
                        day: d,
                        dateLabel,
                        totalTrx: dayAll.length,
                        validTrx: dayValid.length,
                        cancelTrx: dayCancel,
                        itemsSold: dayItems,
                        revenue: dayRev,
                        hpp: dayHpp,
                        grossProfit: dayGrossProfit,
                    });
                }
            }

            return {
                totalAllOrders: monthOrders.length,
                validCount: validOrders.length,
                completedCount,
                activeCount,
                cancelledCount,
                revenue,
                prevRevenue,
                prevMonthLabel: `${this.getMonthName(prevM)} ${prevY}`,
                growthPct,
                modalAwal,
                totalHpp,
                grossProfit,
                grossMarginPct,
                modalPlusGrossProfit,
                totalItemsSold,
                avgOrderValue,
                topProducts,
                paymentMap,
                dailyRows,
                daysInMonth,
            };
        },

        renderRecapCharts() {
            if (typeof Chart === "undefined") return;

            const all = Object.values(window.__SM_ORDERS__ || {});
            const m = Number(this.recapMonth);
            const y = Number(this.recapYear);
            const summary = this.getRecapSummary();

            const dailyCanvas = document.getElementById("recapDailyChart");
            if (dailyCanvas) {
                const labels = [];
                const revData = [];
                const profitData = [];
                const countData = [];

                for (let d = 1; d <= summary.daysInMonth; d++) {
                    labels.push(String(d).padStart(2, "0"));
                    const dayOrders = all.filter(
                        (o) =>
                            Number(o.created_year) === y &&
                            Number(o.created_month) === m &&
                            Number(o.created_day) === d &&
                            this.isOrderCountedInRecap(o),
                    );
                    const dRev = dayOrders.reduce(
                        (s, o) => s + Number(o.total || 0),
                        0,
                    );
                    const dHpp = dayOrders.reduce(
                        (s, o) => s + this.estimateOrderHpp(o),
                        0,
                    );
                    revData.push(dRev);
                    profitData.push(dRev - dHpp);
                    countData.push(dayOrders.length);
                }

                if (window.__SM_RECAP_CHARTS__.daily) {
                    window.__SM_RECAP_CHARTS__.daily.destroy();
                }

                window.__SM_RECAP_CHARTS__.daily = new Chart(dailyCanvas, {
                    type: "bar",
                    data: {
                        labels,
                        datasets: [
                            {
                                label: "Omzet Harian (Rp)",
                                data: revData,
                                backgroundColor: "rgba(220, 38, 38, 0.85)",
                                hoverBackgroundColor: "rgba(185, 28, 28, 1)",
                                borderRadius: 6,
                                yAxisID: "y",
                            },
                            {
                                label: "Laba Kotor Harian (Rp)",
                                data: profitData,
                                backgroundColor: "rgba(16, 185, 129, 0.82)",
                                hoverBackgroundColor: "rgba(5, 150, 105, 1)",
                                borderRadius: 6,
                                yAxisID: "y",
                            },
                            {
                                type: "line",
                                label: "Jumlah Transaksi",
                                data: countData,
                                borderColor: "#0f172a",
                                backgroundColor: "#0f172a",
                                borderWidth: 2,
                                tension: 0.35,
                                pointRadius: 3,
                                yAxisID: "y1",
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: {
                                position: "top",
                                labels: { font: { size: 11, weight: "bold" } },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        if (ctx.datasetIndex === 0) {
                                            return (
                                                " Omzet: Rp " +
                                                Number(
                                                    ctx.raw || 0,
                                                ).toLocaleString("id-ID")
                                            );
                                        }
                                        if (ctx.datasetIndex === 1) {
                                            return (
                                                " Laba Kotor: Rp " +
                                                Number(
                                                    ctx.raw || 0,
                                                ).toLocaleString("id-ID")
                                            );
                                        }
                                        return (
                                            " Transaksi: " + ctx.raw + " order"
                                        );
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: "#f1f5f9" },
                                ticks: {
                                    font: { size: 10 },
                                    callback: (v) =>
                                        "Rp " +
                                        (v >= 1000000
                                            ? v / 1000000 + "jt"
                                            : v >= 1000
                                              ? v / 1000 + "rb"
                                              : v),
                                },
                            },
                            y1: {
                                beginAtZero: true,
                                position: "right",
                                grid: { drawOnChartArea: false },
                                ticks: { stepSize: 1, font: { size: 10 } },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } },
                            },
                        },
                    },
                });
            }

            const annualCanvas = document.getElementById("recapAnnualChart");
            if (annualCanvas) {
                const monthLabels = [
                    "Jan",
                    "Feb",
                    "Mar",
                    "Apr",
                    "Mei",
                    "Jun",
                    "Jul",
                    "Agu",
                    "Sep",
                    "Okt",
                    "Nov",
                    "Des",
                ];
                const monthlyRevs = [];
                const bgColors = [];

                for (let idx = 1; idx <= 12; idx++) {
                    const mOrders = all.filter(
                        (o) =>
                            Number(o.created_year) === y &&
                            Number(o.created_month) === idx &&
                            this.isOrderCountedInRecap(o),
                    );
                    monthlyRevs.push(
                        mOrders.reduce((s, o) => s + Number(o.total || 0), 0),
                    );
                    bgColors.push(idx === m ? "#dc2626" : "#cbd5e1");
                }

                if (window.__SM_RECAP_CHARTS__.annual) {
                    window.__SM_RECAP_CHARTS__.annual.destroy();
                }

                window.__SM_RECAP_CHARTS__.annual = new Chart(annualCanvas, {
                    type: "bar",
                    data: {
                        labels: monthLabels,
                        datasets: [
                            {
                                label: "Omzet Bulanan " + y,
                                data: monthlyRevs,
                                backgroundColor: bgColors,
                                borderRadius: 8,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) =>
                                        " Omzet: Rp " +
                                        Number(ctx.raw || 0).toLocaleString(
                                            "id-ID",
                                        ),
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: "#f1f5f9" },
                                ticks: {
                                    font: { size: 10 },
                                    callback: (v) =>
                                        "Rp " +
                                        (v >= 1000000
                                            ? v / 1000000 + "jt"
                                            : v >= 1000
                                              ? v / 1000 + "rb"
                                              : v),
                                },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11, weight: "bold" } },
                            },
                        },
                    },
                });
            }

            const payCanvas = document.getElementById("recapPaymentChart");
            if (payCanvas) {
                const payLabels = Object.keys(summary.paymentMap);
                const payValues = Object.values(summary.paymentMap);

                if (window.__SM_RECAP_CHARTS__.payment) {
                    window.__SM_RECAP_CHARTS__.payment.destroy();
                }

                window.__SM_RECAP_CHARTS__.payment = new Chart(payCanvas, {
                    type: "doughnut",
                    data: {
                        labels: payLabels.length
                            ? payLabels
                            : ["Belum ada data"],
                        datasets: [
                            {
                                data: payValues.length ? payValues : [1],
                                backgroundColor: payLabels.length
                                    ? [
                                          "#0f172a",
                                          "#dc2626",
                                          "#2563eb",
                                          "#10b981",
                                          "#f59e0b",
                                          "#8b5cf6",
                                      ]
                                    : ["#e2e8f0"],
                                borderWidth: 2,
                                borderColor: "#ffffff",
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: "66%",
                        plugins: {
                            legend: {
                                position: "bottom",
                                labels: {
                                    boxWidth: 12,
                                    font: { size: 11, weight: "bold" },
                                },
                            },
                        },
                    },
                });
            }
        },

        exportMonthlyRecapCsv() {
            const summary = this.getRecapSummary();
            if (!summary.dailyRows.length) {
                this.showToast(
                    "Tidak ada data transaksi pada bulan ini untuk diekspor",
                );
                return;
            }

            const headers = [
                "Tanggal",
                "Total Order Masuk",
                "Order Valid / Lunas",
                "Order Dibatalkan",
                "Total Produk Terjual (Pcs)",
                "Total Omzet (Rp)",
                "Total HPP (Rp)",
                "Laba Kotor (Rp)",
            ];
            const esc = (v) => '"' + String(v ?? "-").replace(/"/g, '""') + '"';

            const rows = summary.dailyRows.map((r) =>
                [
                    esc(r.dateLabel),
                    esc(r.totalTrx),
                    esc(r.validTrx),
                    esc(r.cancelTrx),
                    esc(r.itemsSold),
                    esc(Math.round(r.revenue).toLocaleString("id-ID")),
                    esc(Math.round(r.hpp).toLocaleString("id-ID")),
                    esc(Math.round(r.grossProfit).toLocaleString("id-ID")),
                ].join(";"),
            );

            rows.push(
                [
                    esc(
                        "MODAL AWAL " +
                            this.getMonthName(this.recapMonth).toUpperCase() +
                            " " +
                            this.recapYear,
                    ),
                    esc("-"),
                    esc("-"),
                    esc("-"),
                    esc("-"),
                    esc(Math.round(summary.modalAwal).toLocaleString("id-ID")),
                    esc("-"),
                    esc("-"),
                ].join(";"),
            );

            rows.push(
                [
                    esc(
                        "TOTAL BULAN " +
                            this.getMonthName(this.recapMonth).toUpperCase() +
                            " " +
                            this.recapYear,
                    ),
                    esc(summary.totalAllOrders),
                    esc(summary.validCount),
                    esc(summary.cancelledCount),
                    esc(summary.totalItemsSold),
                    esc(Math.round(summary.revenue).toLocaleString("id-ID")),
                    esc(Math.round(summary.totalHpp).toLocaleString("id-ID")),
                    esc(
                        Math.round(summary.grossProfit).toLocaleString("id-ID"),
                    ),
                ].join(";"),
            );

            const csvContent =
                "\uFEFFsep=;\n" +
                [headers.map(esc).join(";"), ...rows].join("\n");
            const blob = new Blob([csvContent], {
                type: "text/csv;charset=utf-8;",
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `Rekap_Bulanan_SportMania_${this.getMonthName(this.recapMonth)}_${this.recapYear}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            this.showToast(
                `Rekap ${this.getMonthName(this.recapMonth)} ${this.recapYear} berhasil diunduh`,
            );
        },

        parseOrderDate(dateStr) {
            if (!dateStr || dateStr === "-") return null;
            const months = {
                jan: 0,
                januari: 0,
                feb: 1,
                februari: 1,
                mar: 2,
                maret: 2,
                apr: 3,
                april: 3,
                mei: 4,
                may: 4,
                jun: 5,
                juni: 5,
                jul: 6,
                juli: 6,
                agu: 7,
                agustus: 7,
                aug: 7,
                sep: 8,
                september: 8,
                okt: 9,
                oktober: 9,
                oct: 9,
                nov: 10,
                november: 10,
                des: 11,
                desember: 11,
                dec: 11,
            };
            const match = String(dateStr)
                .trim()
                .match(
                    /^(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})(?:,\s*(\d{1,2}):(\d{2}))?/,
                );
            if (!match) return null;
            const day = parseInt(match[1], 10);
            const month = months[match[2].toLowerCase()];
            const year = parseInt(match[3], 10);
            if (month === undefined) return null;
            const hour = match[4] ? parseInt(match[4], 10) : 0;
            const minute = match[5] ? parseInt(match[5], 10) : 0;
            return new Date(year, month, day, hour, minute, 0);
        },

        getTimeLabelText(dateStr) {
            const dt = this.parseOrderDate(dateStr);
            if (!dt || isNaN(dt.getTime())) return dateStr || "-";

            const now = new Date();
            const todayStart = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
            );
            const yesterdayStart = new Date(todayStart);
            yesterdayStart.setDate(yesterdayStart.getDate() - 1);
            const itemStart = new Date(
                dt.getFullYear(),
                dt.getMonth(),
                dt.getDate(),
            );

            if (itemStart.getTime() === todayStart.getTime()) return "Hari Ini";
            if (itemStart.getTime() === yesterdayStart.getTime())
                return "Kemarin";
            return dt.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        matchTimeFilter(dateStr, preset, dateFrom, dateUntil) {
            const dt = this.parseOrderDate(dateStr);
            if (!dt || isNaN(dt.getTime())) return true;

            const now = new Date();
            const todayStart = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
            );
            const todayEnd = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
                23,
                59,
                59,
                999,
            );
            const yesterdayStart = new Date(todayStart);
            yesterdayStart.setDate(yesterdayStart.getDate() - 1);
            const yesterdayEnd = new Date(
                yesterdayStart.getFullYear(),
                yesterdayStart.getMonth(),
                yesterdayStart.getDate(),
                23,
                59,
                59,
                999,
            );

            if (preset === "today") {
                if (dt < todayStart || dt > todayEnd) return false;
            } else if (preset === "yesterday") {
                if (dt < yesterdayStart || dt > yesterdayEnd) return false;
            } else if (preset === "7days") {
                const sevenDaysAgo = new Date(todayStart);
                sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);
                if (dt < sevenDaysAgo || dt > todayEnd) return false;
            } else if (preset === "this_month") {
                const monthStart = new Date(
                    now.getFullYear(),
                    now.getMonth(),
                    1,
                );
                if (dt < monthStart || dt > todayEnd) return false;
            }

            if (dateFrom) {
                const [y, m, d] = dateFrom.split("-").map(Number);
                const fromDt = new Date(y, m - 1, d, 0, 0, 0, 0);
                if (dt < fromDt) return false;
            }

            if (dateUntil) {
                const [y, m, d] = dateUntil.split("-").map(Number);
                const untilDt = new Date(y, m - 1, d, 23, 59, 59, 999);
                if (dt > untilDt) return false;
            }

            return true;
        },

        getFilteredOrdersList() {
            const allOrders = Object.values(window.__SM_ORDERS__ || {});
            const q = (this.searchOrder || "").toLowerCase().trim();

            return allOrders.filter((o) => {
                const matchStatus =
                    this.orderFilter === "semua" ||
                    (this.orderFilter === "verifikasi" &&
                        o.payment_status === "waiting_verification" &&
                        o.status !== "dibatalkan") ||
                    o.status === this.orderFilter;

                const matchSearch =
                    !q || String(o.order_haystack || "").includes(q);
                const matchTime = this.matchTimeFilter(
                    o.date,
                    this.orderTimeFilter,
                    this.orderDateFrom,
                    this.orderDateUntil,
                );

                return matchStatus && matchSearch && matchTime;
            });
        },

        getFilteredHistoryList() {
            const allOrders = Object.values(window.__SM_ORDERS__ || {});
            const finalized = allOrders.filter((o) =>
                ["selesai", "dibatalkan"].includes(o.status),
            );
            const q = (this.searchHistory || "").toLowerCase().trim();

            return finalized.filter((o) => {
                const matchStatus =
                    this.historyFilter === "semua" ||
                    o.status === this.historyFilter;
                const matchSearch =
                    !q || String(o.history_haystack || "").includes(q);
                const matchTime = this.matchTimeFilter(
                    o.updated_date || o.date,
                    this.historyTimeFilter,
                    this.historyDateFrom,
                    this.historyDateUntil,
                );

                return matchStatus && matchSearch && matchTime;
            });
        },

        sumOrdersNominal(list) {
            return (list || []).reduce(
                (acc, item) => acc + Number(item.total || 0),
                0,
            );
        },

        openConfirm({
            title,
            message,
            confirmText = "Ya, Lanjutkan",
            variant = "danger",
            formId,
            onCancel = null,
        }) {
            this.confirmDialog = {
                open: true,
                title,
                message,
                confirmText,
                variant,
                formId,
                onCancel,
            };
        },

        closeConfirm() {
            if (typeof this.confirmDialog.onCancel === "function") {
                this.confirmDialog.onCancel();
            }
            this.confirmDialog.open = false;
            this.confirmDialog.onCancel = null;
        },

        executeConfirm() {
            const targetForm = document.getElementById(
                this.confirmDialog.formId,
            );
            this.confirmDialog.open = false;
            this.confirmDialog.onCancel = null;
            if (targetForm) {
                if (this.confirmDialog.formId === "admin-logout-form") {
                    sessionStorage.removeItem("sm_admin_tab");
                }
                targetForm.submit();
            }
        },

        handleQuickStatusChange(selectEl, orderId, currentStatus) {
            const nextVal = selectEl.value;
            if (nextVal === "dibatalkan") {
                this.openConfirm({
                    title: "Batalkan Pesanan Ini?",
                    message:
                        "Pesanan akan dibatalkan dan stok varian produk akan otomatis dikembalikan ke etalase.",
                    confirmText: "Ya, Batalkan Pesanan",
                    variant: "danger",
                    formId: "quick-status-form-" + orderId,
                    onCancel: () => {
                        selectEl.value = currentStatus;
                    },
                });
                return;
            }
            selectEl.form.submit();
        },

        openOrderModal(orderId) {
            const data = window.__SM_ORDERS__[orderId];
            if (data) {
                this.rejectFormOpen = false;
                this.rejectReasonInput = data.payment_rejected_reason || "";
                this.selectedOrder = JSON.parse(JSON.stringify(data));
            }
        },

        openProofLightbox(url, code, orderId = null) {
            if (!url) return;
            this.proofLightbox = {
                open: true,
                url: url,
                code: code || "",
                orderId: orderId,
            };
        },

        copyText(text, label = "Tersalin ke clipboard") {
            if (!text || text === "-") return;
            navigator.clipboard.writeText(text);
            this.showToast(label);
        },

        copyShippingInfo(order) {
            if (!order) return;
            const itemsText = (order.items || [])
                .map(
                    (i) =>
                        `- ${i.name} (${i.size}${i.color && i.color !== "-" ? "/" + i.color : ""}) x${i.qty}`,
                )
                .join("\n");
            const formatted = [
                `ORDER #${order.code} (${order.shipping_method})`,
                `Penerima: ${order.customer}`,
                `No. HP: ${order.phone}`,
                `Alamat: ${order.address}${order.city && order.city !== "-" ? ", " + order.city : ""}`,
                itemsText ? `Item:\n${itemsText}` : "",
            ]
                .filter(Boolean)
                .join("\n");
            this.copyText(formatted, "Format alamat & resi berhasil disalin");
        },

        exportOrdersCsv(onlyFinalized = false) {
            const filtered = onlyFinalized
                ? this.getFilteredHistoryList()
                : this.getFilteredOrdersList();

            if (filtered.length === 0) {
                this.showToast(
                    "Tidak ada data transaksi sesuai filter untuk diekspor",
                );
                return;
            }

            const cleanCell = (val) =>
                String(val ?? "-")
                    .replace(/[\r\n]+/g, " ")
                    .replace(/\s+/g, " ")
                    .trim() || "-";

            const formatPhoneForExcel = (phone) => {
                const cleaned = cleanCell(phone);
                if (cleaned === "-") return "-";
                return `="${cleaned.replace(/"/g, '""')}"`;
            };

            const formatPaymentStatusLabel = (status) => {
                const st = String(status || "unpaid").toLowerCase();
                if (st === "paid") return "Lunas";
                if (st === "waiting_verification") return "Menunggu Verifikasi";
                if (st === "rejected") return "Bukti Ditolak";
                return "Belum Bayar";
            };

            const formatNominal = (amount) => {
                const num = Math.round(Number(amount || 0));
                return num.toLocaleString("id-ID");
            };

            const extractCityFromAddress = (addressText) => {
                if (!addressText || addressText === "-") return "-";

                const knownRegions = [
                    "NTB",
                    "NTT",
                    "Malang",
                    "Bojonegoro",
                    "Surabaya",
                    "Batu",
                    "Kediri",
                    "Blitar",
                    "Jember",
                    "Banyuwangi",
                    "Sidoarjo",
                    "Pasuruan",
                    "Probolinggo",
                    "Madiun",
                    "Mojokerto",
                    "Tuban",
                    "Lamongan",
                    "Nganjuk",
                    "Tulungagung",
                    "Trenggalek",
                    "Ponorogo",
                    "Ngawi",
                    "Magetan",
                    "Pacitan",
                    "Lumajang",
                    "Bondowoso",
                    "Situbondo",
                    "Gresik",
                    "Bangkalan",
                    "Sampang",
                    "Pamekasan",
                    "Sumenep",
                    "Jakarta",
                    "Bandung",
                    "Yogyakarta",
                    "Semarang",
                    "Solo",
                    "Surakarta",
                    "Denpasar",
                    "Bali",
                    "Medan",
                    "Makassar",
                    "Balikpapan",
                ];

                for (const region of knownRegions) {
                    const regex = new RegExp(
                        `\\b${region.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\b`,
                        "i",
                    );
                    if (regex.test(addressText)) {
                        return region === region.toUpperCase()
                            ? region.toUpperCase()
                            : region;
                    }
                }

                const regencyMatch = addressText.match(
                    /\b(?:kota|kabupaten|kab\.?)\s+([a-zA-Z\s]{3,25})(?:,|;|\d|$)/i,
                );
                if (regencyMatch && regencyMatch[1]) {
                    return regencyMatch[1].trim();
                }

                return "-";
            };

            const resolveCityAndAddress = (order) => {
                const rawAddress = cleanCell(order.address);
                const rawCity = cleanCell(order.city);
                const shipRaw = String(order.shipping_raw || "").toLowerCase();
                const shipMethod = String(
                    order.shipping_method || "",
                ).toUpperCase();

                const isPickup =
                    shipRaw === "pickup" ||
                    shipMethod.includes("STORE") ||
                    /\bambil\s*langsung\b/i.test(rawAddress);

                if (isPickup) {
                    const kota = rawCity !== "-" ? rawCity : "Malang";
                    const isDefaultPickupText =
                        rawAddress === "-" ||
                        /^ambil\s*langsung(\s*di\s*store(\s*malang)?)?$/i.test(
                            rawAddress,
                        );

                    const pengambilan = isDefaultPickupText
                        ? "Ambil Langsung di Store Malang"
                        : `Ambil Langsung di Store Malang (${rawAddress})`;

                    return {
                        kota,
                        alamatPengambilan: pengambilan,
                    };
                }

                const resolvedCity =
                    rawCity !== "-"
                        ? rawCity
                        : extractCityFromAddress(rawAddress);

                return {
                    kota: resolvedCity,
                    alamatPengambilan: rawAddress,
                };
            };

            const headers = [
                "Kode Order",
                "Tanggal Order",
                "Tanggal Update",
                "Pelanggan",
                "Telepon",
                "Kota",
                "Alamat / Pengambilan",
                "Metode Bayar",
                "Status Bayar",
                "Ekspedisi",
                "No Resi",
                "Status Pesanan",
                "Total Pembayaran (Rp)",
            ];

            const escapeRawCsv = (val) =>
                '"' + cleanCell(val).replace(/"/g, '""') + '"';

            const rows = filtered.map((o) => {
                const { kota, alamatPengambilan } = resolveCityAndAddress(o);
                const phoneCell = formatPhoneForExcel(o.phone);
                const formattedPhone = phoneCell.startsWith('="')
                    ? '"' + phoneCell.replace(/"/g, '""') + '"'
                    : escapeRawCsv(phoneCell);

                const cells = [
                    escapeRawCsv(o.code),
                    escapeRawCsv(o.date || "-"),
                    escapeRawCsv(o.updated_date || o.date || "-"),
                    escapeRawCsv(o.customer),
                    formattedPhone,
                    escapeRawCsv(kota),
                    escapeRawCsv(alamatPengambilan),
                    escapeRawCsv(String(o.payment || "-").toUpperCase()),
                    escapeRawCsv(formatPaymentStatusLabel(o.payment_status)),
                    escapeRawCsv(
                        String(o.shipping_method || "-").toUpperCase(),
                    ),
                    escapeRawCsv(o.tracking_number || "-"),
                    escapeRawCsv(String(o.status || "-").toUpperCase()),
                    escapeRawCsv(formatNominal(o.total)),
                ];

                return cells.join(";");
            });

            const csvContent =
                "\uFEFFsep=;\n" +
                [headers.map(escapeRawCsv).join(";"), ...rows].join("\n");
            const blob = new Blob([csvContent], {
                type: "text/csv;charset=utf-8;",
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            const dateTag = new Date().toISOString().slice(0, 10);
            link.href = url;
            link.download = `SportMania_${onlyFinalized ? "Riwayat_Transaksi" : "Laporan_Pesanan"}_${dateTag}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            this.showToast(
                `Berhasil mengunduh ${filtered.length} data pesanan sesuai filter (CSV)`,
            );
        },

        triggerPrint(orderObj, mode = "invoice") {
            if (!orderObj) return;
            this.printOrder = JSON.parse(JSON.stringify(orderObj));
            this.printMode = mode;
            this.$nextTick(() => {
                setTimeout(() => window.print(), 100);
            });
        },

        printOrderById(orderId, mode = "invoice") {
            const data = window.__SM_ORDERS__[orderId];
            if (data) {
                this.triggerPrint(data, mode);
            }
        },
    };
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("adminPanel", window.adminPanel);
});
