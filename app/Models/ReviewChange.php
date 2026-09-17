<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id', 'parse_run_id', 'field', 'old_value', 'new_value',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function run()
    {
        return $this->belongsTo(ParseRun::class, 'parse_run_id');
    }
}
