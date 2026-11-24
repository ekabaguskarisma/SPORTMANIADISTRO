<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Menampilkan halaman katalog produk di beranda (welcome)
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'variants', 'images']);

        // Filter Kategori & Promo Spesial
        if ($request->filled('category') && strtolower($request->category) !== 'semua') {
            $categoryParam = strtolower(trim($request->category));

            if ($categoryParam === 'promo') {
                $query->whereNotNull('discount_price')
                      ->where('discount_price', '>', 0);
            } elseif (is_numeric($categoryParam)) {
                $query->where('category_id', (int) $categoryParam);
            } else {
                $query->whereHas('category', function ($q) use ($categoryParam) {
                    $q->where('name', 'like', '%' . $categoryParam . '%');
                });
            }
        }

        if ($request->boolean('promo')) {
            $query->whereNotNull('discount_price')
                  ->where('discount_price', '>', 0);
        }

        // Pencarian Kata Kunci (nama produk, deskripsi, dan nama kategori)
        if ($request->filled('search')) {
            $keyword = trim($request->search);
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                  ->orWhere('description', 'like', '%' . $keyword . '%')
                  ->orWhereHas('category', function ($catQuery) use ($keyword) {
                      $catQuery->where('name', 'like', '%' . $keyword . '%');
                  });
            });
        }

        $products = $query->latest()->get();
        $categories = Category::all();

        return view('welcome', compact('products', 'categories'));
    }

    /**
     * Menampilkan halaman detail produk (Zalora Style)
     */
    public function show($id)
    {
        $produk = Product::with(['category', 'variants', 'images', 'reviews.user'])->findOrFail($id);
        $categories = Category::all();

        $relatedProducts = Product::with(['variants', 'images'])
                                  ->where('category_id', $produk->category_id)
                                  ->where('id', '!=', $id)
                                  ->inRandomOrder()
                                  ->take(4)
                                  ->get();

        $relatedProducts = $relatedProducts->count() > 0 ? $relatedProducts : Product::with(['variants', 'images'])
                                                                                   ->where('id', '!=', $id)
                                                                                   ->inRandomOrder()
                                                                                   ->take(4)
                                                                                   ->get();

        return view('produk.show', compact('produk', 'relatedProducts', 'categories'));
    }

    /**
     * Menampilkan form tambah produk di panel admin
     */
    public function create()
    {
        $categories = Category::all();

        return view('admin.produk.create', compact('categories'));
    }

    /**
     * Menyimpan data produk, galeri foto, dan variannya ke database
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'image'          => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images.*'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variant_sizes'  => 'nullable|array',
            'variant_colors' => 'nullable|array',
            'variant_stocks' => 'nullable|array',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product-images', 'public');
        }

        $product = Product::create([
            'category_id'    => $request->category_id,
            'name'           => $request->name,
            'slug'           => Str::slug($request->name) . '-' . time(),
            'description'    => $request->description,
            'price'          => $request->price,
            'discount_price' => $request->discount_price,
            'image'          => $imagePath,
            'is_featured'    => $request->has('is_featured'),
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $galleryPath = $file->store('product-images/gallery', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $galleryPath,
                ]);
            }
        }

        if ($request->has('variant_sizes')) {
            $sizes  = $request->variant_sizes;
            $colors = $request->variant_colors;
            $stocks = $request->variant_stocks;

            for ($i = 0; $i < count($sizes); $i++) {
                if (!empty($sizes[$i]) || !empty($colors[$i])) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size'       => $sizes[$i] ?? 'All Size',
                        'color'      => $colors[$i] ?? '-',
                        'stock'      => $stocks[$i] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Produk beserta varian dan galeri foto berhasil ditambahkan!');
    }

    /**
     * Menampilkan form edit produk
     */
    public function edit($id)
    {
        $product = Product::with(['variants', 'images', 'category'])->findOrFail($id);
        $categories = Category::all();

        return view('admin.produk.edit', compact('product', 'categories'));
    }

    /**
     * Memperbarui data produk, varian, dan galeri foto
     */
    public function update(Request $request, $id)
    {
        $product = Product::with(['variants', 'images'])->findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images.*'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variant_sizes'  => 'nullable|array',
            'variant_colors' => 'nullable|array',
            'variant_stocks' => 'nullable|array',
        ]);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('product-images', 'public');
        }

        $product->update([
            'category_id'    => $request->category_id,
            'name'           => $request->name,
            'description'    => $request->description,
            'price'          => $request->price,
            'discount_price' => $request->discount_price,
            'image'          => $imagePath,
            'is_featured'    => $request->has('is_featured'),
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $galleryPath = $file->store('product-images/gallery', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $galleryPath,
                ]);
            }
        }

        $product->variants()->delete();
        if ($request->has('variant_sizes')) {
            $sizes  = $request->variant_sizes;
            $colors = $request->variant_colors;
            $stocks = $request->variant_stocks;

            for ($i = 0; $i < count($sizes); $i++) {
                if (!empty($sizes[$i]) || !empty($colors[$i])) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size'       => $sizes[$i] ?? 'All Size',
                        'color'      => $colors[$i] ?? '-',
                        'stock'      => $stocks[$i] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Data produk berhasil diperbarui!');
    }

    /**
     * Menghapus foto galeri tambahan secara satuan
     */
    public function destroyImage($id)
    {
        $image = ProductImage::findOrFail($id);
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        $image->delete();

        return back()->with('success', 'Foto galeri berhasil dihapus!');
    }

    /**
     * Menghapus produk beserta seluruh varian dan galeri fotonya
     */
    public function destroy($id)
    {
        $product = Product::with(['variants', 'images'])->findOrFail($id);

        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        foreach ($product->images as $img) {
            if (Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        $product->delete();

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Produk berhasil dihapus dari sistem!');
    }
}