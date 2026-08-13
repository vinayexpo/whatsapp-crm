<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'channel' => $this->channel,
            'status' => $this->status,
            'message' => $this->message,
            'attachmentUrl' => $this->attachment_url,
            'attachmentType' => $this->attachment_type,
            'templateId' => $this->whatsappTemplate?->uuid,
            'templateVariables' => $this->template_variables,
            'audienceTag' => $this->audience_tag,
            'phonebookFolderId' => $this->phonebookFolder?->uuid,
            'phonebookFolderName' => $this->phonebookFolder?->name,
            'recipientCount' => $this->recipient_count,
            'deliveredCount' => $this->delivered_count,
            'readCount' => $this->read_count,
            'repliedCount' => $this->replied_count,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'voiceAgentId' => $this->voiceAgent?->uuid,
            'voiceAgentName' => $this->voiceAgent?->name,
            'whatsappCallFlowId' => $this->whatsappCallFlow?->uuid,
            'whatsappCallFlowName' => $this->whatsappCallFlow?->name,
        ];
    }
}
