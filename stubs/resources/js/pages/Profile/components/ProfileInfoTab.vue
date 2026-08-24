<script setup lang="ts">
    import userProfileInformation from '@/routes/user-profile-information';
    import { usePage } from '@inertiajs/vue3';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import { trans } from 'laravel-vue-i18n';

    const page = usePage<{ timezones: string[]; timezone: string }>();
    const user = computed(() => page.props.auth?.user);
    const timezoneOptions = computed(() => [
        {
            label: trans('sk-profile.timezone_site_default', { timezone: page.props.timezone }),
            value: null,
        },
        ...page.props.timezones.map((timezone) => ({ label: timezone, value: timezone })),
    ]);

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-profile.info_title')
            .cardSubtitle('sk-profile.info_subtitle')
            .initialData({
                first_name: user.value?.first_name ?? '',
                last_name: user.value?.last_name ?? '',
                email: user.value?.email ?? '',
                timezone: user.value?.timezone ?? null,
            })
            .submit({
                url: userProfileInformation.update.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                FB.inputText().key('first_name'),
                FB.inputText().key('last_name'),
                FB.inputText().key('email').inputType('email').placeholder('example@mail.com').class('col-span-full'),
                FB.select()
                    .key('timezone')
                    .label('sk-profile.timezone')
                    .options(timezoneOptions.value)
                    .filter(true)
                    .icon('pi pi-clock')
                    .hint('sk-profile.timezone_hint')
                    .optional()
                    .class('col-span-full'),
            )
            .build(),
    );
</script>

<template>
  <!-- Avatar -->
  <AvatarUpload
    :avatar-url="user?.avatar_url"
    upload-url="/user/avatar"
    delete-url="/user/avatar"
    class="mb-8"
  />

  <!-- Profile form -->
  <SkForm :config="formConfig" />
</template>
