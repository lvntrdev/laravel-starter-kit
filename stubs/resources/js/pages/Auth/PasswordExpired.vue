<script setup lang="ts">
    import { Link, useForm } from '@inertiajs/vue3';
    import AuthLayout from '@/layouts/AuthLayout.vue';
    import userPassword from '@/routes/user-password';

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.put(userPassword.update.url(), {
            preserveScroll: true,
            onFinish: () => form.reset('current_password', 'password', 'password_confirmation'),
        });
    };
</script>

<template>
  <AuthLayout :title="$t('sk-auth.password_expired_page.title')">
    <template #header>
      <h2 class="auth-title">
        {{ $t('sk-auth.password_expired_page.heading') }}
      </h2>
      <p class="auth-subtitle">
        {{ $t('sk-auth.password_expired_page.subtitle') }}
      </p>
    </template>

    <form
      class="auth-form"
      @submit.prevent="submit"
    >
      <!-- Current Password -->
      <div class="auth-form__field">
        <label
          for="current_password"
          class="auth-form__label"
        >
          {{ $t('sk-auth.password_expired_page.current_password_label') }}
        </label>
        <IconField>
          <InputIcon class="pi pi-lock" />
          <Password
            id="current_password"
            v-model="form.current_password"
            :invalid="!!form.errors.current_password"
            :feedback="false"
            autocomplete="current-password"
            toggle-mask
            autofocus
            fluid
          />
        </IconField>
        <small
          v-if="form.errors.current_password"
          class="auth-form__error"
        >
          {{ form.errors.current_password }}
        </small>
      </div>

      <!-- New Password -->
      <div class="auth-form__field">
        <label
          for="password"
          class="auth-form__label"
        >
          {{ $t('sk-auth.password_expired_page.password_label') }}
        </label>
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
        <small
          v-if="form.errors.password"
          class="auth-form__error"
        >
          {{ form.errors.password }}
        </small>
      </div>

      <!-- New Password Confirmation -->
      <div class="auth-form__field">
        <label
          for="password_confirmation"
          class="auth-form__label"
        >
          {{ $t('sk-auth.password_expired_page.password_confirmation_label') }}
        </label>
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
        <small
          v-if="form.errors.password_confirmation"
          class="auth-form__error"
        >
          {{ form.errors.password_confirmation }}
        </small>
      </div>

      <!-- Submit -->
      <Button
        type="submit"
        :label="$t('sk-auth.password_expired_page.submit')"
        icon="pi pi-refresh"
        :loading="form.processing"
        class="auth-form__submit"
      />
    </form>

    <template #footer>
      <Link
        href="/logout"
        method="post"
        as="button"
        class="auth-link"
      >
        {{ $t('sk-auth.password_expired_page.logout') }}
      </Link>
    </template>
  </AuthLayout>
</template>
