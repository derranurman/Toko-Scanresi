<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfParseDraft extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'user_id',
        'original_filename',
        'total_pages',
        'status',
        'parsed_orders',
    ];

    protected function casts(): array
    {
        return [
            'parsed_orders' => 'array',
            'total_pages' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
