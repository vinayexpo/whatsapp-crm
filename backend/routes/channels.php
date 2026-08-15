<?php

use App\Models\Conversation;
use App\Models\VoiceCall;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{uuid}', function ($user, string $uuid) {
    $conversation = Conversation::query()->where('uuid', $uuid)->first();

    return $conversation && $user->can('view', $conversation);
});

Broadcast::channel('company.{companyUuid}.conversations', function ($user, string $companyUuid) {
    return $user->company && $user->company->uuid === $companyUuid;
});

Broadcast::channel('voice-call.{uuid}', function ($user, string $uuid) {
    $voiceCall = VoiceCall::query()->where('uuid', $uuid)->first();

    return $voiceCall && $voiceCall->conversation && $user->can('view', $voiceCall->conversation);
});

Broadcast::channel('whatsapp-call.{uuid}', function ($user, string $uuid) {
    $whatsappCall = WhatsappCall::query()->where('uuid', $uuid)->first();

    return $whatsappCall && $whatsappCall->conversation && $user->can('view', $whatsappCall->conversation);
});
