<script setup lang="ts">
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';

    interface Props {
        settings: {
            media_disk: string;
            spaces_key: string | null;
            spaces_secret: null;
            spaces_secret_is_set: boolean;
            spaces_region: string | null;
            spaces_bucket: string | null;
            spaces_endpoint: string | null;
            spaces_url: string | null;
            // AWS S3 — kept on the payload for backward compatibility, but
            // hidden from the UI for now.
            aws_key: string | null;
            aws_secret: null;
            aws_secret_is_set: boolean;
            aws_region: string | null;
            aws_bucket: string | null;
            aws_url: string | null;
            aws_endpoint: string | null;
        };
    }

    const props = defineProps<Props>();

    const spacesSecretPlaceholder = computed(() => (props.settings.spaces_secret_is_set ? '••••••••' : ''));

    // Card-style driver picker. Amazon S3 is intentionally omitted for now.
    const diskOptions = [
        { label: 'sk-setting.storage.local', value: 'local', description: 'sk-setting.storage.local_desc' },
        { label: 'sk-setting.storage.spaces', value: 'do', description: 'sk-setting.storage.spaces_desc' },
    ];

    const doRegionOptions = [
        { label: 'NYC1', value: 'nyc1' },
        { label: 'NYC3', value: 'nyc3' },
        { label: 'AMS3', value: 'ams3' },
        { label: 'SFO2', value: 'sfo2' },
        { label: 'SFO3', value: 'sfo3' },
        { label: 'SGP1', value: 'sgp1' },
        { label: 'LON1', value: 'lon1' },
        { label: 'FRA1', value: 'fra1' },
        { label: 'TOR1', value: 'tor1' },
        { label: 'BLR1', value: 'blr1' },
        { label: 'SYD1', value: 'syd1' },
    ];

    const isLocal = (values: Record<string, unknown>) => values.media_disk === 'local';
    const isDo = (values: Record<string, unknown>) => values.media_disk === 'do';

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-setting.storage.title')
            .cardSubtitle('sk-setting.storage.subtitle')
            .initialData({
                media_disk: props.settings.media_disk ?? 'local',
                spaces_key: props.settings.spaces_key ?? '',
                // Never prefill stored secrets — backend preserves them on
                // empty submissions.
                spaces_secret: '',
                spaces_region: props.settings.spaces_region ?? '',
                spaces_bucket: props.settings.spaces_bucket ?? '',
                spaces_endpoint: props.settings.spaces_endpoint ?? '',
                spaces_url: props.settings.spaces_url ?? '',
                // AWS values stay on the payload (hidden) so the backend keeps
                // preserving existing credentials on empty submissions.
                aws_key: props.settings.aws_key ?? '',
                aws_secret: '',
                aws_region: props.settings.aws_region ?? '',
                aws_bucket: props.settings.aws_bucket ?? '',
                aws_url: props.settings.aws_url ?? '',
                aws_endpoint: props.settings.aws_endpoint ?? '',
            })
            .submit({
                url: adminSettings.update.storage.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                // Vertical layout → kit's bordered radio cards. `sk-fb__cards-row`
                // lays those cards side-by-side (responsive 2-col grid) per the
                // design; see formbuilder.css.
                FB.radio()
                    .key('media_disk')
                    .label(false)
                    .options(diskOptions)
                    .radioLayout('vertical')
                    .class('col-span-full sk-fb__cards-row'),

                // Local-disk hint (shown when "Local" is selected).
                FB.slot().key('media_local_hint').visible(isLocal).class('col-span-full'),

                // ── DO Spaces ──
                FB.slot().key('spaces_header').visible(isDo).class('col-span-full'),
                FB.inputText().key('spaces_key').visible(isDo).optional(),
                FB.password()
                    .key('spaces_secret')
                    .toggleMask()
                    .visible(isDo)
                    .optional()
                    .placeholder(spacesSecretPlaceholder.value),
                FB.select().key('spaces_region').options(doRegionOptions).visible(isDo).optional(),
                FB.inputText().key('spaces_bucket').visible(isDo).optional(),
                FB.inputText()
                    .key('spaces_endpoint')
                    .placeholder('https://fra1.digitaloceanspaces.com')
                    .visible(isDo)
                    .optional(),
                FB.inputText()
                    .key('spaces_url')
                    .placeholder('https://bucket.fra1.cdn.digitaloceanspaces.com')
                    .visible(isDo)
                    .optional(),
            )
            .build(),
    );
</script>

<template>
    <SkForm :config="formConfig">
        <!-- DO Spaces section header — tinted icon tile + title + subtitle,
             matching the kit's section-header motif (.sk-secrow / .sk-testbox-head). -->
        <template #spaces_header>
            <div class="sk-fb__section-head flex items-start gap-3 pt-2">
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-md text-[15px]"
                    :style="{
                        background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)',
                        color: 'var(--p-primary-color)',
                    }"
                >
                    <i class="pi pi-cloud" />
                </span>
                <div class="min-w-0">
                    <span class="sk-fb__title">{{ $t('sk-setting.storage.spaces_title') }}</span>
                    <small class="sk-fb__hint mt-0.5 block text-xs leading-snug">
                        {{ $t('sk-setting.storage.spaces_subtitle') }}
                    </small>
                </div>
            </div>
        </template>

        <template #media_local_hint>
            <!-- eslint-disable-next-line vue/no-v-html — static, trusted i18n string -->
            <p
                class="mt-1 text-[11.5px] leading-relaxed text-[var(--panel-text-muted)] [&_code]:rounded [&_code]:font-mono [&_code]:text-[12px] [&_code]:text-[var(--panel-text-soft)]"
                v-html="$t('sk-setting.storage.local_hint')"
            />
        </template>
    </SkForm>
</template>
