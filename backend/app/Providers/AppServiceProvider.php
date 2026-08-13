<?php

namespace App\Providers;

use App\Services\Calling\FakeWhatsappCallService;
use App\Services\Calling\GraphApiWhatsappCallService;
use App\Services\Calling\WhatsappCallServiceInterface;
use App\Services\Chatbot\ChatbotReplyServiceInterface;
use App\Services\Chatbot\FakeChatbotReplyService;
use App\Services\Chatbot\FakeTrainingEntryGeneratorService;
use App\Services\Chatbot\OpenAiChatbotReplyService;
use App\Services\Chatbot\OpenAiTrainingEntryGeneratorService;
use App\Services\Chatbot\TrainingEntryGeneratorServiceInterface;
use App\Services\Notifications\FakePushNotificationService;
use App\Services\Notifications\PushNotificationServiceInterface;
use App\Services\Notifications\WebPushNotificationService;
use App\Services\Voice\FakeVoiceCallQualificationService;
use App\Services\Voice\FakeVoiceCallService;
use App\Services\Voice\OpenAiVoiceCallQualificationService;
use App\Services\Voice\TwilioVoiceCallService;
use App\Services\Voice\VoiceCallQualificationServiceInterface;
use App\Services\Voice\VoiceCallServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChatbotReplyServiceInterface::class, function () {
            return $this->app->environment('testing')
                ? new FakeChatbotReplyService
                : new OpenAiChatbotReplyService;
        });

        $this->app->bind(TrainingEntryGeneratorServiceInterface::class, function () {
            return $this->app->environment('testing')
                ? new FakeTrainingEntryGeneratorService
                : new OpenAiTrainingEntryGeneratorService;
        });

        $this->app->bind(VoiceCallServiceInterface::class, function () {
            return $this->app->environment('testing') ? new FakeVoiceCallService : new TwilioVoiceCallService;
        });

        $this->app->bind(VoiceCallQualificationServiceInterface::class, function () {
            return $this->app->environment('testing')
                ? new FakeVoiceCallQualificationService
                : new OpenAiVoiceCallQualificationService;
        });

        $this->app->bind(WhatsappCallServiceInterface::class, function () {
            return $this->app->environment('testing') ? new FakeWhatsappCallService : new GraphApiWhatsappCallService;
        });

        $this->app->bind(PushNotificationServiceInterface::class, function () {
            return $this->app->environment('testing')
                ? new FakePushNotificationService
                : new WebPushNotificationService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Railway's edge proxy terminates TLS and forwards plain HTTP to the
        // container, so the request Laravel sees always looks like http://.
        // Force https so url()/route() generate the correct public scheme
        // (e.g. webhook URLs shown in Settings) instead of trusting the
        // request's apparent scheme.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('widget', function (Request $request) {
            return Limit::perMinute(30)->by($request->header('X-Widget-Key') ?? $request->ip());
        });

        // The public widget/* routes live under the same api/* URL prefix that
        // config/cors.php's single global CORS policy (locked to FRONTEND_URL,
        // credentialed) applies to. Skip Laravel's built-in CORS handling for
        // widget/* entirely — EnsureValidWidgetKey sets its own permissive,
        // non-credentialed CORS headers for that prefix instead, so the
        // dashboard's authenticated CORS policy stays untouched.
        HandleCors::skipWhen(function (Request $request) {
            return $request->is('api/widget/*') || $request->is('api/widget');
        });
    }
}
