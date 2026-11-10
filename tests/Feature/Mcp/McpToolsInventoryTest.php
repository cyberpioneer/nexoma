<?php

use App\Mcp\Servers\NexomaServer;
use App\Mcp\Tools\NexomaTool;

test('tools/list shows exactly the three read-only tools and save_job with title and description', function () {
    actingAsMcpUser();

    $tools = collect(mcpRequest('tools/list')->assertOk()->json('result.tools'));

    expect($tools->pluck('name')->all())->toBe(['job_search', 'job_details', 'job_search_options', 'save_job']);

    foreach ($tools as $tool) {
        expect($tool['title'])->not->toBeEmpty()->and($tool['description'])->not->toBeEmpty();
    }

    foreach ($tools->reject(fn (array $tool): bool => $tool['name'] === 'save_job') as $tool) {
        expect($tool['annotations'])->toMatchArray(['readOnlyHint' => true, 'idempotentHint' => true, 'destructiveHint' => false]);
    }

    expect($tools->firstWhere('name', 'job_search')['annotations']['openWorldHint'])->toBeTrue()
        ->and($tools->firstWhere('name', 'job_details')['annotations']['openWorldHint'])->toBeTrue()
        ->and($tools->firstWhere('name', 'job_search_options')['annotations']['openWorldHint'])->toBeFalse();
});

test('the job_search schema has the specified bounds', function () {
    actingAsMcpUser();

    $schema = collect(mcpRequest('tools/list')->json('result.tools'))->firstWhere('name', 'job_search')['inputSchema'];

    expect($schema['required'])->toBe(['was'])
        ->and($schema['properties']['size'])->toMatchArray(['minimum' => 1, 'maximum' => 25])
        ->and($schema['properties']['umkreis'])->toMatchArray(['minimum' => 0, 'maximum' => 200])
        ->and($schema['properties']['angebotsart']['enum'])->toBe([1, 2, 4, 34])
        ->and($schema['properties']['was']['maxLength'])->toBe(255);
});

test('every tool of the server extends the audited base class and the server has no resources or prompts', function () {
    $reflection = new ReflectionClass(NexomaServer::class);
    $defaults = $reflection->getDefaultProperties();
    $tools = $defaults['tools'];

    expect($tools)->toHaveCount(4);

    foreach ($tools as $tool) {
        expect(is_subclass_of($tool, NexomaTool::class))->toBeTrue()
            ->and((new ReflectionMethod($tool, 'handle'))->isFinal())->toBeTrue();
    }

    expect($defaults['resources'])->toBe([])->and($defaults['prompts'])->toBe([]);
});
