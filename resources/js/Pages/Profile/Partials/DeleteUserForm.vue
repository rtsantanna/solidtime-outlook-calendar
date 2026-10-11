<script setup lang="ts">
import { ref } from 'vue';
import ActionSection from '@/Components/ActionSection.vue';
import DangerButton from '@/packages/ui/src/Buttons/DangerButton.vue';
import DialogModal from '@/packages/ui/src/DialogModal.vue';
import { Field, FieldError } from '@/packages/ui/src/field';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import { useDeleteUserMutation } from '@/utils/useUserQuery';
import { getCurrentUserId } from '@/utils/useUser';
import { trans } from 'laravel-vue-i18n';

const deleteUserMutation = useDeleteUserMutation();

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);
const password = ref('');
const passwordError = ref('');
const processing = ref(false);

function confirmUserDeletion() {
    confirmingUserDeletion.value = true;
    setTimeout(() => passwordInput.value?.focus(), 250);
}

async function deleteUser() {
    if (processing.value) return;
    processing.value = true;
    passwordError.value = '';
    try {
        await deleteUserMutation.mutateAsync({
            userId: getCurrentUserId(),
            body: { password: password.value },
        });
        window.location.href = '/';
    } catch (error) {
        if (error && typeof error === 'object' && 'response' in error) {
            const response = error.response as
                { status?: number; data?: { errors?: { password?: string[] } } } | undefined;
            if (response?.status === 422) {
                passwordError.value =
                    response.data?.errors?.password?.[0] ?? trans('Invalid password.');
            }
        }
        processing.value = false;
        passwordInput.value?.focus();
    }
}

function closeModal() {
    confirmingUserDeletion.value = false;
    password.value = '';
    passwordError.value = '';
}
</script>

<template>
    <ActionSection>
        <template #title> {{ $t('Delete Account') }} </template>

        <template #description> {{ $t('Permanently delete your account.') }} </template>

        <template #content>
            <div class="max-w-xl text-sm text-text-secondary">
                {{
                    $t(
                        'Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'
                    )
                }}
            </div>

            <div class="mt-5">
                <DangerButton @click="confirmUserDeletion">
                    {{ $t('Delete Account') }}
                </DangerButton>
            </div>

            <!-- Delete Account Confirmation Modal -->
            <DialogModal :show="confirmingUserDeletion" @close="closeModal">
                <template #title> {{ $t('Delete Account') }} </template>

                <template #content>
                    {{
                        $t(
                            'Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.'
                        )
                    }}

                    <Field class="mt-4">
                        <TextInput
                            ref="passwordInput"
                            v-model="password"
                            type="password"
                            class="block w-3/4"
                            :placeholder="$t('Password')"
                            autocomplete="current-password"
                            @keyup.enter="deleteUser" />

                        <FieldError v-if="passwordError">{{ passwordError }}</FieldError>
                    </Field>
                </template>

                <template #footer>
                    <SecondaryButton @click="closeModal"> {{ $t('Cancel') }} </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': processing }"
                        :disabled="processing"
                        @click="deleteUser">
                        {{ $t('Delete Account') }}
                    </DangerButton>
                </template>
            </DialogModal>
        </template>
    </ActionSection>
</template>
