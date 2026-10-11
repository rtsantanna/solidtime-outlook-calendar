<script setup lang="ts">
import {
    TrashIcon,
    UserCircleIcon,
    PencilSquareIcon,
    ArrowDownOnSquareStackIcon,
} from '@heroicons/vue/20/solid';
import type { Member } from '@/packages/api/src';
import {
    canDeleteMembers,
    canMakeMembersPlaceholders,
    canMergeMembers,
    canUpdateMembers,
} from '@/utils/permissions';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/packages/ui/src';

const emit = defineEmits<{
    delete: [];
    edit: [];
    merge: [];
    makePlaceholder: [];
}>();
const props = defineProps<{
    member: Member;
}>();
</script>

<template>
    <DropdownMenu v-if="canUpdateMembers() || canDeleteMembers()">
        <DropdownMenuTrigger as-child>
            <button
                class="focus-visible:outline-none focus-visible:bg-card-background rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:opacity-100 hover:bg-card-background group-hover:opacity-100 opacity-20 transition-opacity text-text-secondary"
                :aria-label="$t('Actions for Member :name', { name: props.member.name })">
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
                v-if="canUpdateMembers()"
                :aria-label="$t('Edit Member :name', { name: props.member.name })"
                class="flex items-center space-x-3 cursor-pointer"
                @click="emit('edit')">
                <PencilSquareIcon class="w-5 text-icon-active" />
                <span>{{ $t('Edit') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="props.member.role === 'placeholder' && canMergeMembers()"
                :aria-label="$t('Merge Member :name', { name: props.member.name })"
                data-testid="member_merge"
                class="flex items-center space-x-3 cursor-pointer"
                @click="emit('merge')">
                <ArrowDownOnSquareStackIcon class="w-5 text-icon-active" />
                <span>{{ $t('Merge') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="props.member.role !== 'placeholder' && canMakeMembersPlaceholders()"
                :aria-label="$t('Make Member :name a placeholder', { name: props.member.name })"
                class="flex items-center space-x-3 cursor-pointer"
                @click="emit('makePlaceholder')">
                <UserCircleIcon class="w-5 text-icon-active" />
                <span>{{ $t('Deactivate') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="canDeleteMembers()"
                :aria-label="$t('Delete Member :name', { name: props.member.name })"
                data-testid="member_delete"
                class="flex items-center space-x-3 cursor-pointer text-destructive focus:text-destructive"
                @click="emit('delete')">
                <TrashIcon class="w-5" />
                <span>{{ $t('Delete') }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<style scoped></style>
