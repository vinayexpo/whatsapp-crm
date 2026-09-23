<?php

namespace App\Services\Commerce\Steps;

use App\Models\Branch;
use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

class BranchSelectionStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender, private WelcomeStepHandler $welcome) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        $selectedId = $this->matchBranchId($inboundMessage);

        if (! $selectedId) {
            $this->sender->send($session->conversation, 'Please choose a location from the list above.');

            return true;
        }

        $branch = Branch::query()->where('company_id', $session->company_id)->where('id', $selectedId)->first();

        if (! $branch) {
            $this->sender->send($session->conversation, 'That location is no longer available. Please choose another.');

            return true;
        }

        $session->update(['branch_id' => $branch->id]);

        return $this->welcome->enter($session->fresh());
    }

    private function matchBranchId(Message $inboundMessage): ?int
    {
        $id = $inboundMessage->interactive_reply_id ?? trim($inboundMessage->text ?? '');

        if (! str_starts_with($id, 'branch:')) {
            return null;
        }

        return (int) substr($id, strlen('branch:'));
    }
}
