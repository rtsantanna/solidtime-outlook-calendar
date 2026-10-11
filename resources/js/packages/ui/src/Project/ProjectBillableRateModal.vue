<script setup lang="ts">
import { formatCents } from '@/packages/ui/src/utils/money';
import BillableRateModal from '@/packages/ui/src/BillableRateModal.vue';
import { inject, type ComputedRef } from 'vue';
import type { Organization } from '@/packages/api/src';

const show = defineModel('show', { default: false });
const saving = defineModel('saving', { default: false });

const organization = inject<ComputedRef<Organization>>('organization');

defineProps<{
    newBillableRate?: number | null;
    projectName: string;
    currency: string;
}>();

defineEmits<{
    submit: [];
}>();
</script>

<template>
    <BillableRateModal
        v-model:show="show"
        v-model:saving="saving"
        :title="$t('Update Project Billable Rate')"
        @submit="$emit('submit')">
        <p class="py-1 text-center">
            {{ $t('The billable rate of :name will be updated to', { name: projectName }) }}
            <strong>{{
                newBillableRate
                    ? formatCents(
                          newBillableRate,
                          currency,
                          organization?.currency_format,
                          organization?.currency_symbol,
                          organization?.number_format
                      )
                    : $t('the default rate of the organization member')
            }}</strong
            >.
        </p>
        <p class="py-1 text-center font-semibold max-w-md mx-auto">
            {{
                $t(
                    'Do you want to update all existing time entries, where the project billable rate applies as well?'
                )
            }}
        </p>
    </BillableRateModal>
</template>

<style scoped></style>
