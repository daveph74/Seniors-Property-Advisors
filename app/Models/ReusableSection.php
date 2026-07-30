<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReusableSection extends Model
{
    protected $fillable = ['name', 'type', 'block', 'created_by'];

    protected $casts = ['block' => 'array'];

    public function toLibraryRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
