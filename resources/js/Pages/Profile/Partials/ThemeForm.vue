<script setup lang="ts">
import FormSection from '@/Components/FormSection.vue';
import { Field, FieldLabel, FieldDescription } from '@/packages/ui/src/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/packages/ui/src';
import { Checkbox } from '@/packages/ui/src';
import { usePreferredColorScheme } from '@vueuse/core';
import { themeSetting } from '@/utils/theme';
import { groupSimilarTimeEntriesSetting } from '@/utils/timeEntryGrouping';

const preferredColor = usePreferredColorScheme();
</script>

<template>
    <FormSection>
        <template #title> {{ $t('Theme') }}</template>

        <template #description>
            {{ $t('Choose how you want solidtime to look on your device') }}
        </template>

        <template #form>
            <!-- Theme -->
            <Field class="col-span-6 sm:col-span-4">
                <FieldLabel for="theme">{{ $t('Theme') }}</FieldLabel>
                <Select id="theme" v-model="themeSetting">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="system">{{ $t('System') }}</SelectItem>
                        <SelectItem value="light">{{ $t('Light') }}</SelectItem>
                        <SelectItem value="dark">{{ $t('Dark') }}</SelectItem>
                    </SelectContent>
                </Select>
                <FieldDescription v-if="themeSetting === 'system'">
                    {{ $t('System default: :theme', { theme: $t(preferredColor) }) }}
                </FieldDescription>
            </Field>

            <!-- Group similar time entries -->
            <Field class="col-span-6 sm:col-span-4" orientation="horizontal">
                <Checkbox
                    id="group_similar_time_entries"
                    v-model:checked="groupSimilarTimeEntriesSetting" />
                <FieldLabel for="group_similar_time_entries">{{
                    $t('Group similar time entries')
                }}</FieldLabel>
            </Field>
        </template>
    </FormSection>
</template>
