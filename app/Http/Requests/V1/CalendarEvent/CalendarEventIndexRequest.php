<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarEvent;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use App\Service\OutlookCalendarService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Meetings of the logged-in user's Outlook calendar in a period (days in the user's time zone).
 *
 * @property Organization $organization Organization from model binding
 */
class CalendarEventIndexRequest extends BaseFormRequest
{
    // disabled (ENTRA_CALENDAR_ENABLED=false) answers 404 before validating
    protected function prepareForValidation(): void
    {
        abort_unless(app(OutlookCalendarService::class)->isEnabled(), 404);
    }

    /**
     * @return array<string, array<string|ValidationRule|\Closure>>
     */
    public function rules(): array
    {
        return [
            // First day (inclusive), format Y-m-d, in the timezone of the user. Default: day of the last import (or 7 days ago)
            'start' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
            ],
            // Last day (inclusive), format Y-m-d, in the timezone of the user. Default: today
            'end' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
                'after_or_equal:start',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('start');
                    if (! is_string($value) || ! is_string($start) || $start === '') {
                        return;
                    }
                    $max = (int) config('services.entra_calendar.max_days', 62);
                    $from = Carbon::createFromFormat('Y-m-d', $start, 'UTC');
                    $to = Carbon::createFromFormat('Y-m-d', $value, 'UTC');
                    if ($from instanceof Carbon && $to instanceof Carbon && $from->diffInDays($to) > $max) {
                        $fail(__('The period can have at most :days days.', ['days' => $max]));
                    }
                },
            ],
        ];
    }

    public function getStartDate(): ?string
    {
        $start = $this->input('start');

        return is_string($start) && $start !== '' ? $start : null;
    }

    public function getEndDate(): ?string
    {
        $end = $this->input('end');

        return is_string($end) && $end !== '' ? $end : null;
    }
}
