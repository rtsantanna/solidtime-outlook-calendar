<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

defineProps<{
    open: boolean;
    entryCount: number;
    projectName: string;
}>();

defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <AlertDialog :open="open" @update:open="$emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ $t('Remove timesheet row?') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        $tChoice(
                            'This will delete :count time entry for ":project". This action cannot be undone.|This will delete :count time entries for ":project". This action cannot be undone.',
                            entryCount,
                            { count: String(entryCount), project: projectName }
                        )
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ $t('Cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="$emit('confirm')">
                    {{ $t('Delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
