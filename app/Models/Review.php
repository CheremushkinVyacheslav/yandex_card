<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'external_id', 'author', 'author_level', 'author_name',
        'text', 'rating', 'review_date', 'source_id', 'business_reply',
        'business_reply_date', 'likes', 'dislikes', 'avatar_url',
        'is_deleted', 'created_in_run_id', 'updated_in_run_id', 'deleted_in_run_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'rating' => 'integer',
        'review_date' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdInRun()
    {
        return $this->belongsTo(ParseRun::class, 'created_in_run_id');
    }

    public function updatedInRun()
    {
        return $this->belongsTo(ParseRun::class, 'updated_in_run_id');
    }

    public function deletedInRun()
    {
        return $this->belongsTo(ParseRun::class, 'deleted_in_run_id');
    }
}
