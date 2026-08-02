<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $table = 'enquiries';

    protected $fillable = [
        'name', 'email', 'phone', 'suburb', 'message', 'consented', 'page_slug',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'handled_at' => 'datetime',
    ];
}
