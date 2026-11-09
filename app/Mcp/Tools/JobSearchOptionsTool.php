<?php

namespace App\Mcp\Tools;

use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('job_search_options')]
#[Title('Job search options')]
#[Description('List the allowed values and limits for job_search (offer types, radius, publication window, page size). Takes no arguments and does not contact any external service.')]
#[IsReadOnly(true)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class JobSearchOptionsTool extends NexoraTool
{
    /** @var array<int, array{key: string, description: string}> */
    private const OFFER_TYPES = [
        1 => ['key' => 'ARBEIT', 'description' => 'Regular employment (also part-time and temporary work).'],
        2 => ['key' => 'SELBSTAENDIGKEIT', 'description' => 'Self-employment and freelance work.'],
        4 => ['key' => 'AUSBILDUNG', 'description' => 'Vocational training and apprenticeships (dual studies).'],
        34 => ['key' => 'PRAKTIKUM_TRAINEE', 'description' => 'Internships, traineeships and work placements.'],
    ];

    protected function execute(Request $request): Response|ResponseFactory
    {
        $offerTypes = [];

        foreach (JobsucheApiClient::ALLOWED_OFFER_TYPES as $id) {
            $offerTypes[] = ['angebotsart' => $id, ...self::OFFER_TYPES[$id]];
        }

        return Response::structured([
            'offerTypes' => $offerTypes,
            'radiusKm' => ['min' => 0, 'max' => 200],
            'publishedWithinDays' => ['min' => 0, 'max' => 100],
            'pageSize' => ['default' => JobSearchTool::DEFAULT_PAGE_SIZE, 'max' => JobSearchTool::MAX_PAGE_SIZE],
            'berufsfeldHint' => 'Free text; this API has no fixed category list. Put the occupation into `was`; use `berufsfeld` only to narrow by occupational field name (e.g. "Pflege").',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
