<?php

namespace App\Models\Shared;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Models\Utilities\StoredFile;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputSource extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function storedFiles()
    {
        return $this->morphMany(StoredFile::class, 'storable');
    }
}
