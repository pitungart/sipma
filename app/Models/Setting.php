<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pengaturan sistem (kunci–nilai). Dibaca lewat kelas pendukung yang menyimpan nilai
 * bawaannya, mis. App\Support\UploadLimits.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];
}
