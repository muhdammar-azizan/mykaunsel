<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LkmDirectorySnapshot extends Model
{
    use HasFactory;

    protected $table = 'lkm_directory_snapshot';

    protected $fillable = [
        'kb_number',
        'pa_number',
        'full_name',
        'status',
        'pa_valid_from',
        'pa_valid_until',
        'email',
        'city',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'pa_valid_from' => 'date',
            'pa_valid_until' => 'date',
        ];
    }
}
