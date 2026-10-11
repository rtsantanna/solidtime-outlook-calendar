import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { trans, transChoice } from 'laravel-vue-i18n';
import {
    api,
    type CreateTimeEntryBody,
    type TimeEntry,
    type UpdateMultipleTimeEntriesChangeset,
} from '@/packages/api/src';
import { getCurrentMembershipId, getCurrentOrganizationId } from '@/utils/useUser';
import { useNotificationsStore } from '@/utils/notification';

export function useTimeEntriesMutations() {
    const queryClient = useQueryClient();
    const { handleApiRequestNotifications, addNotification } = useNotificationsStore();

    const { mutateAsync: createTimeEntry } = useMutation({
        mutationFn: async (timeEntry: Omit<CreateTimeEntryBody, 'member_id'>) => {
            const organizationId = getCurrentOrganizationId();
            const memberId = getCurrentMembershipId();
            if (organizationId && memberId !== undefined) {
                const newTimeEntry = {
                    ...timeEntry,
                    member_id: memberId,
                } as CreateTimeEntryBody;

                return await handleApiRequestNotifications(
                    () =>
                        api.createTimeEntry(newTimeEntry, {
                            params: {
                                organization: organizationId,
                            },
                        }),
                    trans('Time entry created successfully'),
                    trans('Failed to create time entry')
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: updateTimeEntry } = useMutation({
        mutationFn: async (timeEntry: TimeEntry) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.updateTimeEntry(timeEntry, {
                            params: {
                                organization: organizationId,
                                timeEntry: timeEntry.id,
                            },
                        }),
                    trans('Time entry updated successfully'),
                    trans('Failed to update time entry')
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: updateTimeEntries } = useMutation({
        mutationFn: async ({
            ids,
            changes,
        }: {
            ids: string[];
            changes: UpdateMultipleTimeEntriesChangeset;
        }) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                const response = await handleApiRequestNotifications(
                    () =>
                        api.updateMultipleTimeEntries(
                            {
                                ids: ids,
                                changes: changes,
                            },
                            {
                                params: {
                                    organization: organizationId,
                                },
                            }
                        ),
                    undefined,
                    trans('Failed to update time entries')
                );
                // The endpoint applies the changeset per entry and skips entries it can't
                // apply it to (e.g. breaks with a project/tags/billable change) — a 200
                // with their ids in `error`. Surface that instead of claiming success.
                const skippedCount = response?.error.length ?? 0;
                if (skippedCount > 0) {
                    addNotification(
                        'error',
                        transChoice(
                            ':skipped of :total time entries was skipped|:skipped of :total time entries were skipped',
                            skippedCount,
                            { skipped: String(skippedCount), total: String(ids.length) }
                        ),
                        trans(
                            'No changes were applied to the skipped entries — break entries can not have a project or tags, or be billable.'
                        )
                    );
                } else {
                    addNotification('success', trans('Time entries updated successfully'));
                }
                return response;
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: deleteTimeEntry } = useMutation({
        mutationFn: async (timeEntryId: string) => {
            const organizationId = getCurrentOrganizationId();
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.deleteTimeEntry(undefined, {
                            params: {
                                organization: organizationId,
                                timeEntry: timeEntryId,
                            },
                        }),
                    trans('Time entry deleted successfully'),
                    trans('Failed to delete time entry')
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    const { mutateAsync: deleteTimeEntries } = useMutation({
        mutationFn: async (timeEntries: TimeEntry[]) => {
            const organizationId = getCurrentOrganizationId();
            const timeEntryIds = timeEntries.map((entry) => entry.id);
            if (organizationId) {
                return await handleApiRequestNotifications(
                    () =>
                        api.deleteTimeEntries(undefined, {
                            queries: {
                                ids: timeEntryIds,
                            },
                            params: {
                                organization: organizationId,
                            },
                        }),
                    trans('Time entries deleted successfully'),
                    trans('Failed to delete time entries')
                );
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        },
    });

    return {
        createTimeEntry,
        updateTimeEntry,
        updateTimeEntries,
        deleteTimeEntry,
        deleteTimeEntries,
    };
}
