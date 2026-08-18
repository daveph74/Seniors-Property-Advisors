<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRedirect extends Model
{
    protected $fillable = ['from_url', 'to_url', 'page_id'];
}
