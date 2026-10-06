<?php

declare(strict_types=1);

namespace Atlas\Scope\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    protected $table = 'atlas_scan_events';

    public const UPDATED_AT = null;

    protected $fillable = ['scan_id', 'level', 'stage', 'message', 'context', 'created_at'];

    /** @var array<string, string> */
    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
