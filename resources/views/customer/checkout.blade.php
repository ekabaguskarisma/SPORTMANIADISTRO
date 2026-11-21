<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pesanan | Sport Mania Distro</title>
    
    <!-- Tailwind CDN, Font Inter & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #fafafa; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#FAFAFA] text-gray-900 antialiased selection:bg-red-500 selection:text-white flex flex-col min-h-screen"
      x-data="{
          cartItems: (JSON.parse(localStorage.getItem('cart_items')) || []).map(item => ({
              ...item,
              size: item.size || 'All Size',
              color: item.color || '-',
              qty: Math.max(1, Number(item.qty || 1))
          })),
          isLoading: false,
          qrisZoom: false,
          proofFile: null,
          proofPreview: null,

          // State Voucher Promo
          voucherInput: '',
          appliedVoucher: null,
          voucherDiscount: 0,
          voucherMessage: '',
          voucherError: '',
          isCheckingVoucher: false,

          // State Modal Sukses Order
          orderSuccess: {
              show: false,
              orderNumber: '',
              waUrl: '',
              totalFormatted: '',
              popupBlocked: false
          },
          
          // Toast Notification State
          toast: {
              show: false,
              message: '',
              type: 'error'
          },

          syncCart() {
              const raw = JSON.parse(localStorage.getItem('cart_items')) || [];
              this.cartItems = raw.map(item => ({
                  ...item,
                  size: item.size || 'All Size',
                  color: item.color || '-',
                  qty: Math.max(1, Number(item.qty || 1))
              }));
              if (this.appliedVoucher) {
                  this.applyVoucher(true);
              }
          },

          showToast(message, type = 'error') {
              this.toast.message = message;
              this.toast.type = type;
              this.toast.show = true;
              setTimeout(() => { this.toast.show = false; }, 4500);
          },

          async applyVoucher(silent = false) {
              const code = (silent && this.appliedVoucher ? this.appliedVoucher : this.voucherInput).trim().toUpperCase();
              if (!code) {
                  this.voucherError = 'Masukkan kode voucher terlebih dahulu.';
                  return;
              }
              if (this.subtotal <= 0) {
                  this.voucherError = 'Keranjang belanja masih kosong.';
                  return;
              }

              this.isCheckingVoucher = true;
              this.voucherError = '';
              if (!silent) this.voucherMessage = '';

              try {
                  const res = await fetch('{{ route('voucher.apply') }}', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json',
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Accept': 'application/json'
                      },
                      body: JSON.stringify({
                          code: code,
                          subtotal: this.subtotal
                      })
                  });

                  const data = await res.json();

                  if (res.ok && (data.valid || data.success)) {
                      this.appliedVoucher = data.code || code;
                      this.voucherInput = this.appliedVoucher;
                      this.voucherDiscount = Math.min(this.subtotal, Number(data.discount || data.discount_amount || 0));
                      this.voucherMessage = data.message || `Voucher ${this.appliedVoucher} berhasil diterapkan!`;
                      this.voucherError = '';
                      if (!silent) {
                          this.showToast(this.voucherMessage, 'success');
                      }
                  } else {
                      this.appliedVoucher = null;
                      this.voucherDiscount = 0;
                      this.voucherMessage = '';
                      this.voucherError = data.message || 'Kode voucher tidak valid atau tidak memenuhi syarat.';
                      if (!silent) {
                          this.showToast(this.voucherError, 'error');
                      }
                  }
              } catch (err) {
                  this.voucherError = 'Gagal mengecek kode voucher. Periksa koneksi internet.';
                  if (!silent) {
                      this.showToast(this.voucherError, 'error');
                  }
              } finally {
                  this.isCheckingVoucher = false;
              }
          },

          removeVoucher() {
              this.appliedVoucher = null;
              this.voucherInput = '';
              this.voucherDiscount = 0;
              this.voucherMessage = '';
              this.voucherError = '';
              this.showToast('Kode voucher dihapus.', 'success');
          },

          handleProofUpload(event) {
              const file = event.target.files[0];
              if (!file) return;

              const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
              if (!allowedTypes.includes(file.type)) {
                  this.showToast('Format foto harus JPG, PNG, atau WEBP.', 'error');
                  event.target.value = '';
                  return;
              }
              if (file.size > 4 * 1024 * 1024) {
                  this.showToast('Ukuran foto bukti bayar maksimal 4MB.', 'error');
                  event.target.value = '';
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
                  this.$refs.proofInput.value = '';
              }
          },

          copyRekening(text) {
              navigator.clipboard.writeText(text);
              this.showToast('Nomor rekening berhasil disalin: ' + text, 'success');
          },
          
          form: {
              name: @js(Auth::user()->name ?? ''),
              phone: @js($defaultPhone ?? (Auth::user()->phone ?? '')),
              email: @js(Auth::user()->email ?? ''),
              address: @js($defaultAddress ?? (Auth::user()->address ?? '')),
              shippingMethod: (() => {
                  const saved = localStorage.getItem('selected_courier') || 'jne';
                  if (saved === 'SiCepat BEST' || saved === 'sicepat') return 'sicepat';
                  if (saved === 'Ambil di Store' || saved === 'pickup') return 'pickup';
                  return 'jne';
              })(),
              paymentMethod: 'transfer',
              notes: ''
          },

          setShippingMethod(method) {
              this.form.shippingMethod = method;
              localStorage.setItem('selected_courier', method);
          },

          get totalItems() {
              return this.cartItems.reduce((acc, item) => acc + Number(item.qty || 0), 0);
          },

          get subtotal() {
              return this.cartItems.reduce((acc, item) => acc + (Number(item.price || 0) * Number(item.qty || 0)), 0);
          },

          get effectiveDiscount() {
              return Math.min(this.subtotal, Number(this.voucherDiscount || 0));
          },

          get shippingCost() {
              if (this.form.shippingMethod === 'pickup' || this.subtotal === 0) return 0;
              if (this.subtotal >= 250000) return 0;
              return this.form.shippingMethod === 'sicepat' ? 25000 : 20000;
          },

          get total() {
              return Math.max(0, this.subtotal - this.effectiveDiscount + this.shippingCost);
          },

          async processCheckout() {
              if (this.isLoading) return;

              if (this.cartItems.length === 0) {
                  this.showToast('Keranjang belanja Anda masih kosong!', 'error');
                  return;
              }
              if (!this.form.name.trim()) {
                  this.showToast('Nama lengkap penerima wajib diisi.', 'error');
                  return;
              }
              
              // Validasi Nomor WhatsApp / HP
              const phoneRegex = /^\+?[0-9\s\-\(\)]{8,20}$/;
              if (!phoneRegex.test(this.form.phone.trim())) {
                  this.showToast('Format nomor WhatsApp tidak valid! Masukkan 8 hingga 20 digit angka (boleh diawali tanda +).', 'error');
                  return;
              }

              if (this.form.shippingMethod !== 'pickup' && !this.form.address.trim()) {
                  this.showToast('Alamat lengkap pengiriman wajib diisi.', 'error');
                  return;
              }

              this.isLoading = true;

              const itemsSnapshot = this.cartItems.map(item => ({
                  ...item,
                  size: item.size || 'All Size',
                  color: item.color || '-',
                  qty: Math.max(1, Number(item.qty || 1))
              }));
              const subtotalSnapshot = this.subtotal;
              const discountSnapshot = this.effectiveDiscount;
              const voucherCodeSnapshot = this.appliedVoucher || '';
              const shippingSnapshot = this.shippingCost;
              const totalSnapshot = this.total;

              try {
                  const formData = new FormData();
                  formData.append('name', this.form.name.trim());
                  formData.append('phone', this.form.phone.trim());
                  formData.append('email', this.form.email.trim());
                  formData.append('address', this.form.shippingMethod === 'pickup' ? 'Ambil di Store Malang' : this.form.address.trim());
                  formData.append('shipping_method', this.form.shippingMethod);
                  formData.append('payment_method', this.form.paymentMethod);
                  formData.append('notes', this.form.notes.trim());
                  formData.append('subtotal', subtotalSnapshot);
                  formData.append('discount_amount', discountSnapshot);
                  if (voucherCodeSnapshot) {
                      formData.append('voucher_code', voucherCodeSnapshot);
                  }
                  formData.append('shipping_cost', shippingSnapshot);
                  formData.append('total', totalSnapshot);
                  formData.append('cart_items', JSON.stringify(itemsSnapshot));

                  if (this.proofFile && this.form.paymentMethod !== 'cod') {
                      formData.append('payment_proof', this.proofFile);
                  }

                  let response = await fetch('{{ route('checkout.process') }}', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Accept': 'application/json'
                      },
                      body: formData
                  });

                  let result = await response.json();

                  if (response.ok && result.success) {
                      const finalTotal = Number(result.total ?? totalSnapshot);
                      const finalDiscount = Number(result.discount_amount ?? discountSnapshot);

                      let detailPesanan = itemsSnapshot.map((item, index) => 
                          `*${index + 1}. ${item.name}* (${item.size}, ${item.color}) - ${item.qty}x @ Rp ${Number(item.price).toLocaleString('id-ID')}`
                      ).join('\n');

                      let statusBukti = (this.proofFile && this.form.paymentMethod !== 'cod')
                          ? '✅ Sudah diunggah saat checkout'
                          : (this.form.paymentMethod === 'cod' ? '💵 Bayar di Tempat (COD)' : '⏳ Menyusul di Riwayat Pesanan');

                      let rawTextWA = `*HALO ADMIN SPORT MANIA DISTRO*\nSaya ingin mengkonfirmasi pesanan baru dengan detail berikut:\n\n` +
                                      `🔖 *ORDER ID:* *${result.order_number}*\n` +
                                      `👤 *Nama:* ${this.form.name.trim()}\n` +
                                      `📞 *No. HP:* ${this.form.phone.trim()}\n` +
                                      `📍 *Pengiriman:* ${this.form.shippingMethod.toUpperCase()}\n` +
                                      (this.form.shippingMethod !== 'pickup' ? `🏠 *Alamat:* ${this.form.address.trim()}\n` : `🏢 *Catatan:* Ambil langsung di Store Malang\n`) +
                                      `💳 *Pembayaran:* ${this.form.paymentMethod.toUpperCase()} (${statusBukti})\n` +
                                      (this.form.notes.trim() ? `📝 *Catatan Pembeli:* ${this.form.notes.trim()}\n` : '') +
                                      `\n🛒 *Detail Produk:*\n${detailPesanan}\n\n` +
                                      `💰 *Subtotal:* Rp ${Number(subtotalSnapshot).toLocaleString('id-ID')}\n` +
                                      (finalDiscount > 0 ? `🎟️ *Voucher (${voucherCodeSnapshot}):* -Rp ${Number(finalDiscount).toLocaleString('id-ID')}\n` : '') +
                                      `📦 *Ongkir:* ${shippingSnapshot === 0 ? 'GRATIS' : 'Rp ' + Number(shippingSnapshot).toLocaleString('id-ID')}\n` +
                                      `🔥 *Total Tagihan:* *Rp ${Number(finalTotal).toLocaleString('id-ID')}*`;

                      const waLink = `https://wa.me/6282234548092?text=${encodeURIComponent(rawTextWA)}`;

                      localStorage.removeItem('cart_items');
                      this.cartItems = [];
                      this.appliedVoucher = null;
                      this.voucherInput = '';
                      this.voucherDiscount = 0;
                      window.dispatchEvent(new CustomEvent('cart-updated', { detail: [] }));

                      const waWin = window.open(waLink, '_blank');

                      this.orderSuccess = {
                          show: true,
                          orderNumber: result.order_number,
                          waUrl: waLink,
                          totalFormatted: 'Rp ' + Number(finalTotal).toLocaleString('id-ID'),
                          popupBlocked: !waWin
                      };

                      this.isLoading = false;
                      this.showToast('Pesanan berhasil disimpan!', 'success');

                  } else {
                      let errorMsg = result.message || 'Gagal memproses pesanan.';
                      if (result.errors) {
                          const firstField = Object.keys(result.errors)[0];
                          if (firstField && result.errors[firstField][0]) {
                              errorMsg = result.errors[firstField][0];
                          }
                      }
                      this.showToast(errorMsg, 'error');
                      this.isLoading = false;
                  }
              } catch (error) {
                  this.showToast('Terjadi kesalahan jaringan atau server.', 'error');
                  console.error(error);
                  this.isLoading = false;
              }
          }
      }"
      @keydown.escape.window="qrisZoom = false"
      @cart-updated.window="syncCart()"
      @storage.window="syncCart()"
      @pageshow.window="syncCart()">

    <!-- TOAST NOTIFICATION FLOATING BANNER -->
    <div class="fixed top-5 right-5 z-50 max-w-md w-full px-4 transition-all duration-300 transform pointer-events-none"
         x-show="toast.show"
         x-transition:enter="translate-y-[-20px] opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="opacity-100"
         x-transition:leave-end="opacity-0" x-cloak>
        <div class="flex items-center gap-3 p-4 rounded-2xl shadow-xl border text-xs font-bold pointer-events-auto"
             :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <span class="text-base" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
            <p class="flex-1" x-text="toast.message"></p>
        </div>
    </div>

    <!-- Top Navbar -->
    <nav class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex justify-between items-center">
            <a href="{{ route('home') }}" class="flex items-center gap-3 hover:opacity-80 transition">
                <div class="p-1 rounded-lg bg-gray-900 border border-gray-800 shadow-inner">
                    <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                </div>
                <span class="text-xl font-black tracking-tighter uppercase italic">
                    <span class="text-red-600">SPORT</span><span>MANIA</span>
                </span>
            </a>
            <div class="flex items-center gap-2 sm:gap-3">
                @if(Route::has('customer.orders'))
                    <a href="{{ route('customer.orders') }}" class="hidden sm:inline-flex text-xs font-bold uppercase tracking-wider text-gray-600 hover:text-red-600 transition px-3 py-2 rounded-lg">
                        Pesanan Saya
                    </a>
                @endif
                <a href="{{ route('customer.cart') }}" class="text-xs font-bold uppercase tracking-wider text-gray-700 hover:text-red-600 transition flex items-center gap-1.5 px-3 py-2 rounded-lg bg-gray-100">
                    ← Kembali ke Keranjang
                </a>
            </div>
        </div>
    </nav>

    <!-- Konten Utama Checkout -->
    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 flex-grow w-full">
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">Checkout <span class="text-red-600">Pesanan</span></h1>
            <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">Lengkapi detail pengiriman dan metode pembayaran pilihanmu.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- FORM INPUT DATA & PENGIRIMAN (KIRI) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- 1. Informasi Penerima -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-wider text-gray-900 border-b border-gray-100 pb-3">1. Informasi Penerima</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Lengkap</label>
                            <input type="text" x-model="form.name" placeholder="Nama penerima" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nomor WhatsApp / HP</label>
                            <input type="text" x-model="form.phone" placeholder="Contoh: 08123456789" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600">
                            <span class="text-[10px] text-gray-400 mt-1 block">Masukkan 8–20 digit angka aktif WhatsApp (boleh diawali +62).</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email (Opsional)</label>
                        <input type="email" x-model="form.email" placeholder="email@domain.com" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600">
                    </div>
                </div>

                <!-- 2. Metode Pengiriman -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-wider text-gray-900 border-b border-gray-100 pb-3">2. Metode Pengiriman</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label @click="setShippingMethod('jne')" 
                               :class="form.shippingMethod === 'jne' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">JNE REG</span>
                                <input type="radio" name="shipping" value="jne" x-model="form.shippingMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-medium text-gray-500" x-text="subtotal >= 250000 ? 'Gratis Ongkir (Min. 250rb)' : 'Estimasi 2-3 Hari (Rp 20.000)'"></span>
                        </label>

                        <label @click="setShippingMethod('sicepat')" 
                               :class="form.shippingMethod === 'sicepat' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">SiCepat BEST</span>
                                <input type="radio" name="shipping" value="sicepat" x-model="form.shippingMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-medium text-gray-500" x-text="subtotal >= 250000 ? 'Gratis Ongkir (Min. 250rb)' : 'Fast Delivery 1-2 Hari (Rp 25.000)'"></span>
                        </label>

                        <label @click="setShippingMethod('pickup')" 
                               :class="form.shippingMethod === 'pickup' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">Ambil di Store</span>
                                <input type="radio" name="shipping" value="pickup" x-model="form.shippingMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-bold text-green-600">Gratis (Store Malang)</span>
                        </label>
                    </div>

                    <!-- Alamat Diisi Jika Bukan Ambil di Tempat -->
                    <div x-show="form.shippingMethod !== 'pickup'" x-transition class="pt-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alamat Lengkap Pengiriman</label>
                        <textarea x-model="form.address" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW, kecamatan, kota..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600"></textarea>
                    </div>

                    <div class="pt-1">
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catatan Pesanan (Opsional)</label>
                        <input type="text" x-model="form.notes" placeholder="Contoh: Tolong bungkus rapi untuk kado..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600">
                    </div>
                </div>

                <!-- 3. Metode Pembayaran -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-wider text-gray-900 border-b border-gray-100 pb-3">3. Metode Pembayaran</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label @click="form.paymentMethod = 'transfer'" 
                               :class="form.paymentMethod === 'transfer' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">Transfer Bank</span>
                                <input type="radio" name="payment" value="transfer" x-model="form.paymentMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-medium text-gray-500">BCA / Mandiri / BNI</span>
                        </label>

                        <label @click="form.paymentMethod = 'qris'" 
                               :class="form.paymentMethod === 'qris' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">QRIS / E-Wallet</span>
                                <input type="radio" name="payment" value="qris" x-model="form.paymentMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-medium text-gray-500">Gopay, OVO, Dana, ShopeePay</span>
                        </label>

                        <label @click="form.paymentMethod = 'cod'; removeProof()" 
                               :class="form.paymentMethod === 'cod' ? 'border-red-600 bg-red-50/50 text-red-900' : 'border-gray-200 bg-white text-gray-700'"
                               class="p-4 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-black text-xs uppercase">COD / Bayar di Tempat</span>
                                <input type="radio" name="payment" value="cod" x-model="form.paymentMethod" class="accent-red-600">
                            </div>
                            <span class="text-[11px] font-medium text-gray-500">Khusus wilayah tertentu</span>
                        </label>
                    </div>

                    <!-- Informasi Rekening Transfer -->
                    <div x-show="form.paymentMethod === 'transfer'" x-transition class="bg-gray-50 p-4 rounded-2xl border border-gray-200 space-y-2.5 text-xs">
                        <p class="font-bold text-gray-800">Rekening Resmi Sport Mania Distro:</p>
                        <div class="flex items-center justify-between bg-white px-3.5 py-2.5 rounded-xl border border-gray-200">
                            <p class="text-gray-700"><strong>BCA:</strong> 1234567890 a.n. Sport Mania Distro</p>
                            <button type="button" @click="copyRekening('1234567890')" class="text-[10px] font-black uppercase bg-gray-900 hover:bg-red-600 text-white px-2.5 py-1 rounded-lg transition">Salin</button>
                        </div>
                        <div class="flex items-center justify-between bg-white px-3.5 py-2.5 rounded-xl border border-gray-200">
                            <p class="text-gray-700"><strong>Mandiri:</strong> 1440012345678 a.n. Sport Mania Distro</p>
                            <button type="button" @click="copyRekening('1440012345678')" class="text-[10px] font-black uppercase bg-gray-900 hover:bg-red-600 text-white px-2.5 py-1 rounded-lg transition">Salin</button>
                        </div>
                        <p class="text-[10px] text-gray-500 pt-0.5">Transfer sesuai nominal tagihan, lalu unggah bukti bayar di bawah ini (atau menyusul di halaman <strong>Riwayat Pesanan Saya</strong>).</p>
                    </div>

                    <!-- Informasi Detail QRIS (Menggunakan public/image/QRIS.jpeg) -->
                    <div x-show="form.paymentMethod === 'qris'" x-transition class="bg-gray-50 p-5 sm:p-6 rounded-2xl border border-gray-200 text-center space-y-3">
                        <div class="flex items-center justify-center gap-2">
                            <span class="bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider">QRIS</span>
                            <p class="text-xs font-bold text-gray-800">KYOWACELL • SPORT MANIA DISTRO</p>
                        </div>

                        <div @click="qrisZoom = true"
                             title="Klik untuk memperbesar kode QRIS"
                             class="w-60 sm:w-64 bg-white mx-auto p-2.5 rounded-2xl border border-gray-200 shadow-sm cursor-zoom-in hover:border-red-400 transition group relative overflow-hidden">
                            <img src="{{ asset('image/QRIS.jpeg') }}"
                                 alt="QRIS Sport Mania Distro"
                                 class="w-full h-auto object-contain rounded-xl">
                            <span class="mt-2 inline-block text-[10px] font-bold text-gray-500 group-hover:text-red-600 transition">
                                🔍 Klik gambar untuk perbesar
                            </span>
                        </div>

                        <div class="flex items-center justify-center gap-2">
                            <button type="button"
                                    @click="qrisZoom = true"
                                    class="text-[11px] font-bold text-gray-700 hover:text-gray-900 bg-white hover:bg-gray-100 px-3 py-1.5 rounded-xl border border-gray-200 transition">
                                Perbesar QRIS
                            </button>
                            <a href="{{ asset('image/QRIS.jpeg') }}"
                               download="QRIS-SportManiaDistro.jpeg"
                               class="text-[11px] font-bold text-red-600 hover:text-white bg-red-50 hover:bg-red-600 px-3 py-1.5 rounded-xl border border-red-200 transition">
                                ↓ Simpan QRIS
                            </a>
                        </div>

                        <div class="pt-1 space-y-1">
                            <p class="text-xs font-black text-red-600" x-text="'Nominal Pembayaran: Rp ' + Number(total).toLocaleString('id-ID')"></p>
                            <p class="text-[10px] text-gray-500 max-w-md mx-auto leading-relaxed">
                                Scan via GoPay, OVO, DANA, ShopeePay, atau Mobile Banking sesuai nominal di atas, lalu unggah screenshot bukti bayar di bawah ini.
                            </p>
                        </div>
                    </div>

                    <!-- Form Unggah Bukti Transfer / QRIS (Langsung masuk ke Verifikasi Admin) -->
                    <div x-show="form.paymentMethod !== 'cod'" x-transition class="pt-2 border-t border-gray-100 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-gray-800 uppercase">
                                Unggah Bukti Pembayaran <span class="text-gray-400 font-medium normal-case">(Opsional sekarang / bisa menyusul)</span>
                            </label>
                            <button type="button" x-show="proofFile" @click="removeProof()" class="text-[11px] font-bold text-red-600 hover:underline">
                                Hapus Foto
                            </button>
                        </div>

                        <input type="file"
                               x-ref="proofInput"
                               accept="image/jpeg,image/png,image/webp"
                               @change="handleProofUpload($event)"
                               class="block w-full text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-xl p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-red-600 transition cursor-pointer">

                        <template x-if="proofPreview">
                            <div class="mt-2 p-3 bg-gray-50 rounded-2xl border border-gray-200 inline-block">
                                <img :src="proofPreview" alt="Preview Bukti Bayar" class="max-h-48 w-auto object-contain rounded-xl">
                                <p class="text-[10px] text-emerald-600 font-bold mt-1.5">✓ Bukti pembayaran siap dikirim ke Admin</p>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            <!-- RINGKASAN PESANAN (KANAN) -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm sticky top-28 space-y-6">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <h3 class="text-base font-black text-gray-900 uppercase tracking-tight">Ringkasan Pesanan</h3>
                        <span class="text-[11px] font-bold bg-gray-100 text-gray-700 px-2.5 py-1 rounded-lg" x-text="totalItems + ' pcs'"></span>
                    </div>
                    
                    <!-- List Item Mini -->
                    <div class="space-y-3.5 max-h-64 overflow-y-auto pr-2">
                        <template x-for="(item, index) in cartItems" :key="item.id + '-' + (item.variant_id || '') + '-' + (item.size || '') + '-' + (item.color || '') + '-' + index">
                            <div class="flex justify-between items-center text-xs gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-11 h-11 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden shrink-0 flex items-center justify-center font-black text-[10px] text-gray-300">
                                        <template x-if="item.image">
                                            <img :src="item.image" :alt="item.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!item.image">
                                            <span>SM</span>
                                        </template>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-black text-gray-900 shrink-0" x-text="item.qty + 'x'"></span>
                                            <span class="text-gray-800 font-semibold truncate" x-text="item.name"></span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 font-medium" x-text="(item.size || 'All Size') + ' • ' + (item.color || '-')"></p>
                                    </div>
                                </div>
                                <span class="font-bold text-gray-900 shrink-0" x-text="'Rp ' + Number(item.price * item.qty).toLocaleString('id-ID')"></span>
                            </div>
                        </template>

                        <div x-show="cartItems.length === 0" class="text-center py-6 space-y-3">
                            <p class="text-xs text-gray-400 font-medium">Tidak ada produk di keranjang.</p>
                            <a href="{{ route('home') }}#katalog" class="inline-block text-[11px] font-bold text-red-600 hover:underline">← Pilih Produk di Katalog</a>
                        </div>
                    </div>

                    <!-- INPUT KODE VOUCHER PROMO -->
                    <div class="border-t border-gray-100 pt-4 space-y-2">
                        <label class="block text-[11px] font-black uppercase tracking-wider text-gray-700">
                            🎟️ Kode Promo / Voucher
                        </label>
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="voucherInput"
                                   @keydown.enter.prevent="applyVoucher()"
                                   :disabled="Boolean(appliedVoucher) || isCheckingVoucher"
                                   placeholder="Masukkan kode voucher..."
                                   class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-red-600 disabled:bg-gray-100 disabled:text-gray-500">
                            <button type="button"
                                    x-show="!appliedVoucher"
                                    @click="applyVoucher()"
                                    :disabled="isCheckingVoucher || !voucherInput.trim()"
                                    class="bg-gray-900 hover:bg-red-600 disabled:bg-gray-300 text-white px-4 py-2.5 rounded-xl font-black text-[11px] uppercase tracking-wider transition shrink-0">
                                <span x-show="!isCheckingVoucher">Gunakan</span>
                                <span x-show="isCheckingVoucher" x-cloak>...</span>
                            </button>
                            <button type="button"
                                    x-show="appliedVoucher"
                                    x-cloak
                                    @click="removeVoucher()"
                                    class="bg-red-50 hover:bg-red-600 text-red-600 hover:text-white border border-red-200 px-3.5 py-2.5 rounded-xl font-black text-[11px] uppercase tracking-wider transition shrink-0">
                                Hapus
                            </button>
                        </div>

                        <p x-show="voucherMessage" x-cloak class="text-[11px] font-bold text-emerald-600 flex items-center gap-1">
                            <span>✓</span> <span x-text="voucherMessage"></span>
                        </p>
                        <p x-show="voucherError" x-cloak class="text-[11px] font-bold text-red-600 flex items-center gap-1">
                            <span>!</span> <span x-text="voucherError"></span>
                        </p>
                    </div>

                    <div class="space-y-3 text-xs font-medium text-gray-600 border-t border-gray-100 pt-4">
                        <div class="flex justify-between">
                            <span>Subtotal Barang</span>
                            <span class="font-bold text-gray-900" x-text="'Rp ' + Number(subtotal).toLocaleString('id-ID')"></span>
                        </div>
                        <div x-show="effectiveDiscount > 0" x-cloak class="flex justify-between items-center text-emerald-600">
                            <span>Diskon Voucher (<span class="font-black uppercase" x-text="appliedVoucher"></span>)</span>
                            <span class="font-black" x-text="'- Rp ' + Number(effectiveDiscount).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Biaya Pengiriman (<span class="uppercase" x-text="form.shippingMethod"></span>)</span>
                            <span class="font-bold" :class="shippingCost === 0 ? 'text-green-600 bg-green-50 px-2 py-0.5 rounded' : 'text-gray-900'" x-text="shippingCost === 0 ? 'GRATIS' : 'Rp ' + Number(shippingCost).toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 flex justify-between items-end">
                        <span class="font-bold text-sm text-gray-900 uppercase">Total Tagihan</span>
                        <span class="font-black text-xl text-red-600" x-text="'Rp ' + Number(total).toLocaleString('id-ID')"></span>
                    </div>

                    <!-- Tombol dengan State Loading -->
                    <button type="button"
                            @click="processCheckout()" 
                            :disabled="isLoading || cartItems.length === 0"
                            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white py-4 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-green-600/20 flex items-center justify-center gap-2 cursor-pointer">
                        <span x-show="!isLoading">Konfirmasi Pesanan via WhatsApp →</span>
                        <span x-show="isLoading" class="flex items-center gap-2" x-cloak>
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Memproses Pesanan...
                        </span>
                    </button>
                    
                    <p class="text-[10px] text-center text-gray-400 font-medium">Dengan menekan tombol konfirmasi, data pesananmu akan tersimpan otomatis dan diteruskan ke WhatsApp Official Sport Mania Distro.</p>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Zoom QRIS -->
    <div x-show="qrisZoom"
         x-cloak
         x-transition.opacity
         @click="qrisZoom = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/85 backdrop-blur-sm">
        <div @click.stop class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-gray-200">
            <div class="bg-gray-900 px-5 py-3.5 flex items-center justify-between text-white">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block">Scan QRIS Pembayaran</span>
                    <p class="text-xs font-bold mt-0.5" x-text="'Total: Rp ' + Number(total).toLocaleString('id-ID')"></p>
                </div>
                <button type="button" @click="qrisZoom = false" class="w-8 h-8 rounded-xl bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center text-xs transition">✕</button>
            </div>
            <div class="p-4 bg-gray-100 flex items-center justify-center max-h-[75vh] overflow-auto">
                <img src="{{ asset('image/QRIS.jpeg') }}" alt="QRIS Full" class="max-h-[70vh] w-auto object-contain rounded-xl shadow">
            </div>
            <div class="px-5 py-3.5 bg-white border-t border-gray-100 flex items-center justify-between gap-2">
                <a href="{{ asset('image/QRIS.jpeg') }}"
                   download="QRIS-SportManiaDistro.jpeg"
                   class="text-xs font-bold text-red-600 hover:underline">
                    ↓ Download Gambar QRIS
                </a>
                <button type="button"
                        @click="qrisZoom = false"
                        class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-black uppercase tracking-wider px-4 py-2 rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Sukses Pesanan -->
    <div x-show="orderSuccess.show"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/70 backdrop-blur-sm">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-gray-100 text-center space-y-5">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl font-black">
                ✓
            </div>
            <div class="space-y-1.5">
                <span class="inline-block bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full" x-text="'ORDER #' + orderSuccess.orderNumber"></span>
                <h3 class="text-xl font-black text-gray-900 pt-1">Pesanan Berhasil Dibuat!</h3>
                <p class="text-xs text-gray-500 leading-relaxed">
                    Total tagihan sebesar <strong class="text-gray-900" x-text="orderSuccess.totalFormatted"></strong> telah tercatat.
                    <span x-show="orderSuccess.popupBlocked" class="block mt-1 text-amber-600 font-semibold">
                        Browser menahan tab WhatsApp otomatis—silakan klik tombol hijau di bawah untuk mengirim detail pesanan ke Admin.
                    </span>
                </p>
            </div>
            <div class="space-y-2.5 pt-2">
                <a :href="orderSuccess.waUrl" target="_blank"
                   class="w-full bg-green-600 hover:bg-green-700 text-white py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-green-600/20 flex items-center justify-center gap-2">
                    Kirim Konfirmasi ke WhatsApp Admin →
                </a>
                <a href="{{ route('customer.orders') }}"
                   class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition block">
                    Lihat Riwayat & Unggah Bukti Bayar
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-10 w-auto mx-auto mb-4 object-contain grayscale opacity-60">
            <p class="text-gray-500 text-[10px] sm:text-xs font-medium">© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika.</p>
        </div>
    </footer>
</body>
</html>