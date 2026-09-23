<?php

namespace App\Services\Commerce\Steps;

use App\Models\Message;
use App\Models\OrderSession;

interface StepHandlerInterface
{
    /**
     * Handle an inbound message while the session is on this handler's step.
     * Always returns true (a resumed session never falls through to
     * AI/automation) — implementations advance the session, reprompt on
     * unrecognized input, or end the session, and always send a reply.
     */
    public function handle(OrderSession $session, Message $inboundMessage): bool;
}
