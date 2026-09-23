<?php

namespace App\Services\Commerce\Steps;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\CommerceMessageSender;

/**
 * The welcome step is entered immediately when a session is created and acts
 * as a pass-through: it renders whichever of branch_selection /
 * category_browse is appropriate and advances past itself. It never actually
 * waits for input, but exists as a distinct step value so it is visible in
 * order_sessions.step for debugging/admin visibility.
 */
class WelcomeStepHandler implements StepHandlerInterface
{
    public function __construct(private CommerceMessageSender $sender) {}

    public function handle(OrderSession $session, Message $inboundMessage): bool
    {
        return $this->enter($session);
    }

    public function enter(OrderSession $session): bool
    {
        $cart = new CartContext($session);

        if (! $session->branch_id) {
            return $this->presentBranchSelection($session, $cart);
        }

        $cart->setBranch($session->branch_id, Branch::withoutGlobalScopes()->find($session->branch_id)?->name ?? '');

        return $this->presentCategories($session, $cart);
    }

    private function presentBranchSelection(OrderSession $session, CartContext $cart): bool
    {
        $branches = Branch::query()
            ->where('company_id', $session->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        if ($branches->isEmpty()) {
            $cart->persist('completed');
            $session->update(['status' => 'abandoned']);
            $this->sender->send($session->conversation, "Sorry, we're not able to take orders right now. Please try again later.");

            return true;
        }

        $cart->persist('branch_selection');

        $buttons = $branches->map(fn (Branch $b) => ['id' => 'branch:'.$b->id, 'label' => $b->name])->values()->all();
        $this->sender->send($session->conversation, 'Welcome! Which location would you like to order from?', $buttons);

        return true;
    }

    private function presentCategories(OrderSession $session, CartContext $cart): bool
    {
        $categories = Category::query()
            ->where('company_id', $session->company_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        if ($categories->isEmpty()) {
            $cart->persist('completed');
            $session->update(['status' => 'abandoned']);
            $this->sender->send($session->conversation, "Sorry, we don't have any products available right now.");

            return true;
        }

        $cart->persist('category_browse');

        $buttons = $categories->map(fn (Category $c) => ['id' => 'category:'.$c->id, 'label' => $c->name])->values()->all();
        $this->sender->send($session->conversation, 'What would you like to browse?', $buttons);

        return true;
    }
}
