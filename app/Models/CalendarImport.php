<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Outlook meeting the member already handled (imported as a time entry or ignored).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $member_id
 * @property string $event_key iCalUId + start (UTC)
 * @property string|null $time_entry_id
 * @property string $status imported|ignored
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Member $member
 * @property-read TimeEntry|null $timeEntry
 */
class CalendarImport extends Model
{
    use HasUuids;

    public const string STATUS_IMPORTED = 'imported';

    public const string STATUS_IGNORED = 'ignored';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'event_key' => 'string',
        'status' => 'string',
    ];

    /**
     * Imported and the time entry still exists (deleting the entry frees the meeting to be imported again).
     */
    public function isImported(): bool
    {
        return $this->status === self::STATUS_IMPORTED && $this->time_entry_id !== null;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<TimeEntry, $this>
     */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class, 'time_entry_id');
    }
}
