<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'size',
        'color',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // Kompatibilitas alias variant_size <-> size dan variant_color <-> color
    public function getVariantSizeAttribute()
    {
        return $this->size;
    }

    public function setVariantSizeAttribute($val)
    {
        $this->attributes['size'] = $val;
    }

    public function getVariantColorAttribute()
    {
        return $this->color;
    }

    public function setVariantColorAttribute($val)
    {
        $this->attributes['color'] = $val;
    }

    public function getDisplayNameAttribute()
    {
        return $this->product->name ?? 'Produk Tidak Tersedia';
    }

    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format((float) ($this->price ?? 0), 0, ',', '.');
    }

    public function getSubtotalAttribute()
    {
        return (float) $this->price * (int) $this->quantity;
    }

    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }
}