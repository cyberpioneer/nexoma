<?php

use App\Http\Requests\SearchJobsRequest;
use App\Mcp\Servers\NexomaServer;
use App\Mcp\Tools\JobSearchOptionsTool;
use App\Mcp\Tools\JobSearchTool;
use App\Models\User;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Support\Facades\Http;

test('the options need no arguments, make no http call and list the offer types', function () {
    Http::preventStrayRequests();

    $response = NexomaServer::actingAs(User::factory()->create())->tool(JobSearchOptionsTool::class, []);

    $response->assertOk()->assertStructuredContent(fn ($json) => $json
        ->has('offerTypes', 4)
        ->where('offerTypes.0', ['angebotsart' => 1, 'key' => 'ARBEIT', 'description' => 'Regular employment (also part-time and temporary work).'])
        ->where('offerTypes.3.key', 'PRAKTIKUM_TRAINEE')
        ->where('radiusKm', ['min' => 0, 'max' => 200])
        ->where('publishedWithinDays', ['min' => 0, 'max' => 100])
        ->where('pageSize', ['default' => 10, 'max' => 25])
        ->has('berufsfeldHint')
        ->etc());
    Http::assertNothingSent();
});

test('the offer type ids equal the api client constant', function () {
    $content = NexomaServer::actingAs(User::factory()->create())->tool(JobSearchOptionsTool::class, []);
    $ids = [];
    $content->assertStructuredContent(function ($json) use (&$ids) {
        $json->etc();
        $ids = array_column($json->toArray()['offerTypes'], 'angebotsart');
    });

    expect($ids)->toBe(JobsucheApiClient::ALLOWED_OFFER_TYPES);
});

test('the limits match the search validation rules', function () {
    $rules = (new SearchJobsRequest)->rules();

    expect($rules['umkreis'])->toContain('between:0,200')
        ->and($rules['veroeffentlichtseit'])->toContain('between:0,100')
        ->and(JobSearchTool::MAX_PAGE_SIZE)->toBe(25)
        ->and(JobSearchTool::DEFAULT_PAGE_SIZE)->toBe(10);
});
