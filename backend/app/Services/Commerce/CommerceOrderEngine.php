<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\CommerceSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OrderSession;
use App\Services\Commerce\Steps\BranchSelectionStepHandler;
use App\Services\Commerce\Steps\CartReviewStepHandler;
use App\Services\Commerce\Steps\CategoryBrowseStepHandler;
use App\Services\Commerce\Steps\CustomerDetailsStepHandler;
use App\Services\Commerce\Steps\DeliveryOrPickupStepHandler;
use App\Services\Commerce\Steps\OrderConfirmationStepHandler;
use App\Services\Commerce\Steps\PaymentMethodStepHandler;
use App\Services\Commerce\Steps\ProductBrowseStepHandler;
use App\Services\Commerce\Steps\ProductDetailStepHandler;
use App\Services\Commerce\Steps\VariantAddonSelectionStepHandler;
use App\Services\Commerce\Steps\WelcomeStepHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Entry point matching ChatMenuFlowEngine::handle()'s exact contract.
 * Commerce is checked before ChatMenuFlowEngine (see
 * ProcessInboundWhatsAppMessage::storeInboundMessage()); trigger keywords
 * are validated mutually-exclusive at config time
 * (TriggerKeywordUniquenessValidator), so this never actually shadows a
 * chat-menu-flow in practice.
 */
class CommerceOrderEngine
{
    /** @var array<string, class-string> */
    private const STEP_HANDLERS = [
        'welcome' => WelcomeStepHandler::class,
        'branch_selection' => BranchSelectionStepHandler::class,
        'category_browse' => CategoryBrowseStepHandler::class,
        'product_browse' => ProductBrowseStepHandler::class,
        'product_detail' => ProductDetailStepHandler::class,
        'variant_addon_selection' => VariantAddonSelectionStepHandler::class,
        'cart_review' => CartReviewStepHandler::class,
        'customer_details' => CustomerDetailsStepHandler::class,
        'delivery_or_pickup' => DeliveryOrPickupStepHandler::class,
        'payment_method' => PaymentMethodStepHandler::class,
        'order_confirmation' => OrderConfirmationStepHandler::class,
    ];

    private const DEFAULT_TRIGGER_KEYWORDS = ['hi', 'hello', 'menu', 'order'];

    private const EXIT_KEYWORDS = ['cancel', 'exit', 'stop'];

    public function __construct(private CommerceMessageSender $sender) {}

    public function handle(Conversation $conversation, Message $inboundMessage): bool
    {
        $activeSession = OrderSession::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->first();

        if ($activeSession) {
            return $this->resumeSession($activeSession, $inboundMessage);
        }

        return $this->maybeStartSession($conversation, $inboundMessage);
    }

    private function resumeSession(OrderSession $session, Message $inboundMessage): bool
    {
        $text = strtolower(trim($inboundMessage->text ?? ''));

        if (in_array($text, self::EXIT_KEYWORDS, true)) {
            $session->update(['status' => 'abandoned']);
            $this->sender->send($session->conversation, 'Your order has been cancelled. Send "hi" any time to start again.');

            return true;
        }

        $handlerClass = self::STEP_HANDLERS[$session->step] ?? null;

        if (! $handlerClass) {
            $session->update(['status' => 'abandoned']);

            return false;
        }

        return app($handlerClass)->handle($session, $inboundMessage);
    }

    private function maybeStartSession(Conversation $conversation, Message $inboundMessage): bool
    {
        if ($conversation->channel !== 'whatsapp' && $conversation->channel !== 'web') {
            return false;
        }

        $companyId = $conversation->contact->company_id ?? null;

        if (! $companyId) {
            return false;
        }

        $keywords = $this->triggerKeywords($companyId);
        $text = strtolower(trim($inboundMessage->text ?? ''));

        if ($text === '' || ! in_array($text, $keywords, true)) {
            return false;
        }

        $branch = $this->resolveBranch($companyId, $conversation->api_connection_id);

        if (! $branch && ! $this->companyHasAnyBranch($companyId)) {
            return false;
        }

        try {
            $session = DB::transaction(function () use ($conversation, $companyId, $branch) {
                return OrderSession::create([
                    'company_id' => $companyId,
                    'conversation_id' => $conversation->id,
                    'contact_id' => $conversation->contact_id,
                    'branch_id' => $branch?->id,
                    'api_connection_id' => $conversation->api_connection_id,
                    'status' => 'active',
                    'step' => 'welcome',
                    'context' => [],
                    'last_interaction_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Another concurrent webhook already created the active session for
            // this conversation (order_sessions_active_conversation_unique) --
            // resume the winner's session instead of failing this job.
            $winner = OrderSession::query()
                ->where('conversation_id', $conversation->id)
                ->where('status', 'active')
                ->first();

            if (! $winner) {
                throw $e;
            }

            return $this->resumeSession($winner, $inboundMessage);
        }

        return app(WelcomeStepHandler::class)->enter($session);
    }

    /**
     * @return array<int, string>
     */
    private function triggerKeywords(int $companyId): array
    {
        $configured = CommerceSetting::query()->where('company_id', $companyId)->value('settings')['trigger_keywords'] ?? null;

        if (empty($configured)) {
            return self::DEFAULT_TRIGGER_KEYWORDS;
        }

        return array_map('strtolower', array_map('trim', $configured));
    }

    private function resolveBranch(int $companyId, ?int $apiConnectionId): ?Branch
    {
        if ($apiConnectionId) {
            $matched = Branch::query()
                ->where('company_id', $companyId)
                ->where('api_connection_id', $apiConnectionId)
                ->where('status', 'active')
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        $unassigned = Branch::query()
            ->where('company_id', $companyId)
            ->whereNull('api_connection_id')
            ->where('status', 'active')
            ->get();

        return $unassigned->count() === 1 ? $unassigned->first() : null;
    }

    private function companyHasAnyBranch(int $companyId): bool
    {
        return Branch::query()->where('company_id', $companyId)->where('status', 'active')->exists();
    }
}
