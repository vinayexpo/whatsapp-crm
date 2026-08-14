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
     *
     * When $template is given (name, language, components), the message is
     * sent as a Meta template message instead of free-form text -- this is
     * the only message type Meta allows outside the 24-hour customer
     * service window.
     *
     * @param  array{name: string, language: string, components: array}|null  $template
     */
    public function send(Message $message, ApiConnection $connection, ?array $template = null): string;
}
