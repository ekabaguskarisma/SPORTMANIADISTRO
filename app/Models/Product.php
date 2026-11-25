<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'hpp',
        'image',
        'video',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'price' => 'integer',
        'discount_price' => 'integer',
        'hpp' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->slug) && filled($product->name)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'produk';
        $slug = $baseSlug;
        $counter = 2;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class);
    }

    // Otomatis menghitung total stok dari seluruh varian tanpa kolom stok ganda
    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('stock');
    }

    // Otomatis menghitung persentase diskon untuk badge etalase
    public function getDiscountPercentageAttribute(): ?int
    {
        if ($this->discount_price && $this->discount_price < $this->price && $this->price > 0) {
            return (int) round((($this->price - $this->discount_price) / $this->price) * 100);
        }
        return null;
    }

    // Mengambil harga aktif (harga diskon jika ada, jika tidak harga normal)
    public function getFinalPriceAttribute(): int
    {
        return ($this->discount_price && $this->discount_price < $this->price)
            ? $this->discount_price
            : $this->price;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}   