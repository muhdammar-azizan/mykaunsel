<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmotionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'emotion',
        'confidence_score',
        'analysed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:4',
            'analysed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
