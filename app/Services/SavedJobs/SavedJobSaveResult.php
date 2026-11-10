<?php

namespace App\Services\SavedJobs;

use App\Models\SavedJob;

/**
 * Status und (bei Created/AlreadySaved) der betroffene Eintrag.
 */
final readonly class SavedJobSaveResult
{
    public function __construct(public SavedJobSaveStatus $status, public ?SavedJob $savedJob = null) {}
}
