<?php

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('marks the webhook event failed and records the error when the job fails', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => ['entry' => []],
    ]);

    $job = new ProcessInboundWhatsAppMessage($event->id);
    $job->failed(new Exception('boom'));

    $event->refresh();
    expect($event->status)->toBe('failed');
    expect($event->error)->toBe('boom');
    expect($event->attempts)->toBe(1);
});

it('notifies team.manage users with a job_failed notification when a job fails', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => ['entry' => []],
    ]);

    $job = new ProcessInboundWhatsAppMessage($event->id);
    $job->failed(new Exception('something broke'));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $admin->id,
        'type' => 'job_failed',
    ]);
});

it('does not notify a manager who lacks team.manage permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $company = Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => ['entry' => []],
    ]);

    $job = new ProcessInboundWhatsAppMessage($event->id);
    $job->failed(new Exception('something broke'));

    $this->assertDatabaseMissing('notifications', [
        'user_id' => $manager->id,
        'type' => 'job_failed',
    ]);
});

it('still marks the webhook event processed on the success path', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['wa_id' => '15551234567', 'profile' => ['name' => 'Jane Doe']],
                                ],
                                'messages' => [
                                    [
                                        'from' => '15551234567',
                                        'id' => 'wamid.OK1',
                                        'timestamp' => (string) now()->timestamp,
                                        'text' => ['body' => 'Hello there'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    expect($event->fresh()->status)->toBe('processed');
});
