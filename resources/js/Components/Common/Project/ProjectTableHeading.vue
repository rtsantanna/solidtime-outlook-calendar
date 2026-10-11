<script setup lang="ts">
import { computed } from 'vue';
import TableHeading from '@/Components/Common/TableHeading.vue';
import SortableTableHeaderCell from '@/Components/Common/SortableTableHeaderCell.vue';
import type { SortColumn, SortDirection } from '@/Components/Common/Project/ProjectTable.vue';

const props = defineProps<{
    showBillableRate: boolean;
    sortColumn: SortColumn;
    sortDirection: SortDirection;
    descFirstColumns: ReadonlySet<SortColumn>;
}>();

const emit = defineEmits<{
    sort: [column: SortColumn];
}>();

// Bound once per cell instead of repeating the three sort props on every column.
const sortState = computed(() => ({
    sortColumn: props.sortColumn,
    sortDirection: props.sortDirection,
    descFirstColumns: props.descFirstColumns,
}));

function handleSort(column: SortColumn) {
    emit('sort', column);
}
</script>

<template>
    <TableHeading>
        <SortableTableHeaderCell
            class="pr-3 pl-2 sm:pl-4 lg:pl-6"
            column="name"
            v-bind="sortState"
            @sort="handleSort">
            {{ $t('Name') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell column="client_name" v-bind="sortState" @sort="handleSort">
            {{ $t('Client') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell column="spent_time" v-bind="sortState" @sort="handleSort">
            {{ $t('Total Time') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell column="progress" v-bind="sortState" @sort="handleSort">
            {{ $t('Progress') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell
            v-if="showBillableRate"
            column="billable_rate"
            v-bind="sortState"
            @sort="handleSort">
            {{ $t('Billable Rate') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell column="status" v-bind="sortState" @sort="handleSort">
            {{ $t('Status') }}
        </SortableTableHeaderCell>
        <SortableTableHeaderCell column="visibility" v-bind="sortState" @sort="handleSort">
            {{ $t('Visibility') }}
        </SortableTableHeaderCell>
        <div class="relative py-1.5 pl-3 pr-2 sm:pr-4 lg:pr-6">
            <span class="sr-only">{{ $t('Edit') }}</span>
        </div>
    </TableHeading>
</template>
