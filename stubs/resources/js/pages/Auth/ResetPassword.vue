<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import AuthLayout from '@/layouts/AuthLayout.vue';

    interface Props {
        token: string;
        email?: string;
    }

    const props = defineProps<Props>();

    const form = useForm({
        token: props.token,
        email: props.email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
</script>

<template>
    <AuthLayout title="Reset Password">
        <template #header>
            <h2 class="auth-title">
                Reset Password
            </h2>
            <p class="auth-subtitle">
                Set your new password
            </p>
        </template>
        <form class="auth-form" @submit.prevent="submit">
            <!-- Email -->
            <div class="auth-form__field">
                <label for="email" class="auth-form__label"> Email </label>
                <IconField>
                    <InputIcon class="pi pi-envelope" />
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="example@email.com"
                        :invalid="!!form.errors.email"
                        autocomplete="email"
                        disabled
                        fluid
                    />
                </IconField>
                <small v-if="form.errors.email" class="auth-form__error">
                    {{ form.errors.email }}
                </small>
            </div>

            <!-- Password -->
            <div class="auth-form__field">
                <label for="password" class="auth-form__label"> New Password </label>
                <IconField>
                    <InputIcon class="pi pi-lock" />
                    <Password
                        id="password"
                        v-model="form.password"
                        :invalid="!!form.errors.password"
                        autocomplete="new-password"
                        toggle-mask
                        fluid
                    />
                </IconField>
                <small v-if="form.errors.password" class="auth-form__error">
                    {{ form.errors.password }}
                </small>
            </div>

            <!-- Password Confirmation -->
            <div class="auth-form__field">
                <label for="password_confirmation" class="auth-form__label"> Confirm New Password </label>
                <IconField>
                    <InputIcon class="pi pi-lock" />
                    <Password
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :invalid="!!form.errors.password_confirmation"
                        :feedback="false"
                        autocomplete="new-password"
                        toggle-mask
                        fluid
                    />
                </IconField>
                <small v-if="form.errors.password_confirmation" class="auth-form__error">
                    {{ form.errors.password_confirmation }}
                </small>
            </div>

            <!-- Submit -->
            <Button
                type="submit"
                label="Reset Password"
                icon="pi pi-refresh"
                :loading="form.processing"
                class="auth-form__submit"
            />
        </form>

        <template #footer>
            <a href="/login" class="auth-link" @click.prevent="$inertia.visit('/login')"> Back to sign in </a>
        </template>
    </AuthLayout>
</template>
