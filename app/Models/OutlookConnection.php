<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's connection to their own Outlook calendar (Microsoft Graph, delegated flow).
 * Tokens are encrypted with APP_KEY and never go to audits, logs or API responses.
 *
 * @property string $id
 * @property string $user_id
 * @property string $refresh_token
 * @property string|null $access_token
 * @property Carbon|null $access_token_expires_at
 * @property string|null $account_email
 * @property Carbon $connected_at
 * @property Carbon|null $last_import_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class OutlookConnection extends Model
{
    use HasUuids;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'refresh_token' => 'encrypted',
        'access_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'account_email' => 'string',
        'connected_at' => 'datetime',
        'last_import_at' => 'datetime',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'refresh_token',
        'access_token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
