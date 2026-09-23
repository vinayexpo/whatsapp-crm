<?php

use App\Jobs\SendOrderConfirmationMessage;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderSession;
use App\Models\Product;
use App\Services\Commerce\CommerceOrderEngine;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function commerceInbound(Conversation $conversation, string $text, ?string $interactiveReplyId = null): Message
{
    return Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => $text,
        'interactive_reply_id' => $interactiveReplyId,
    ]);
}

function commerceLocationInbound(Conversation $conversation, float $lat, float $lng): Message
{
    return Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => '',
        'location_lat' => $lat,
        'location_lng' => $lng,
    ]);
}

function commerceHandle(Conversation $conversation, Message $inbound): bool
{
    return app(CommerceOrderEngine::class)->handle($conversation, $inbound);
}

function lastOutbound(Conversation $conversation): ?Message
{
    return Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->latest('id')
        ->first();
}

beforeEach(function () {
    Queue::fake();
    $this->seed(PipelineStagesSeeder::class);

    $this->company = Company::factory()->create();
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->category = Category::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'base_price' => 10000,
        'sale_price' => null,
    ]);

    $this->contact = Contact::factory()->create(['company_id' => $this->company->id, 'channel' => 'whatsapp']);
    $this->conversation = Conversation::factory()->create([
        'contact_id' => $this->contact->id,
        'channel' => 'whatsapp',
    ]);
});

it('does not trigger commerce on unrelated text when no session exists', function () {
    $inbound = commerceInbound($this->conversation, 'what are your hours?');

    $handled = commerceHandle($this->conversation, $inbound);

    expect($handled)->toBeFalse();
    $this->assertDatabaseMissing('order_sessions', ['conversation_id' => $this->conversation->id]);
});

it('starts a session and presents categories on a trigger keyword when exactly one branch exists', function () {
    $inbound = commerceInbound($this->conversation, 'hi');

    $handled = commerceHandle($this->conversation, $inbound);

    expect($handled)->toBeTrue();

    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();
    expect($session)->not->toBeNull();
    expect($session->status)->toBe('active');
    expect($session->step)->toBe('category_browse');
    expect($session->branch_id)->toBe($this->branch->id);

    $reply = lastOutbound($this->conversation);
    expect($reply->buttons)->toHaveCount(1);
    expect($reply->buttons[0]['id'])->toBe('category:'.$this->product->category_id);
});

it('completes a full order end to end via chat', function () {
    Queue::fake();

    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();
    expect($session->step)->toBe('category_browse');

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'category:'.$this->category->id));
    $session->refresh();
    expect($session->step)->toBe('product_browse');

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'product:'.$this->product->id));
    $session->refresh();
    expect($session->step)->toBe('variant_addon_selection');

    commerceHandle($this->conversation, commerceInbound($this->conversation, '2'));
    $session->refresh();
    expect($session->step)->toBe('cart_review');
    expect($session->context['cart'])->toHaveCount(1);
    expect($session->context['cart'][0]['quantity'])->toBe(2);
    expect($session->context['cart_total'])->toBe(20000);

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'cart:checkout'));
    $session->refresh();
    expect($session->step)->toBe('customer_details');

    commerceHandle($this->conversation, commerceInbound($this->conversation, 'Praveen'));
    $session->refresh();
    expect($session->step)->toBe('delivery_or_pickup');

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'fulfillment:pickup'));
    $session->refresh();
    expect($session->step)->toBe('payment_method');

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'payment:cod'));
    $session->refresh();
    expect($session->step)->toBe('completed');
    expect($session->status)->toBe('completed');
    expect($session->order_id)->not->toBeNull();

    $order = Order::find($session->order_id);
    expect($order)->not->toBeNull();
    expect($order->grand_total)->toBe(20000);
    expect($order->fulfillment_type)->toBe('pickup');
    expect($order->payment_method)->toBe('cod');
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->quantity)->toBe(2);

    Queue::assertPushed(SendOrderConfirmationMessage::class, fn ($job) => $job->orderId === $order->id);
});

it('prices delivery by zone and stamps coordinates on the order when the customer shares a location', function () {
    \App\Models\DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'radius_km' => 5,
        'delivery_charge' => 4500,
        'sort_order' => 0,
    ]);
    $this->branch->update(['latitude' => 12.9716, 'longitude' => 77.5946]);

    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'category:'.$this->category->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'product:'.$this->product->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '1'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'cart:checkout'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'Praveen'));

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'fulfillment:delivery'));
    $session->refresh();
    expect($session->step)->toBe('delivery_or_pickup');

    // ~1km from the branch, inside the 5km zone.
    commerceHandle($this->conversation, commerceLocationInbound($this->conversation, 12.98, 77.5946));
    $session->refresh();
    expect($session->step)->toBe('payment_method');
    expect($session->context['fulfillment']['delivery_charge'])->toBe(4500);

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'payment:cod'));
    $session->refresh();

    $order = Order::find($session->order_id);
    expect($order->fulfillment_type)->toBe('delivery');
    expect($order->delivery_charge)->toBe(4500);
    expect((float) $order->delivery_lat)->toBe(12.98);
    expect((float) $order->delivery_lng)->toBe(77.5946);
});

