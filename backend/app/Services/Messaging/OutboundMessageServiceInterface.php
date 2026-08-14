<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;
use App\Models\Message;
use Illuminate\Http\UploadedFile;

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
     * When $attachmentFile is given, the raw upload is sent using Meta's
     * two-step media upload (POST the bytes to Meta, then reference the
     * returned media id) instead of a "link" URL -- this avoids depending
     * on our own storage being reachable over the public internet.
     *
     * @param  array{name: string, language: string, components: array}|null  $template
     */
    public function send(Message $message, ApiConnection $connection, ?array $template = null, ?UploadedFile $attachmentFile = null): string;
}
