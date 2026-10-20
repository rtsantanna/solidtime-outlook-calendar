<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * Microsoft Graph or Entra ID is unreachable or answered with an unexpected error.
 */
class OutlookUnavailableApiException extends ApiException
{
    public const string KEY = 'outlook_unavailable';

    public function getTranslatedMessage(): string
    {
        return __('Could not read the Outlook calendar. Please try again later.');
    }
}
