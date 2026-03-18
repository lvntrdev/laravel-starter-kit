<script setup lang="ts">
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import AuthLayout from '@/layouts/AuthLayout.vue';

    const useRecoveryCode = ref(false);

    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = () => {
        form.post('/two-factor-challenge', {
            onFinish: () => form.reset(),
        });
    };

    const toggleRecovery = () => {
        useRecoveryCode.value = !useRecoveryCode.value;
        form.reset();
        form.clearErrors();
    };
</script>

<template>
    <AuthLayout title="Two-Factor Authentication">
        <template #header>
            <h2 class="auth-title">
                Two-Factor Authentication
            </h2>
            <p class="auth-subtitle">
                {{
                    useRecoveryCode
                        ? 'Please enter one of your emergency recovery codes.'
                        : 'Please enter the authentication code from your authenticator app.'
                }}
            </p>
        </template>
        <form class="auth-form" @submit.prevent="submit">
            <!-- TOTP Code -->
            <div v-if="!useRecoveryCode" class="auth-form__field">
                <InputOtp v-model="form.code" :length="6" :invalid="!!form.errors.code" integer-only fluid />
                <small v-if="form.errors.code" class="auth-form__error">
                    {{ form.errors.code }}
                </small>
            </div>

            <!-- Recovery Code -->
            <div v-else class="auth-form__field">
                <label for="recovery_code" class="auth-form__label"> Recovery Code </label>
                <InputText
                    id="recovery_code"
                    v-model="form.recovery_code"
                    type="text"
                    placeholder="xxxxx-xxxxx"
                    :invalid="!!form.errors.recovery_code"
                    autocomplete="one-time-code"
                    autofocus
                    fluid
                />
                <small v-if="form.errors.recovery_code" class="auth-form__error">
                    {{ form.errors.recovery_code }}
                </small>
            </div>

            <!-- Submit -->
            <Button
                type="submit"
                label="Verify"
                icon="pi pi-shield"
                :loading="form.processing"
                class="auth-form__submit"
            />
        </form>

        <template #footer>
            <a href="#" class="auth-link" @click.prevent="toggleRecovery">
                {{ useRecoveryCode ? 'Use authentication code' : 'Use a recovery code' }}
            </a>
        </template>
    </AuthLayout>
</template>
