<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParseRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'status', 'parser_version', 'found_total',
        'new_count', 'changed_count', 'deleted_count',
        'started_at', 'finished_at', 'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function changes()
    {
        return $this->hasMany(ReviewChange::class);
    }
}
