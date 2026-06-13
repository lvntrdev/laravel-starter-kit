<script setup lang="ts">
    import { computed, ref } from 'vue';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        settings: {
            mailer: string;
            host: string | null;
            port: number | null;
            username: string | null;
            password: null;
            password_is_set: boolean;
            encryption: string | null;
            from_address: string;
            from_name: string;
            reply_to: string | null;
            send_limit: number;
        };
    }

    const props = defineProps<Props>();

    const mailerOptions = [{ label: 'SMTP', value: 'smtp' }];

    const encryptionOptions = [
        { label: 'TLS / STARTTLS', value: 'tls' },
        { label: 'SSL', value: 'ssl' },
        { label: 'sk-setting.mail.encryption_none', value: 'none' },
    ];

    const passwordPlaceholder = computed(() => (props.settings.password_is_set ? '••••••••' : ''));

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-setting.mail.title')
            .cardSubtitle('sk-setting.mail.subtitle')
            .initialData({
                ...props.settings,
                host: props.settings.host ?? '',
                username: props.settings.username ?? '',
                // Never prefill the stored password — the backend preserves it
                // when this field is submitted empty.
                password: '',
                encryption: props.settings.encryption ?? 'none',
                reply_to: props.settings.reply_to ?? '',
                send_limit: props.settings.send_limit ?? 0,
            })
            .submit({
                url: adminSettings.update.mail.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                // SMTP is the only supported transport — keep it submitted but hidden.
                FB.select().key('mailer').options(mailerOptions).default('smtp').hidden(),
                FB.section(trans('sk-setting.mail.sender_title'))
                    .trans(false)
                    .subtitle(trans('sk-setting.mail.sender_subtitle'))
                    .aside()
                    .cols(2)
                    .addFields(
                        FB.inputText().key('from_name').required().icon('pi pi-briefcase'),
                        FB.inputText().key('from_address').required().inputType('email').icon('pi pi-envelope').placeholder('noreply@example.com'),
                        FB.inputText().key('reply_to').optional().inputType('email').icon('pi pi-reply').placeholder('destek@example.com').colSpan(2),
                    ),
                FB.section(trans('sk-setting.mail.smtp_title'))
                    .trans(false)
                    .subtitle(trans('sk-setting.mail.smtp_subtitle'))
                    .aside()
                    .cols(2)
                    .addFields(
                        FB.inputText().key('host').required().icon('pi pi-server').placeholder('smtp.example.com'),
                        FB.inputNumber().key('port').required().icon('pi pi-sort').useGrouping(false),
                        FB.inputText().key('username').optional().icon('pi pi-user'),
                        FB.password().key('password').optional().toggleMask().icon('pi pi-lock').placeholder(passwordPlaceholder.value),
                        FB.select().key('encryption').options(encryptionOptions).default('none').icon('pi pi-shield'),
                        FB.inputNumber()
                            .key('send_limit')
                            .optional()
                            .icon('pi pi-clock')
                            .min(0)
                            .useGrouping(false)
                            .suffix(` ${trans('sk-setting.mail.send_limit_unit')}`),
                    ),
            )
            .build(),
    );

    /* ── Test Mail (separate small form) ── */
    const testMailRef = ref<InstanceType<typeof SkForm> | null>(null);

    const testMailConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .cardTitle('sk-setting.mail.test_title')
            .cardSubtitle('sk-setting.mail.test_subtitle')
            .initialData({ test_email: '' })
            .submit({
                url: adminSettings.testMail.url(),
                method: 'post',
                preserveScroll: true,
            })
            .actionLabels({ submit: 'sk-setting.mail.test_send', submitIcon: 'pi pi-send' })
            .hideCancel()
            .addFields(FB.inputText().key('test_email').label(false).inputType('email').placeholder('test@example.com'))
            .build(),
    );

    function onTestMailSuccess() {
        testMailRef.value?.reset();
    }
</script>

<template>
    <div class="space-y-6">
        <SkForm :config="formConfig" />
        <SkForm ref="testMailRef" :config="testMailConfig" @success="onTestMailSuccess" />
    </div>
</template>
