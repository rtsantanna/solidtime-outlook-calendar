<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * Entra ID rejected the refresh token (revoked, expired or password changed); the connection was removed.
 */
class OutlookReconnectRequiredApiException extends ApiException
{
    public const string KEY = 'outlook_reconnect_required';

    public function getTranslatedMessage(): string
    {
        return __('The Outlook connection has expired. Please connect again.');
    }
}
