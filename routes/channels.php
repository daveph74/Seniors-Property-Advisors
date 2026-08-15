<?php

use App\Broadcasting\CmsChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/** The admin's own channel, held to the same rule as the admin itself — see `CmsChannel`. */
Broadcast::channel('cms', fn (User $user) => CmsChannel::allows($user));
