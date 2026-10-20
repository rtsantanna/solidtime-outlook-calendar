<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarEvent;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use App\Service\OutlookCalendarService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mark Outlook meetings as ignored (they no longer show up as new).
 *
 * @property Organization $organization Organization from model binding
 */
class CalendarEventIgnoreRequest extends BaseFormRequest
{
    // disabled (ENTRA_CALENDAR_ENABLED=false) answers 404 before validating
    protected function prepareForValidation(): void
    {
        abort_unless(app(OutlookCalendarService::class)->isEnabled(), 404);
    }

    /**
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            // Key of the meeting as returned by the list endpoint (iCalUId + start)
            'items.*.event_key' => [
                'required',
                'string',
                'max:512',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function getEventKeys(): array
    {
        /** @var array<int, array{event_key: string}> $items */
        $items = $this->validated('items');

        return array_values(array_unique(array_map(fn (array $item): string => $item['event_key'], $items)));
    }
}
