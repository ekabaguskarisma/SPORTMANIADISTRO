<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'image',
        'video',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'price' => 'integer',
        'discount_price' => 'integer',
    ];

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