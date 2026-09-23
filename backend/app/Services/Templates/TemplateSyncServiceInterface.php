<?php

namespace App\Services\Templates;

use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use Illuminate\Http\UploadedFile;

interface TemplateSyncServiceInterface
{
    /**
     * Fetch the current set of WhatsApp message templates for the given
     * connection's WhatsApp Business Account from Meta and return them as
     * plain arrays ready to be upserted into whatsapp_templates.
     *
     * Each element: ['meta_template_id', 'name', 'language', 'category', 'status', 'body', 'variables']
     *
     * @return array<int, array{meta_template_id: string, name: string, language: string, category: string, status: string, body: string, variables: array<int, string>}>
     */
    public function fetchTemplates(ApiConnection $connection): array;

    /**
     * Submit a draft template to Meta for review.
     *
     * @return array{meta_template_id: string, status: string}
     */
    public function submitTemplate(ApiConnection $connection, WhatsappTemplate $template): array;

    /**
     * Upload a file to Meta as template header media and return the
     * resulting upload handle to reference in a HEADER component's
     * example.header_handle.
     */
    public function uploadHeaderMedia(ApiConnection $connection, UploadedFile $file): string;

    /**
     * Push local edits of an already-submitted/synced template to Meta's
     * edit-template endpoint.
     *
     * @return array{status: string}
     */
    public function pushTemplateEdits(ApiConnection $connection, WhatsappTemplate $template): array;
}
