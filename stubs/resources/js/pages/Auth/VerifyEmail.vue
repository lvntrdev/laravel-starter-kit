<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import AuthLayout from '@/layouts/AuthLayout.vue';

    interface Props {
        status?: string;
    }

    const props = defineProps<Props>();

    const form = useForm({});

    const submit = () => {
        form.post('/email/verification-notification');
    };
</script>

<template>
    <AuthLayout title="Email Verification">
        <template #header>
            <h2 class="auth-title">
                Email Verification
            </h2>
            <p class="auth-subtitle">
                Please verify your email address by clicking the link we sent to you.
            </p>
        </template>
        <div v-if="props.status === 'verification-link-sent'" class="auth-status">
            A new verification link has been sent to your email address.
        </div>

        <form class="auth-form" @submit.prevent="submit">
            <Button
                type="submit"
                label="Resend Verification Email"
                icon="pi pi-envelope"
                :loading="form.processing"
                class="auth-form__submit"
            />
        </form>

        <template #footer>
            <a href="/logout" class="auth-link" @click.prevent="$inertia.visit('/logout', { method: 'post' })">
                Log Out
            </a>
        </template>
    </AuthLayout>
</template>
