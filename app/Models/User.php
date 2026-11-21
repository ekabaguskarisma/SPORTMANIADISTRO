<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    'address',
    'city',
    'postal_code',
    'role', // Jika sudah ada role admin/user
    'google_id'
];

public function orders()
{
    return $this->hasMany(Order::class);
}
}
