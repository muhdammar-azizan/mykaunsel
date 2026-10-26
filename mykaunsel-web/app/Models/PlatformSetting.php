<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One platform-wide setting (see PlatformSettings). The value is stored as JSON. */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
