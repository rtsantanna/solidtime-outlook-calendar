<script setup lang="ts">
// "Import from calendar": the user's own Outlook meetings become time entries (delegated Entra ID flow)
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQueryClient } from '@tanstack/vue-query';
import { TagIcon } from '@heroicons/vue/20/solid';
import type { ZodiosResponseByAlias } from '@zodios/core';
import { api } from '@/packages/api/src';
import type { CreateClientBody, CreateProjectBody, SolidTimeApi } from '@/packages/api/src';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { Button } from '@/packages/ui/src/Buttons';
import Checkbox from '@/packages/ui/src/Input/Checkbox.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import TimeTrackerProjectTaskDropdown from '@/packages/ui/src/TimeTracker/TimeTrackerProjectTaskDropdown.vue';
import TagDropdown from '@/packages/ui/src/Tag/TagDropdown.vue';
import {
    formatDateLocalized,
    formatHumanReadableDuration,
    formatTime,
} from '@/packages/ui/src/utils/time';
import type { DateFormat, TimeFormat } from '@/packages/ui/src/utils/time';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { useNotificationsStore } from '@/utils/notification';
import { getOrganizationCurrencyString } from '@/utils/money';
import { canCreateProjects } from '@/utils/permissions';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { trans } from 'laravel-vue-i18n';
import axios from 'axios';

type CalendarEventsResponse = ZodiosResponseByAlias<SolidTimeApi, 'getCalendarEvents'>;
type CalendarEvent = CalendarEventsResponse['data'][0];
type Row = CalendarEvent & {
    selected: boolean;
    description: string;
    project_id: string | null;
    task_id: string | null;
    tags: string[];
};

const show = defineModel<boolean>('show', { default: false });

const page = usePage<{ outlook_connected?: boolean }>();
const connected = computed(() => !!page.props.outlook_connected);

const organizationId = getCurrentOrganizationId();
const { organization } = useOrganizationQuery(organizationId!);
const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { clients } = useClientsQuery();
const { tags } = useTagsQuery();
const queryClient = useQueryClient();
const { addNotification, handleApiRequestNotifications } = useNotificationsStore();

const rows = ref<Row[]>([]);
const periodStart = ref<string>('');
const periodEnd = ref<string>('');
const accountEmail = ref<string | null>(null);
const loading = ref(false);
const saving = ref(false);

const newCount = computed(() => rows.value.filter((row) => row.status === null).length);
const selectedRows = computed(() =>
    rows.value.filter((row) => row.selected && !row.already_imported)
);
const allSelectableSelected = computed(() => {
    const selectable = rows.value.filter((row) => !row.already_imported);
    return selectable.length > 0 && selectable.every((row) => row.selected);
});

const dateFormat = computed(() => organization.value?.date_format as DateFormat | undefined);
const timeFormat = computed(() => organization.value?.time_format as TimeFormat | undefined);

function toRow(event: CalendarEvent): Row {
    return {
        ...event,
        // new ones come checked; imported and ignored ones unchecked
        selected: event.status === null,
        description: event.subject,
        project_id: event.suggested_project_id,
        task_id: event.suggested_project_id ? event.suggested_task_id : null,
        tags: [...event.suggested_tags],
    };
}

function isOutlookConnectionError(error: unknown): boolean {
    if (!axios.isAxiosError(error)) return false;
    const key = error.response?.data?.key;
    return key === 'outlook_not_connected' || key === 'outlook_reconnect_required';
}

async function loadEvents(usePeriod: boolean) {
    if (!organizationId || !connected.value) return;
    loading.value = true;
    try {
        const queries =
            usePeriod && periodStart.value && periodEnd.value
                ? { start: periodStart.value, end: periodEnd.value }
                : {};
        const response = await api.getCalendarEvents({
            params: { organization: organizationId },
            queries,
        });
        rows.value = response.data.map(toRow);
        periodStart.value = response.meta.start;
        periodEnd.value = response.meta.end;
        accountEmail.value = response.meta.account_email;
    } catch (error) {
        if (isOutlookConnectionError(error)) {
            router.reload({ only: ['outlook_connected'] });
        }
        const message = axios.isAxiosError(error)
            ? (error.response?.data?.message ??
              trans('Could not read the Outlook calendar. Please try again later.'))
            : trans('Could not read the Outlook calendar. Please try again later.');
        addNotification('error', trans('Import from calendar'), message);
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [show.value, connected.value] as const,
    ([isShown, isConnected]) => {
        if (isShown && isConnected) {
            loadEvents(false);
        }
    },
    { immediate: true }
);

function toggleAll(checked: boolean) {
    for (const row of rows.value) {
        if (!row.already_imported) row.selected = checked;
    }
}

function connectOutlook() {
    window.location.href = '/outlook/connect';
}

function disconnectOutlook() {
    router.post(
        '/outlook/disconnect',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                rows.value = [];
            },
        }
    );
}

