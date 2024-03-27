<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserByCode extends Model
{
    use HasFactory;

    protected $fillable = [
      'user',
      'code'
    ];
}
