<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'total_reviews', 'average_rating', 'metrics_snapshot', 'parser_version', 'status'
    ];

    protected $casts = [
        'metrics_snapshot' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
