<script setup lang="ts">
import { TrashIcon, PencilSquareIcon, ArchiveBoxIcon } from '@heroicons/vue/20/solid';
import type { Project } from '@/packages/api/src';
import { canDeleteProjects, canUpdateProjects } from '@/utils/permissions';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/packages/ui/src';

const emit = defineEmits<{
    delete: [];
    edit: [];
    archive: [];
}>();
const props = defineProps<{
    project: Project;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                class="focus-visible:outline-none focus-visible:bg-card-background rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:opacity-100 hover:bg-card-background group-hover:opacity-100 opacity-20 transition-opacity text-text-secondary"
                :aria-label="$t('Actions for Project :name', { name: props.project.name })">
                <svg
                    class="h-8 w-8 p-1 rounded-full"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M12 5.92A.96.96 0 1 0 12 4a.96.96 0 0 0 0 1.92m0 7.04a.96.96 0 1 0 0-1.92a.96.96 0 0 0 0 1.92M12 20a.96.96 0 1 0 0-1.92a.96.96 0 0 0 0 1.92" />
                </svg>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="min-w-[150px]" align="end">
            <DropdownMenuItem
                v-if="canUpdateProjects()"
                :aria-label="$t('Edit Project :name', { name: props.project.name })"
                data-testid="project_edit"
                class="flex items-center space-x-3 cursor-pointer"
                @click.prevent="emit('edit')">
                <PencilSquareIcon class="w-5 text-icon-active" />
                <span>{{ $t('Edit') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="canUpdateProjects()"
                :aria-label="$t('Archive Project :name', { name: props.project.name })"
                class="flex items-center space-x-3 cursor-pointer"
                @click.prevent="emit('archive')">
                <ArchiveBoxIcon class="w-5 text-icon-active" />
                <span>{{ project.is_archived ? $t('Unarchive') : $t('Archive') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="canDeleteProjects()"
                :aria-label="$t('Delete Project :name', { name: props.project.name })"
                data-testid="project_delete"
                class="flex items-center space-x-3 cursor-pointer text-destructive focus:text-destructive"
                @click.prevent="emit('delete')">
                <TrashIcon class="w-5" />
                <span>{{ $t('Delete') }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<style scoped></style>
