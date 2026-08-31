<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AiAssistantSettingController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ApiConnectionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AutomationFlowController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\ChatMenuFlowController;
use App\Http\Controllers\Api\V1\ChatbotTrainingEntryController;
use App\Http\Controllers\Api\V1\CompanyAdminController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DailyMetricController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PhonebookFolderController;
use App\Http\Controllers\Api\V1\PipelineStageController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\VoiceAgentController;
use App\Http\Controllers\Api\V1\VoiceCallController;
use App\Http\Controllers\Api\V1\WhatsappFlowController;
use App\Http\Controllers\Api\V1\WhatsappTemplateController;
use App\Http\Controllers\Api\InstagramWebhookController;
use App\Http\Controllers\Api\TwilioVoiceWebhookController;
use App\Http\Controllers\Api\V1\WhatsappCallController;
use App\Http\Controllers\Api\V1\WhatsappCallFlowController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WhatsappCallWebhookController;
use App\Http\Controllers\Api\Widget\WidgetController;
use App\Http\Middleware\EnsureValidWidgetKey;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function () {
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'handle']);
    Route::get('/instagram', [InstagramWebhookController::class, 'verify']);
    Route::post('/instagram', [InstagramWebhookController::class, 'handle']);
    Route::post('/whatsapp-call', [WhatsappCallWebhookController::class, 'handle']);
    Route::post('/whatsapp-call/action', [WhatsappCallWebhookController::class, 'action']);

    Route::prefix('twilio')->group(function () {
        Route::post('/voice', [TwilioVoiceWebhookController::class, 'handle']);
        Route::post('/voice/gather', [TwilioVoiceWebhookController::class, 'gather']);
        Route::post('/voice/status', [TwilioVoiceWebhookController::class, 'status']);
        Route::post('/voice/recording', [TwilioVoiceWebhookController::class, 'recording']);
        Route::match(['get', 'post'], '/voice/outbound-connect/{voiceCall}', [TwilioVoiceWebhookController::class, 'outboundConnect']);
    });
});

