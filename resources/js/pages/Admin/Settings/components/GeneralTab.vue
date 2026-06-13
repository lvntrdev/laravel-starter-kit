<script setup lang="ts">
    import { computed } from 'vue';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        settings: {
            app_name: string;
            tagline: string | null;
            admin_email: string;
            support_email: string | null;
            timezone: string;
            languages: string[];
            default_language: string;
            currency: string;
            date_format: string;
            logo_url: string | null;
            welcome_message: string | null;
        };
        timezones: string[];
        availableLanguages: Record<string, string>;
    }

    const props = defineProps<Props>();

    const timezoneOptions = computed(() => props.timezones.map((tz) => ({ label: tz, value: tz })));

    const languageOptions = computed(() =>
        Object.entries(props.availableLanguages).map(([locale, label]) => ({
            label,
            value: locale,
        })),
    );

    // Default language must stay within the currently active languages.
    const activeLanguageOptions = computed(() =>
        languageOptions.value.filter((opt) => props.settings.languages.includes(opt.value)),
    );

    function toggleLanguage(current: string[] | undefined, locale: string, onUpdate?: (value: unknown) => void) {
        const next = new Set(current ?? []);
        next.has(locale) ? next.delete(locale) : next.add(locale);
        onUpdate?.(Array.from(next));
    }

    const isLanguageOn = (current: string[] | undefined, locale: string) => (current ?? []).includes(locale);

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-setting.general.title')
            .cardSubtitle('sk-setting.general.subtitle')
            .initialData(props.settings)
            .submit({
                url: adminSettings.update.general.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                FB.section(trans('sk-setting.general.identity_title'))
                    .trans(false)
                    .subtitle(trans('sk-setting.general.identity_subtitle'))
                    .aside()
                    .cols(2)
                    .addFields(
                        FB.inputText().key('app_name').required().icon('pi pi-building').colSpan(2),
                        FB.inputText().key('tagline').optional().icon('pi pi-align-left').colSpan(2),
                        FB.inputText().key('admin_email').required().inputType('email').icon('pi pi-envelope'),
                        FB.inputText().key('support_email').optional().inputType('email').icon('pi pi-inbox'),
                    ),
                FB.section(trans('sk-setting.general.regional_title'))
                    .trans(false)
                    .subtitle(trans('sk-setting.general.regional_subtitle'))
                    .aside()
                    .cols(2)
                    .addFields(
                        FB.select()
                            .key('default_language')
                            .options(activeLanguageOptions.value)
                            .icon('pi pi-language')
                            .hint(trans('sk-setting.general.default_language_hint')),
                        FB.select().key('timezone').options(timezoneOptions.value).filter(true).icon('pi pi-clock'),
                        FB.select().key('currency').definitionOptions('currency').icon('pi pi-wallet'),
                        FB.select().key('date_format').definitionOptions('dateFormat').icon('pi pi-calendar'),
                        // Rendered by the #field-languages slot as design pill toggles.
                        FB.selectButton()
                            .key('languages')
                            .options(languageOptions.value)
                            .required()
                            .hint(trans('sk-setting.general.languages_hint'))
                            .colSpan(2),
                    ),
                FB.section(trans('sk-setting.general.welcome_title'))
                    .trans(false)
                    .subtitle(trans('sk-setting.general.welcome_subtitle'))
                    .aside()
                    .cols(1)
                    .addFields(
                        FB.editor()
                            .key('welcome_message')
                            .optional()
                            .label(false)
                            .minHeight('14rem')
                            .toolbar('standard')
                            .links(true)
                            .placeholder('sk-setting.general.welcome_message_placeholder')
                            .imageUpload({
                                context: 'global',
                                folderName: 'Welcome Message',
                            }),
                    ),
            )
            .build(),
    );
</script>

<template>
    <!-- General Settings Form -->
    <SkForm :config="formConfig">
        <!-- Active languages — design pill toggles (ring + check) -->
        <template #field-languages="{ value, onUpdate }">
            <div class="flex flex-wrap gap-2.5">
                <button
                    v-for="opt in languageOptions"
                    :key="opt.value"
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded border px-4 text-sm font-semibold transition-colors"
                    :class="
                        isLanguageOn(value as string[], opt.value)
                            ? 'border-primary-500 bg-primary-500/8 text-primary-500 dark:bg-primary-500/15'
                            : 'border-surface-200 text-surface-600 hover:border-surface-300 dark:border-surface-700 dark:text-surface-300 dark:hover:border-surface-600'
                    "
                    @click="toggleLanguage(value as string[], opt.value, onUpdate)"
                >
                    <span
                        class="grid h-[18px] w-[18px] place-items-center rounded-full border transition-colors"
                        :class="
                            isLanguageOn(value as string[], opt.value)
                                ? 'border-primary-500 bg-primary-500'
                                : 'border-surface-300 dark:border-surface-600'
                        "
                    >
                        <i
                            v-show="isLanguageOn(value as string[], opt.value)"
                            class="pi pi-check text-[9px] text-white"
                        />
                    </span>
                    {{ opt.label }}
                </button>
            </div>
        </template>
    </SkForm>
</template>