async function importSelected() {
    if (!organizationId || selectedRows.value.length === 0) return;
    saving.value = true;
    try {
        const response = await handleApiRequestNotifications(() =>
            api.importCalendarEvents(
                {
                    items: selectedRows.value.map((row) => ({
                        event_key: row.event_key,
                        description: row.description,
                        start: row.start,
                        end: row.end,
                        project_id: row.project_id,
                        task_id: row.project_id ? row.task_id : null,
                        tags: row.tags,
                    })),
                },
                { params: { organization: organizationId } }
            )
        );
        const result = response.data;
        addNotification(
            'success',
            trans('Meetings imported: :count', { count: String(result.imported) }),
            result.skipped.length > 0
                ? trans(':count skipped (already imported, overlapping or in a closed period).', {
                      count: String(result.skipped.length),
                  })
                : undefined
        );
        queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
        await loadEvents(true);
    } catch {
        // notice already shown by handleApiRequestNotifications
    } finally {
        saving.value = false;
    }
}

async function ignoreSelected() {
    if (!organizationId || selectedRows.value.length === 0) return;
    saving.value = true;
    try {
        await handleApiRequestNotifications(
            () =>
                api.ignoreCalendarEvents(
                    { items: selectedRows.value.map((row) => ({ event_key: row.event_key })) },
                    { params: { organization: organizationId } }
                ),
            trans('Meetings ignored.')
        );
        await loadEvents(true);
    } catch {
        // notice already shown by handleApiRequestNotifications
    } finally {
        saving.value = false;
    }
}

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}
async function createProject(project: CreateProjectBody) {
    return await useProjectsStore().createProject(project);
}
async function createClient(body: CreateClientBody) {
    return await useClientsStore().createClient(body);
}

function formatDuration(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        organization.value?.interval_format,
        organization.value?.number_format
    );
}
</script>