// Public widget API: authenticated via a per-chatbot, non-secret "widget key"
// (X-Widget-Key header) + origin allow-list, not Sanctum. Laravel's built-in
// CORS handling (config/cors.php) is a single global policy matched against
// `paths`, and the dashboard's policy is intentionally locked to FRONTEND_URL
// with credentialed cookies — widening it here would loosen the authenticated
// v1 API too. So widget/* is deliberately left OUT of cors.php, and CORS
// (including the OPTIONS preflight) is handled inline by EnsureValidWidgetKey
// and the catch-all OPTIONS route below, scoped only to this prefix.
Route::prefix('widget')->middleware(['throttle:widget', EnsureValidWidgetKey::class])->group(function () {
    Route::get('/bootstrap', [WidgetController::class, 'bootstrap']);
    Route::post('/messages', [WidgetController::class, 'sendMessage']);
    Route::get('/messages', [WidgetController::class, 'messages']);
    Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/bootstrap-status', [AuthController::class, 'bootstrapStatus']);
    Route::post('/auth/setup-superadmin', [AuthController::class, 'setupSuperadmin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/profile', [AuthController::class, 'updateProfile']);

        Route::get('/pipeline-stages', [PipelineStageController::class, 'index']);
        Route::get('/analytics/pipeline-funnel', [AnalyticsController::class, 'pipelineFunnel']);
        Route::get('/analytics/dashboard-summary', [AnalyticsController::class, 'dashboardSummary']);

        Route::get('/contacts', [ContactController::class, 'index']);
        Route::post('/contacts', [ContactController::class, 'store']);
        Route::get('/contacts/export', [ContactController::class, 'export']);
        Route::get('/contacts/{contact}', [ContactController::class, 'show']);
        Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
        Route::patch('/contacts/{contact}/pipeline-stage', [ContactController::class, 'updatePipelineStage']);
        Route::post('/contacts/{contact}/tags', [ContactController::class, 'addTag']);

        Route::get('/phonebook-folders/template', [PhonebookFolderController::class, 'template']);
        Route::post('/phonebook-folders/import', [PhonebookFolderController::class, 'import']);
        Route::get('/phonebook-folders', [PhonebookFolderController::class, 'index']);
        Route::post('/phonebook-folders', [PhonebookFolderController::class, 'store']);
        Route::get('/phonebook-folders/{phonebookFolder}', [PhonebookFolderController::class, 'show']);
        Route::patch('/phonebook-folders/{phonebookFolder}', [PhonebookFolderController::class, 'update']);
        Route::delete('/phonebook-folders/{phonebookFolder}', [PhonebookFolderController::class, 'destroy']);
        Route::get('/phonebook-folders/{phonebookFolder}/export', [PhonebookFolderController::class, 'export']);
        Route::get('/phonebook-folders/{phonebookFolder}/contacts', [PhonebookFolderController::class, 'contacts']);
        Route::post('/phonebook-folders/{phonebookFolder}/contacts', [PhonebookFolderController::class, 'addContacts']);
        Route::delete('/phonebook-folders/{phonebookFolder}/contacts/{contact}', [PhonebookFolderController::class, 'removeContact']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
        Route::patch('/conversations/{conversation}/status', [ConversationController::class, 'updateStatus']);
        Route::patch('/conversations/{conversation}/assign', [ConversationController::class, 'assign']);

        Route::get('/automation-flows', [AutomationFlowController::class, 'index']);
        Route::post('/automation-flows', [AutomationFlowController::class, 'store']);
        Route::patch('/automation-flows/{automationFlow}', [AutomationFlowController::class, 'update']);
        Route::patch('/automation-flows/{automationFlow}/status', [AutomationFlowController::class, 'updateStatus']);
        Route::delete('/automation-flows/{automationFlow}', [AutomationFlowController::class, 'destroy']);

        Route::get('/campaigns/dashboard', [CampaignController::class, 'dashboard']);
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::get('/campaigns/{campaign}/recipients', [CampaignController::class, 'recipients']);
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);

        Route::get('/daily-metrics', [DailyMetricController::class, 'index']);

        Route::get('/assignable-members', [TeamMemberController::class, 'assignable']);

        Route::get('/team-members', [TeamMemberController::class, 'index']);
        Route::post('/team-members', [TeamMemberController::class, 'store']);
        Route::patch('/team-members/{teamMember}/role', [TeamMemberController::class, 'updateRole']);
        Route::delete('/team-members/{teamMember}', [TeamMemberController::class, 'destroy']);

        Route::get('/api-connections', [ApiConnectionController::class, 'index']);
        Route::patch('/api-connections/{apiConnection}', [ApiConnectionController::class, 'update']);
        Route::patch('/api-connections/{apiConnection}/calling', [ApiConnectionController::class, 'toggleCalling']);
        Route::get('/api-connections/{apiConnection}/templates', [WhatsappTemplateController::class, 'index']);
        Route::post('/api-connections/{apiConnection}/templates/sync', [WhatsappTemplateController::class, 'sync']);
        Route::post('/api-connections/{apiConnection}/templates', [WhatsappTemplateController::class, 'store']);
        Route::patch('/templates/{whatsappTemplate}', [WhatsappTemplateController::class, 'update']);
        Route::post('/templates/{whatsappTemplate}/submit', [WhatsappTemplateController::class, 'submit']);
        Route::delete('/templates/{whatsappTemplate}', [WhatsappTemplateController::class, 'destroy']);
        Route::get('/api-connections/{apiConnection}/flows', [WhatsappFlowController::class, 'index']);
        Route::post('/api-connections/{apiConnection}/flows/sync', [WhatsappFlowController::class, 'sync']);
        Route::get('/whatsapp-flows', [WhatsappFlowController::class, 'all']);

        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

        Route::get('/ai-assistant-settings', [AiAssistantSettingController::class, 'show']);
        Route::patch('/ai-assistant-settings', [AiAssistantSettingController::class, 'update']);
        Route::post('/ai-assistant/chat', [AiAssistantSettingController::class, 'chat']);

        Route::get('/chatbots', [ChatbotController::class, 'index']);
        Route::post('/chatbots', [ChatbotController::class, 'store']);
        Route::get('/chatbots/{chatbot}', [ChatbotController::class, 'show']);
        Route::patch('/chatbots/{chatbot}', [ChatbotController::class, 'update']);
        Route::delete('/chatbots/{chatbot}', [ChatbotController::class, 'destroy']);
        Route::get('/chatbots/{chatbot}/training-entries', [ChatbotTrainingEntryController::class, 'index']);
        Route::post('/chatbots/{chatbot}/training-entries', [ChatbotTrainingEntryController::class, 'store']);
        Route::post('/chatbots/{chatbot}/training-entries/generate', [ChatbotTrainingEntryController::class, 'generate']);
        Route::patch('/chatbots/{chatbot}/training-entries/{trainingEntry}', [ChatbotTrainingEntryController::class, 'update']);
        Route::delete('/chatbots/{chatbot}/training-entries/{trainingEntry}', [ChatbotTrainingEntryController::class, 'destroy']);

        Route::get('/voice-agents', [VoiceAgentController::class, 'index']);
        Route::post('/voice-agents', [VoiceAgentController::class, 'store']);
        Route::get('/voice-agents/{voiceAgent}', [VoiceAgentController::class, 'show']);
        Route::patch('/voice-agents/{voiceAgent}', [VoiceAgentController::class, 'update']);
        Route::delete('/voice-agents/{voiceAgent}', [VoiceAgentController::class, 'destroy']);

        Route::get('/voice-calls', [VoiceCallController::class, 'index']);
        Route::get('/voice-calls/{voiceCall}', [VoiceCallController::class, 'show']);
        Route::post('/voice-calls/simulated', [VoiceCallController::class, 'logSimulatedCall']);
        Route::patch('/voice-calls/{voiceCall}/followup', [VoiceCallController::class, 'assignFollowup']);
        Route::patch('/voice-calls/{voiceCall}/followup/complete', [VoiceCallController::class, 'completeFollowup']);

        Route::get('/whatsapp-call-flows', [WhatsappCallFlowController::class, 'index']);
        Route::post('/whatsapp-call-flows', [WhatsappCallFlowController::class, 'store']);
        Route::get('/whatsapp-call-flows/{whatsappCallFlow}', [WhatsappCallFlowController::class, 'show']);
        Route::patch('/whatsapp-call-flows/{whatsappCallFlow}', [WhatsappCallFlowController::class, 'update']);
        Route::delete('/whatsapp-call-flows/{whatsappCallFlow}', [WhatsappCallFlowController::class, 'destroy']);

        Route::get('/chat-menu-flows', [ChatMenuFlowController::class, 'index']);
        Route::post('/chat-menu-flows', [ChatMenuFlowController::class, 'store']);
        Route::post('/chat-menu-flows/generate', [ChatMenuFlowController::class, 'generate']);
        Route::get('/chat-menu-flows/{chatMenuFlow}', [ChatMenuFlowController::class, 'show']);
        Route::patch('/chat-menu-flows/{chatMenuFlow}', [ChatMenuFlowController::class, 'update']);
        Route::delete('/chat-menu-flows/{chatMenuFlow}', [ChatMenuFlowController::class, 'destroy']);

        Route::get('/whatsapp-calls', [WhatsappCallController::class, 'index']);
        Route::post('/whatsapp-calls', [WhatsappCallController::class, 'store']);
        Route::get('/whatsapp-calls/ice-servers', [WhatsappCallController::class, 'iceServers']);
        Route::get('/whatsapp-calls/{whatsappCall}', [WhatsappCallController::class, 'show']);
        Route::post('/whatsapp-calls/{whatsappCall}/offer', [WhatsappCallController::class, 'submitOffer']);
        Route::post('/whatsapp-calls/{whatsappCall}/hangup', [WhatsappCallController::class, 'hangup']);
        Route::post('/whatsapp-calls/{whatsappCall}/permission-request', [WhatsappCallController::class, 'requestCallPermission']);
        Route::patch('/whatsapp-calls/{whatsappCall}/followup', [WhatsappCallController::class, 'assignFollowup']);
        Route::patch('/whatsapp-calls/{whatsappCall}/followup/complete', [WhatsappCallController::class, 'completeFollowup']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/companies/{company}/admins', [CompanyAdminController::class, 'index']);
        Route::post('/companies/{company}/admins', [CompanyAdminController::class, 'store']);
    });
});
