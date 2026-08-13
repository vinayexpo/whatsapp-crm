<?php

namespace App\Services\Calling;

use App\Models\ApiConnection;
use App\Models\WhatsappCall;

interface WhatsappCallServiceInterface
{
    /**
     * Place an outbound WhatsApp call for the given WhatsappCall record and
     * return Meta's call ID (used later to match webhook status updates back
     * to this call), mirroring VoiceCallServiceInterface::placeCall().
     */
    public function placeCall(WhatsappCall $call, ApiConnection $connection): string;

    /**
     * Send a call action (e.g. speak a prompt / pre-accept / terminate) to
     * Meta's Calling API for an in-progress call.
     */
    public function sendCallAction(WhatsappCall $call, ApiConnection $connection, array $action): void;
}
