<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit trail of content status changes. */
class StatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['content_item_id', 'from_status', 'to_status', 'note', 'actor'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }
}
