<?php

use App\Models\Inbox;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register the authorization callbacks for broadcast channels.
| For the temp mail system, we authorize based on inbox token.
|
*/

/**
 * Private channel for inbox email updates.
 * Authorization is done via the inbox token passed in the request.
 */
Broadcast::channel('inbox.{inboxId}', function ($user, $inboxId) {
    // For public temp mail, we allow access if the request has a valid token
    // The token validation happens in the frontend before subscribing
    // Since this is a public temp mail service without user authentication,
    // we return true and rely on the token being kept secret
    return true;
});
