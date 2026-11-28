<?php

use App\Models\AiAssistantSetting;
use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    $this->connection = ApiConnection::factory()->create(['company_id' => $this->company->id, 'channel' => 'whatsapp', 'access_token' => 'private-meta-token']);
    $this->path = "/api/v1/api-connections/{$this->connection->uuid}/templates/ai-draft";
    $this->input = ['brief' => 'Remind a customer about their requested appointment. No promotional offer.', 'language' => 'en_US', 'category' => 'utility', 'tone' => 'friendly'];
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

function templateAiSettings(int $companyId): void
{
    AiAssistantSetting::forceCreate(['company_id' => $companyId, 'base_url' => 'https://ai.example.test/v1', 'api_key' => 'private-ai-key', 'model' => 'company-model']);
}

function templateAiCopy(): array
{
    return ['name' => 'appointment_reminder', 'body' => 'Hi {{1}}, your requested appointment is on {{2}}. Reply if you need help.', 'headerText' => 'Your appointment', 'footerText' => 'Thank you', 'bodyExamples' => ['Alex', 'Monday at 10 am'], 'rationale' => 'A concise reminder about an existing service request.'];
}

function fakeTemplateAi(mixed $copy): void
{
    Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => is_array($copy) ? json_encode($copy) : $copy]]]])]);
}

it('generates a company-scoped editable proposal without saving submitting or sending to Meta', function () {
    templateAiSettings($this->company->id);
    fakeTemplateAi(templateAiCopy() + ['language' => 'es_ES', 'category' => 'marketing', 'status' => 'APPROVED', 'components' => [['type' => 'BUTTONS']]]);
    $response = $this->postJson($this->path, $this->input)->assertOk()
        ->assertJsonPath('data.proposal.language', 'en_US')->assertJsonPath('data.proposal.category', 'utility')
        ->assertJsonPath('data.proposal.bodyExamples.0', 'Alex')->assertJsonMissingPath('data.proposal.status');
    expect(WhatsappTemplate::count())->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer private-ai-key') && ! str_contains(json_encode($r->data()), 'private-meta-token'));
    $draft = $response->json('data.proposal');
    $this->postJson("/api/v1/api-connections/{$this->connection->uuid}/templates", [
        'name' => $draft['name'], 'language' => $draft['language'], 'category' => $draft['category'], 'body' => $draft['body'], 'variables' => ['1', '2'],
        'components' => [['type' => 'BODY', 'text' => $draft['body'], 'example' => ['body_text' => [$draft['bodyExamples']]]]],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.components.0.example.body_text.0.1', 'Monday at 10 am');
    Http::assertSentCount(1);
});

it('requires authentication and template management permission', function () {
    auth()->forgetGuards();
    $this->postJson($this->path, $this->input)->assertUnauthorized();
    $agent = User::factory()->create(['company_id' => $this->company->id]);
    $agent->assignRole('agent');
    $this->actingAs($agent)->postJson($this->path, $this->input)->assertForbidden();
    Http::assertNothingSent();
});

it('rejects connections from another company and non WhatsApp connections', function () {
    $other = ApiConnection::factory()->create(['company_id' => Company::factory()->create()->id, 'channel' => 'whatsapp']);
    $this->postJson("/api/v1/api-connections/{$other->uuid}/templates/ai-draft", $this->input)->assertNotFound();
    $this->connection->update(['channel' => 'instagram']);
    $this->postJson($this->path, $this->input)->assertUnprocessable();
    Http::assertNothingSent();
});

it('does not borrow another company AI provider or return a fake draft', function () {
    templateAiSettings(Company::factory()->create()->id);
    $this->postJson($this->path, $this->input)->assertUnprocessable()->assertJsonPath('errors.ai.0', 'Configure your company AI provider in AI Assistant settings before generating a template.');
    Http::assertNothingSent();
});

it('validates the brief language tone and supported categories before calling AI', function () {
    $this->postJson($this->path, array_replace($this->input, ['brief' => 'short', 'language' => 'invalid', 'category' => 'authentication', 'tone' => 'invented']))
        ->assertUnprocessable()->assertJsonValidationErrors(['brief', 'language', 'category', 'tone']);
    Http::assertNothingSent();
});

it('rejects malformed AI proposals without creating a template', function (string $failure) {
    templateAiSettings($this->company->id);
    $copy = templateAiCopy();
    match ($failure) {
        'json' => $copy = 'not json',
        'name' => $copy['name'] = 'Invalid Name',
        'oversize' => $copy['body'] = str_repeat('x', 1025),
        'sequence' => $copy['body'] = 'Hi {{1}}, your request {{3}} is confirmed.',
        'named' => $copy['body'] = 'Hello {{name}}, please check your request.',
        'examples' => $copy['bodyExamples'] = ['Alex'],
        'dangling' => $copy['body'] = '{{1}}, your requested appointment is on {{2}}.',
        'header' => $copy['headerText'] = 'Hello {{1}}',
    };
    fakeTemplateAi($copy);
    $this->postJson($this->path, $this->input)->assertUnprocessable();
    expect(WhatsappTemplate::count())->toBe(0);
})->with(['json', 'name', 'oversize', 'sequence', 'named', 'examples', 'dangling', 'header']);

it('returns an actionable provider failure with no saved draft', function () {
    templateAiSettings($this->company->id);
    Http::fake(['ai.example.test/*' => Http::response(['error' => 'Unavailable'], 503)]);
    $this->postJson($this->path, $this->input)->assertUnprocessable()->assertJsonValidationErrors('ai');
    expect(WhatsappTemplate::count())->toBe(0);
});
