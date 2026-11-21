<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminProductController extends Controller
{
    /**
     * Menampilkan daftar semua produk di panel admin.
     */
    public function index()
    {
        $products = Product::with(['category', 'variants', 'images', 'videos'])->latest()->get();
        return view('admin.produk.index', compact('products'));
    }

    /**
     * Menampilkan form untuk menambah produk baru.
     */
    public function create()
    {
        $categories = Category::all();
        return view('admin.produk.create', compact('categories'));
    }

    /**
     * Menyimpan data produk baru beserta varian, galeri foto, dan multi-video ke database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'image'          => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images'         => 'nullable|array',
            'images.*'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'videos'         => 'nullable|array',
            'videos.*'       => 'nullable|file|mimes:mp4,webm,mov,avi|max:51200',
            'variant_sizes'  => 'nullable|array',
            'variant_colors' => 'nullable|array',
            'variant_stocks' => 'nullable|array',
        ]);

        // 1. Simpan Foto Utama (Cover)
        $mainImagePath = $request->file('image')->store('products', 'public');

        // 2. Buat Produk Baru
        $product = Product::create([
            'name'           => $request->name,
            'category_id'    => $request->category_id,
            'price'          => $request->price,
            'discount_price' => $request->discount_price,
            'description'    => $request->description,
            'image'          => $mainImagePath,
            'is_featured'    => $request->has('is_featured') ? 1 : 0,
        ]);

        // 3. Simpan Banyak Foto Galeri Tambahan (Jika ada)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('products/gallery', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        // 4. Simpan Banyak Video Katalog (Jika ada)
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $videoFile) {
                $videoPath = $videoFile->store('product-videos', 'public');
                ProductVideo::create([
                    'product_id' => $product->id,
                    'video_path' => $videoPath,
                ]);
            }
        }

        // 5. Simpan Varian Ukuran, Warna, dan Stok
        if ($request->filled('variant_sizes')) {
            foreach ($request->variant_sizes as $index => $size) {
                if (!empty($size)) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size'       => $size,
                        'color'      => $request->variant_colors[$index] ?? '-',
                        'stock'      => $request->variant_stocks[$index] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Produk beserta varian, galeri foto, dan video berhasil ditambahkan!');
    }

    /**
     * Menampilkan form edit produk.
     */
    public function edit($id)
    {
        $product = Product::with(['variants', 'images', 'videos'])->findOrFail($id);
        $categories = Category::all();
        return view('admin.produk.edit', compact('product', 'categories'));
    }

    /**
     * Memperbarui data produk, galeri foto, multi-video, dan variannya.
     */
    public function update(Request $request, $id)
    {
        $product = Product::with(['variants', 'images', 'videos'])->findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images'         => 'nullable|array',
            'images.*'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'videos'         => 'nullable|array',
            'videos.*'       => 'nullable|file|mimes:mp4,webm,mov,avi|max:51200',
            'description'    => 'nullable|string',
            'variant_sizes'  => 'nullable|array',
            'variant_colors' => 'nullable|array',
            'variant_stocks' => 'nullable|array',
        ]);

        // 1. Jika ada upload gambar cover baru, hapus gambar lama dan simpan yang baru
        $mainImagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $mainImagePath = $request->file('image')->store('products', 'public');
        }

        // 1B. Jika video tunggal lama dicentang untuk dihapus
        $legacyVideoPath = $product->video;
        if ($request->boolean('remove_video') && !empty($legacyVideoPath)) {
            if (Storage::disk('public')->exists($legacyVideoPath)) {
                Storage::disk('public')->delete($legacyVideoPath);
            }
            $legacyVideoPath = null;
        }

        // 2. Update data utama produk
        $product->update([
            'name'           => $request->name,
            'category_id'    => $request->category_id,
            'price'          => $request->price,
            'discount_price' => $request->discount_price,
            'description'    => $request->description,
            'image'          => $mainImagePath,
            'video'          => $legacyVideoPath,
            'is_featured'    => $request->has('is_featured') ? 1 : 0,
        ]);

        // 3. Tambah Foto Galeri Baru (Jika diunggah saat edit)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('products/gallery', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        // 4. Tambah Video Katalog Baru (Bisa lebih dari 1)
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $videoFile) {
                $videoPath = $videoFile->store('product-videos', 'public');
                ProductVideo::create([
                    'product_id' => $product->id,
                    'video_path' => $videoPath,
                ]);
            }
        }

        // 5. Perbarui Varian (Hapus varian lama, lalu buat ulang dari input form)
        $product->variants()->delete();
        if ($request->filled('variant_sizes')) {
            foreach ($request->variant_sizes as $index => $size) {
                if (!empty($size)) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size'       => $size,
                        'color'      => $request->variant_colors[$index] ?? '-',
                        'stock'      => $request->variant_stocks[$index] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Data produk beserta varian, galeri, dan video berhasil diperbarui!');
    }

    /**
     * Menghapus satu video katalog dari produk (dipanggil dari halaman edit).
     */
    public function destroyVideo($id)
    {
        $video = ProductVideo::findOrFail($id);

        if ($video->video_path && Storage::disk('public')->exists($video->video_path)) {
            Storage::disk('public')->delete($video->video_path);
        }

        $video->delete();

        return back()->with('success', 'Video katalog berhasil dihapus.');
    }

    /**
     * Menghapus produk dari database beserta file gambar dan videonya.
     */
    public function destroy($id)
    {
        $product = Product::with(['variants', 'images', 'videos'])->findOrFail($id);

        // Hapus file gambar cover utama dari storage
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        // Hapus file video tunggal lama jika ada
        if ($product->video && Storage::disk('public')->exists($product->video)) {
            Storage::disk('public')->delete($product->video);
        }

        // Hapus file galeri foto tambahan dari storage
        foreach ($product->images as $img) {
            if (Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        // Hapus semua file multi-video dari storage
        foreach ($product->videos as $vid) {
            if (Storage::disk('public')->exists($vid->video_path)) {
                Storage::disk('public')->delete($vid->video_path);
            }
        }

        // Hapus data produk
        $product->delete();

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Produk berhasil dihapus!');
    }
}