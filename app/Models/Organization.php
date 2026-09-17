<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_id', 'name', 'address', 'rating', 'rating_count', 'review_count',
        'rubrics', 'yandex_url', 'average_rating', 'total_ratings', 'total_reviews',
        'raw_data', 'status', 'parsed_at', 'last_error',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'rating' => 'float',
        'rubrics' => 'array',
        'raw_data' => 'array',
        'parsed_at' => 'datetime',
    ];

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function snapshots()
    {
        return $this->hasMany(ReviewSnapshot::class);
    }

    public function parseRuns()
    {
        return $this->hasMany(ParseRun::class);
    }
}
