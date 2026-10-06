<?php

declare(strict_types=1);

namespace Atlas\Scope\Models;

use Atlas\Scope\Enums\EdgeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphEdge extends Model
{
    protected $table = 'atlas_graph_edges';

    protected $fillable = [
        'scan_id', 'source_key', 'target_key', 'kind', 'label', 'weight', 'hits', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'kind' => EdgeKind::class,
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
