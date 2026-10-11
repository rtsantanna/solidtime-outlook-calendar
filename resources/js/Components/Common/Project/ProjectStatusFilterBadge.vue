<script setup lang="ts">
import { computed } from 'vue';
import { CircleStackIcon } from '@heroicons/vue/16/solid';
import { DropdownMenuItem } from '@/packages/ui/src';
import BaseFilterBadge from './BaseFilterBadge.vue';
import { trans } from 'laravel-vue-i18n';

type StatusValue = 'active' | 'archived' | 'all';

const props = defineProps<{
    value: StatusValue;
}>();

const emit = defineEmits<{
    remove: [];
    'update:value': [value: StatusValue];
}>();

const statusOptions = [
    { id: 'active' as const, name: trans('Active') },
    { id: 'archived' as const, name: trans('Archived') },
];

const label = computed(() => {
    return statusOptions.find((opt) => opt.id === props.value)?.name ?? trans('Status');
});

function updateStatus(status: StatusValue) {
    emit('update:value', status);
}
</script>

<template>
    <BaseFilterBadge
        :icon="CircleStackIcon"
        :label="label"
        :filter-name="$t('Status')"
        @remove="emit('remove')">
        <DropdownMenuItem
            v-for="option in statusOptions"
            :key="option.id"
            :class="[value === option.id && 'bg-accent text-accent-foreground']"
            @click="updateStatus(option.id)">
            {{ option.name }}
        </DropdownMenuItem>
    </BaseFilterBadge>
</template>
