<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['key', 'name', 'mime', 'size', 'width', 'height', 'disk'];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function url(): string
    {
        return '/media/'.$this->key;
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
