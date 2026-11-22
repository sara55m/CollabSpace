<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Number;

class Attachment extends Model
{
    protected $fillable=['name','size','path','mime_type','uploaded_by'];

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

    protected function formattedSize(): Attribute
    {
        return Attribute::get(fn () => Number::fileSize($this->size ?? 0, precision: 1));
    }

    protected static function booted(): void
    {
        // delete the stored file when a single attachment is deleted
        static::deleted(fn (Attachment $a) => Storage::delete($a->path));
    }
}
