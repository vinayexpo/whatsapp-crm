<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;
use App\Models\Message;

interface OutboundMessageServiceInterface
{
    /**
     * Send a message through the channel's messaging API and return the
     * provider's external message id (used later to match delivery/read
     * receipt webhooks back to this message).
     */
    public function send(Message $message, ApiConnection $connection): string;
}
