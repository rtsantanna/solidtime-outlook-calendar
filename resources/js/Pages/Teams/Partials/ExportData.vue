<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import { ref } from 'vue';
import { useNotificationsStore } from '@/utils/notification';
import { api, type OrganizationExportResponse } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { ArrowUpOnSquareIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { CardTitle } from '@/packages/ui/src';
import Card from '@/Components/Common/Card.vue';
import { useOrganizationStore } from '@/utils/useOrganization';

const showResultModal = ref(false);
const loading = ref(false);
const exportResponse = ref<OrganizationExportResponse | null>(null);

const { organization } = useOrganizationStore();
const { handleApiRequestNotifications } = useNotificationsStore();

async function exportData() {
    loading.value = true;
    const organizationId = getCurrentOrganizationId();
    if (organizationId) {
        try {
            const response = await handleApiRequestNotifications(
                () =>
                    api.exportOrganization(undefined, {
                        params: {
                            organization: organizationId,
                        },
                    }),
                trans('Organization data exported successfully.'),
                trans('Exporting organization data failed.')
            );
            if (response) {
                showResultModal.value = true;
                exportResponse.value = response;
                window.open(response.download_url, '_self')?.focus();
            }
        } finally {
            loading.value = false;
        }
    }
}
</script>

<template>
    <DialogModal closeable :show="showResultModal" @close="showResultModal = false">
        <template #title>{{ $t('The export was successful!') }}</template>
        <template #content>
            <div class="pb-6">
                {{ $t('The download should start automatically. If it does not') }}
                <a
                    class="font-semibold text-accent-200 hover:text-accent-300"
                    target="_self"
                    :href="exportResponse?.download_url"
                    >{{ $t('click here') }}</a
                >
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="showResultModal = false"> {{ $t('Close') }} </SecondaryButton>
        </template>
    </DialogModal>
    <div>
        <CardTitle :title="$t('Export Data')" :icon="ArrowUpOnSquareIcon"></CardTitle>
        <Card class="mb-3">
            <div class="py-2 px-3 sm:px-4 text-sm flex items-center space-x-3">
                <InformationCircleIcon class="h-5 min-w-0 w-5 text-bg-tertiary" />
                <p class="flex-1">
                    {{
                        $t(
                            'Export your solidtime organization data. This will include all clients, projects, tasks, and time entries. You will receive a zip file with json files for each entity.'
                        )
                    }}
                </p>
            </div>
        </Card>
        <Card>
            <div class="py-6 flex-col items-center flex space-y-5 text-center text-sm">
                <div>
                    {{ $t('The following organization will be exported:') }} <br />
                    <strong class="font-semibold">{{ organization?.name }}</strong>
                </div>
                <PrimaryButton :loading @click="exportData"
                    >{{ $t('Export Organization Data') }}
                </PrimaryButton>
            </div>
        </Card>
    </div>
</template>
