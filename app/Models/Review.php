<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'yandex_id',
        'author',
        'rating',
        'text',
        'branch',
        'phone',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
