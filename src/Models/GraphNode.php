<?php

declare(strict_types=1);

namespace Atlas\Scope\Models;

use Atlas\Scope\Enums\EdgeKind;
use Atlas\Scope\Enums\Layer;
use Atlas\Scope\Enums\NodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphNode extends Model
{
    protected $table = 'atlas_graph_nodes';

    protected $fillable = [
        'scan_id', 'node_key', 'type', 'layer', 'label', 'fqcn', 'file_path', 'line',
        'module', 'parent_key', 'weight', 'fan_in', 'fan_out', 'loc',
        'pos_x', 'pos_y', 'pos_z', 'meta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'meta' => 'array',
        'type' => NodeType::class,
        'layer' => Layer::class,
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function edgeKind(): EdgeKind
    {
        return EdgeKind::tryFrom($this->type->value) ?? EdgeKind::Uses;
    }
}