it('rejects a shared location outside every configured radius zone instead of silently falling back to a flat charge', function () {
    \App\Models\DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'radius_km' => 5,
        'delivery_charge' => 4500,
        'sort_order' => 0,
    ]);
    $this->branch->update(['latitude' => 12.9716, 'longitude' => 77.5946]);

    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'category:'.$this->category->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'product:'.$this->product->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '1'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'cart:checkout'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'Praveen'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'fulfillment:delivery'));

    // ~500km from the branch, far outside the 5km zone and no flat zone configured.
    commerceHandle($this->conversation, commerceLocationInbound($this->conversation, 17.385, 78.4867));
    $session->refresh();
    expect($session->step)->toBe('delivery_or_pickup');
    expect($session->context['customer']['delivery_address'] ?? null)->toBeNull();

    $reply = lastOutbound($this->conversation);
    expect($reply->text)->toContain('outside our delivery area');

    // Customer can still switch to pickup after being rejected.
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'pickup'));
    $session->refresh();
    expect($session->step)->toBe('payment_method');
    expect($session->context['fulfillment']['type'])->toBe('pickup');
});

it('blocks selecting a fulfillment type when the cart contains an item unavailable for it', function () {
    $this->product->update(['delivery_available' => false]);

    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'category:'.$this->category->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'product:'.$this->product->id));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '1'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'cart:checkout'));
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'Praveen'));

    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'fulfillment:delivery'));
    $session->refresh();
    expect($session->step)->toBe('delivery_or_pickup');
    expect($session->context['fulfillment'] ?? null)->toBeNull();

    $reply = lastOutbound($this->conversation);
    expect($reply->text)->toContain("aren't available for delivery");
    expect($reply->text)->toContain($this->product->name);

    // Pickup still works since only delivery_available was disabled.
    commerceHandle($this->conversation, commerceInbound($this->conversation, '', 'fulfillment:pickup'));
    $session->refresh();
    expect($session->step)->toBe('payment_method');
});

it('resumes an active session by dispatching to its current step handler rather than falling through', function () {
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();
    expect($session->step)->toBe('category_browse');

    $handled = commerceHandle($this->conversation, commerceInbound($this->conversation, 'garbage input mid-flow'));

    expect($handled)->toBeTrue();
    $session->refresh();
    expect($session->status)->toBe('active');
    expect($session->step)->toBe('category_browse');
});

it('cancels an active session on an exit keyword', function () {
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    $session = OrderSession::query()->where('conversation_id', $this->conversation->id)->first();

    $handled = commerceHandle($this->conversation, commerceInbound($this->conversation, 'cancel'));

    expect($handled)->toBeTrue();
    $session->refresh();
    expect($session->status)->toBe('abandoned');
});

it('enforces only one active session per conversation via the unique constraint', function () {
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));

    expect(OrderSession::query()->where('conversation_id', $this->conversation->id)->where('status', 'active')->count())->toBe(1);

    // A second trigger while a session is already active resumes (goes through resumeSession), not a new create.
    commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));

    expect(OrderSession::query()->where('conversation_id', $this->conversation->id)->where('status', 'active')->count())->toBe(1);
});

it('gracefully resumes the winning session when two inbound webhooks race to create one for the same conversation', function () {
    // Simulate two webhook jobs racing on maybeStartSession(): both pass the
    // "no active session yet" check before either has created a row, then
    // both attempt OrderSession::create(). We model this directly (rather
    // than through two real concurrent connections, which a single in-memory
    // SQLite connection can't represent) by pre-committing the "winner" row
    // via a raw insert and then invoking the engine's private
    // maybeStartSession() through reflection -- bypassing handle()'s own
    // "already active" short-circuit exactly as the real loser's in-flight
    // check already had. This proves the QueryException catch in
    // maybeStartSession() resumes the winner's session instead of letting
    // the unique-constraint violation blow up the job.
    $inbound = commerceInbound($this->conversation, 'hi');

    DB::table('order_sessions')->insert([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->company->id,
        'conversation_id' => $this->conversation->id,
        'contact_id' => $this->contact->id,
        'branch_id' => $this->branch->id,
        'api_connection_id' => null,
        'status' => 'active',
        'step' => 'welcome',
        'context' => '[]',
        'last_interaction_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $engine = app(CommerceOrderEngine::class);
    $method = new ReflectionMethod($engine, 'maybeStartSession');
    $method->setAccessible(true);

    $handled = $method->invoke($engine, $this->conversation, $inbound);

    expect($handled)->toBeTrue();

    $sessions = OrderSession::query()->where('conversation_id', $this->conversation->id)->get();
    expect($sessions)->toHaveCount(1);
    expect($sessions->first()->status)->toBe('active');
});

it('uses configured trigger keywords instead of defaults when commerce_settings has them', function () {
    CommerceSetting::factory()->create([
        'company_id' => $this->company->id,
        'settings' => ['trigger_keywords' => ['start shopping']],
    ]);

    $handledDefault = commerceHandle($this->conversation, commerceInbound($this->conversation, 'hi'));
    expect($handledDefault)->toBeFalse();

    $handledCustom = commerceHandle($this->conversation, commerceInbound($this->conversation, 'start shopping'));
    expect($handledCustom)->toBeTrue();
});
