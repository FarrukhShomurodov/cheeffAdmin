<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YandexDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'ya_delivery_uuid'
    ];
}
