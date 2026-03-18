<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import AuthLayout from '@/layouts/AuthLayout.vue';

    const form = useForm({
        password: '',
    });

    const submit = () => {
        form.post('/user/confirm-password', {
            onFinish: () => form.reset('password'),
        });
    };
</script>

<template>
    <AuthLayout title="Confirm Password">
        <template #header>
            <h2 class="auth-title">
                Confirm Password
            </h2>
            <p class="auth-subtitle">
                This is a secure area. Please confirm your password before continuing.
            </p>
        </template>
        <form class="auth-form" @submit.prevent="submit">
            <!-- Password -->
            <div class="auth-form__field">
                <label for="password" class="auth-form__label"> Password </label>
                <IconField>
                    <InputIcon class="pi pi-lock" />
                    <Password
                        id="password"
                        v-model="form.password"
                        :invalid="!!form.errors.password"
                        :feedback="false"
                        autocomplete="current-password"
                        toggle-mask
                        autofocus
                        fluid
                    />
                </IconField>
                <small v-if="form.errors.password" class="auth-form__error">
                    {{ form.errors.password }}
                </small>
            </div>

            <!-- Submit -->
            <Button
                type="submit"
                label="Confirm"
                icon="pi pi-lock"
                :loading="form.processing"
                class="auth-form__submit"
            />
        </form>
    </AuthLayout>
</template>
