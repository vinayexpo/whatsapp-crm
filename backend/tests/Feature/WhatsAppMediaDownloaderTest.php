<?php

use App\Models\ApiConnection;
use App\Services\Messaging\WhatsAppMediaDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads inbound media to the public disk and returns its url and type', function () {
    Storage::fake('public');

    Http::fake([
        'graph.facebook.com/v20.0/media-1' => Http::response(['url' => 'https://lookaside.fbsbx.com/file/media-1'], 200),
        'lookaside.fbsbx.com/*' => Http::response('binary-image-bytes', 200),
    ]);

    $connection = ApiConnection::factory()->make(['access_token' => 'token']);

    $result = app(WhatsAppMediaDownloader::class)->download('media-1', 'image/jpeg', $connection);

    expect($result)->not->toBeNull();
    expect($result['type'])->toBe('image');
    expect($result['url'])->toContain('/storage/inbound-media/');
    expect(str_ends_with($result['url'], '.jpeg'))->toBeTrue();

    $stored = Storage::disk('public')->files('inbound-media');
    expect(count($stored))->toBe(1);
    expect(Storage::disk('public')->get($stored[0]))->toBe('binary-image-bytes');
});

it('uses a clean extension for mime types with parameters', function () {
    Storage::fake('public');

    Http::fake([
        'graph.facebook.com/v20.0/media-2' => Http::response(['url' => 'https://lookaside.fbsbx.com/file/media-2'], 200),
        'lookaside.fbsbx.com/*' => Http::response('audio-bytes', 200),
    ]);

    $connection = ApiConnection::factory()->make(['access_token' => 'token']);

    $result = app(WhatsAppMediaDownloader::class)->download('media-2', 'audio/ogg; codecs=opus', $connection);

    expect($result)->not->toBeNull();
    expect(str_ends_with($result['url'], '.ogg'))->toBeTrue();
    expect(str_contains($result['url'], ' '))->toBeFalse();
    expect($result['type'])->toBe('audio');
});

it('returns null when the media url cannot be resolved', function () {
    Storage::fake('public');

    Http::fake([
        'graph.facebook.com/v20.0/media-3' => Http::response(['error' => ['message' => 'not found']], 404),
    ]);

    $connection = ApiConnection::factory()->make(['access_token' => 'token']);

    $result = app(WhatsAppMediaDownloader::class)->download('media-3', 'image/jpeg', $connection);

    expect($result)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
