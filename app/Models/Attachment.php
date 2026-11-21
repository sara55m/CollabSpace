<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable=['name','size','path','mime_type'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class,'uploaded_by');
    }

    protected static function booted(): void
    {
        // delete the stored file when a single attachment is deleted
        static::deleted(fn (Attachment $a) => Storage::delete($a->path));
    }
}
