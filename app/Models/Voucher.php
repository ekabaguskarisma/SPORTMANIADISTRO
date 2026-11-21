<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_purchase', 'max_discount',
        'quota', 'used_count', 'expires_at', 'is_active'
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function calculateDiscount(float $subtotal): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Kode voucher sudah tidak aktif.'];
        }
        if ($this->expires_at && Carbon::parse($this->expires_at)->endOfDay()->isPast()) {
            return ['valid' => false, 'message' => 'Kode voucher sudah kedaluwarsa.'];
        }
        if ($this->used_count >= $this->quota) {
            return ['valid' => false, 'message' => 'Kuota voucher sudah habis.'];
        }
        if ($subtotal < (float) $this->min_purchase) {
            return [
                'valid' => false,
                'message' => 'Minimal belanja Rp ' . number_format($this->min_purchase, 0, ',', '.') . ' untuk memakai voucher ini.'
            ];
        }

        $discount = $this->type === 'percent'
            ? ($subtotal * ((float) $this->value / 100))
            : (float) $this->value;

        if ($this->type === 'percent' && $this->max_discount && $discount > (float) $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        $discount = min($discount, $subtotal);

        return [
            'valid' => true,
            'code' => strtoupper($this->code),
            'discount' => round($discount),
            'message' => 'Voucher berhasil diterapkan! Hemat Rp ' . number_format($discount, 0, ',', '.')
        ];
    }
}