<template>
    <DialogModal closeable :show="show" max-width="2xl" @close="show = false">
        <template #title>
            <span>{{ $t('Import from calendar') }}</span>
        </template>

        <template #content>
            <div v-if="!connected" class="space-y-4 py-2" data-testid="outlook_connect">
                <p>
                    {{
                        $t(
                            'Connect your Outlook calendar to turn your meetings into time entries. Only your own calendar is read.'
                        )
                    }}
                </p>
                <PrimaryButton type="button" @click="connectOutlook">
                    {{ $t('Connect Outlook') }}
                </PrimaryButton>
            </div>

            <div v-else class="space-y-3">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex flex-col text-xs text-text-tertiary">
                        <span>{{ $t('From') }}</span>
                        <input
                            v-model="periodStart"
                            type="date"
                            class="h-9 px-2 rounded-md border border-input-border bg-input-background text-text-primary text-sm" />
                    </label>
                    <label class="flex flex-col text-xs text-text-tertiary">
                        <span>{{ $t('To') }}</span>
                        <input
                            v-model="periodEnd"
                            type="date"
                            class="h-9 px-2 rounded-md border border-input-border bg-input-background text-text-primary text-sm" />
                    </label>
                    <SecondaryButton type="button" :disabled="loading" @click="loadEvents(true)">
                        {{ $t('Load') }}
                    </SecondaryButton>
                    <span
                        class="ml-auto text-xs text-text-tertiary"
                        data-testid="outlook_new_count">
                        {{
                            $tChoice(':count new meeting|:count new meetings', newCount, {
                                count: String(newCount),
                            })
                        }}
                    </span>
                </div>

                <div v-if="loading" class="flex justify-center py-8">
                    <LoadingSpinner></LoadingSpinner>
                </div>
                <p v-else-if="rows.length === 0" class="py-6 text-center text-text-tertiary">
                    {{ $t('No meetings in this period.') }}
                </p>
                <div v-else class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                    <label class="flex items-center gap-2 text-xs text-text-tertiary">
                        <Checkbox
                            :checked="allSelectableSelected"
                            @update:checked="(value: boolean) => toggleAll(value)" />
                        <span>{{ $t('Select all') }}</span>
                    </label>
                    <div
                        v-for="row in rows"
                        :key="row.event_key"
                        data-testid="outlook_event_row"
                        class="rounded-lg border border-card-background-separator p-2 space-y-2"
                        :class="row.already_imported ? 'opacity-50' : ''">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-if="!row.already_imported"
                                v-model:checked="row.selected"
                                :aria-label="row.subject" />
                            <TextInput
                                v-model="row.description"
                                size="sm"
                                class="flex-1 min-w-0"
                                :disabled="row.already_imported"
                                :placeholder="$t('Description')" />
                            <span
                                v-if="row.already_imported"
                                class="shrink-0 rounded-full bg-secondary px-2 py-0.5 text-xs text-text-secondary">
                                {{ $t('Imported') }}
                            </span>
                            <span
                                v-else-if="row.status === 'ignored'"
                                class="shrink-0 rounded-full bg-secondary px-2 py-0.5 text-xs text-text-tertiary">
                                {{ $t('Ignored') }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-text-tertiary">
                            <span class="tabular-nums">
                                {{ formatDateLocalized(row.start, dateFormat) }}
                                {{ formatTime(row.start, timeFormat) }}–{{
                                    formatTime(row.end, timeFormat)
                                }}
                            </span>
                            <span class="tabular-nums">{{ formatDuration(row.duration) }}</span>
                            <span v-if="row.organizer" class="truncate max-w-[12rem]">{{
                                row.organizer
                            }}</span>
                            <div
                                v-if="!row.already_imported"
                                class="ml-auto flex items-center gap-2">
                                <TimeTrackerProjectTaskDropdown
                                    v-model:project="row.project_id"
                                    v-model:task="row.task_id"
                                    variant="outline"
                                    size="xs"
                                    :no-project-value="null"
                                    :clients
                                    :create-project
                                    :create-client
                                    :can-create-project="canCreateProjects()"
                                    :currency="getOrganizationCurrencyString()"
                                    :organization-billable-rate="
                                        organization?.billable_rate ?? null
                                    "
                                    :projects="projects"
                                    :tasks="tasks"
                                    :enable-estimated-time="isAllowedToPerformPremiumAction()" />
                                <TagDropdown
                                    v-model="row.tags"
                                    :create-tag
                                    :tags="tags"
                                    :show-no-tag-option="false">
                                    <template #trigger>
                                        <Button variant="outline" size="xs">
                                            <TagIcon class="h-3.5 text-icon-default" />
                                            <span>{{
                                                row.tags.length === 0
                                                    ? $t('Tags')
                                                    : $tChoice(
                                                          ':count Tag|:count Tags',
                                                          row.tags.length,
                                                          { count: String(row.tags.length) }
                                                      )
                                            }}</span>
                                        </Button>
                                    </template>
                                </TagDropdown>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="flex w-full flex-wrap items-center gap-2">
                <button
                    v-if="connected"
                    type="button"
                    class="mr-auto text-xs text-text-tertiary underline hover:text-text-primary"
                    data-testid="outlook_disconnect"
                    @click="disconnectOutlook">
                    {{ $t('Disconnect Outlook') }}
                    <span v-if="accountEmail">({{ accountEmail }})</span>
                </button>
                <SecondaryButton
                    type="button"
                    :class="connected ? '' : 'ml-auto'"
                    @click="show = false">
                    {{ $t('Cancel') }}
                </SecondaryButton>
                <template v-if="connected">
                    <SecondaryButton
                        type="button"
                        :disabled="saving || selectedRows.length === 0"
                        @click="ignoreSelected">
                        {{ $t('Ignore selected') }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :loading="saving"
                        :disabled="saving || selectedRows.length === 0"
                        @click="importSelected">
                        {{ $t('Import selected') }}
                    </PrimaryButton>
                </template>
            </div>
        </template>
    </DialogModal>
</template>
