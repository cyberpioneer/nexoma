<?php

namespace App\Http\Controllers;

use App\Services\Legal\LegalDocuments;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function imprint(LegalDocuments $documents): Response
    {
        return $this->page($documents, 'imprint', 'impressum');
    }

    public function privacy(LegalDocuments $documents): Response
    {
        return $this->page($documents, 'privacy', 'datenschutzerklaerung');
    }

    public function disclaimer(LegalDocuments $documents): Response
    {
        return $this->page($documents, 'disclaimer', 'haftungsausschluss');
    }

    /**
     * Die Texte sind nur auf Deutsch vorhanden; `document` waehlt im Client den uebersetzten Seitentitel.
     */
    private function page(LegalDocuments $documents, string $document, string $file): Response
    {
        return Inertia::render('Legal/Show', [
            'document' => $document,
            'blocks' => $documents->blocks($file),
        ]);
    }
}
