<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'key', 'thumb_key', 'name', 'alt', 'caption', 'mime', 'size', 'width', 'height', 'disk',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function url(): string
    {
        return '/media/'.$this->key;
    }

    /** What the CMS should show. Falls back to the original for anything with no small copy. */
    public function thumbUrl(): string
    {
        return '/media/'.($this->thumb_key ?: $this->key);
    }

    public function meta(): string
    {
        $type = strtoupper(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'FILE');

        if ($this->width && $this->height) {
            return "{$type} · {$this->width} × {$this->height}";
        }

        return $type.' · '.$this->readableSize();
    }

    public function readableSize(): string
    {
        if ($this->size >= 1048576) {
            return round($this->size / 1048576, 1).' MB';
        }

        return max(1, (int) round($this->size / 1024)).' KB';
    }
}
