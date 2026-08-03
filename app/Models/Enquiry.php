<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $table = 'enquiries';

    protected $fillable = [
        'name', 'email', 'phone', 'suburb', 'message', 'consented', 'page_slug',
        /* The column existed but was not fillable, so marking an enquiry dealt with would have
           silently done nothing whenever that screen gets built. */
        'handled_at',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'handled_at' => 'datetime',
    ];
}
