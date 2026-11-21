<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'name',
        'phone',
        'email',
        'city',
        'address',
        'shipping_address',
        'shipping_method',
        'payment_method',
        'payment_status',
        'payment_proof',
        'payment_rejected_reason',
        'payment_verified_at',
        'notes',
        'subtotal',
        'shipping_cost',
        'total_price',
        'total_amount',
        'status',
        'tracking_number',
        'resi',
    ];

    protected $casts = [
        'subtotal'            => 'decimal:2',
        'shipping_cost'       => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'payment_verified_at' => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Kompatibilitas otomatis jika controller/view memanggil alias lama
    public function getRecipientNameAttribute()
    {
        return $this->name;
    }

    public function setRecipientNameAttribute($val)
    {
        $this->attributes['name'] = $val;
    }

    public function getShippingAddressAttribute()
    {
        return $this->address;
    }

    public function setShippingAddressAttribute($val)
    {
        $this->attributes['address'] = $val;
    }

    public function getTotalPriceAttribute()
    {
        return $this->total_amount;
    }

    public function setTotalPriceAttribute($val)
    {
        $this->attributes['total_amount'] = $val;
    }

    public function getResiAttribute()
    {
        return $this->tracking_number;
    }

    public function setResiAttribute($val)
    {
        $this->attributes['tracking_number'] = $val;
    }

    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format((float) ($this->total_amount ?? 0), 0, ',', '.');
    }

    public function getStatusColorAttribute()
    {
        return match (strtolower((string) $this->status)) {
            'pending'    => 'bg-amber-50 text-amber-700 border-amber-200',
            'diproses'   => 'bg-blue-50 text-blue-700 border-blue-200',
            'dikirim'    => 'bg-purple-50 text-purple-700 border-purple-200',
            'selesai'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-200',
            default      => 'bg-slate-50 text-slate-600 border-slate-200',
        };
    }
}