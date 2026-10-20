<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * The user has not connected their Outlook calendar yet.
 */
class OutlookNotConnectedApiException extends ApiException
{
    public const string KEY = 'outlook_not_connected';

    public function getTranslatedMessage(): string
    {
        return __('Outlook is not connected. Connect your calendar first.');
    }
}
