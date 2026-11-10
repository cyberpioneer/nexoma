<?php

namespace App\Services\SavedJobs;

/**
 * Ergebnis eines Speichervorgangs, siehe {@see SavedJobSaver}.
 */
enum SavedJobSaveStatus
{
    /** Neuer Eintrag angelegt. */
    case Created;

    /** Eintrag existierte bereits; hoechstens der Vermerk wurde aktualisiert. */
    case AlreadySaved;

    /** Hoechstzahl je Benutzer erreicht, nichts gespeichert. */
    case LimitReached;

    /** BA-API lieferte keine Details, nichts gespeichert. */
    case Unavailable;
}
