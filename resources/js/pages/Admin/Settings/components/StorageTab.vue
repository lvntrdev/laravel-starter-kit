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
            aws_key: string | null;
            aws_secret: null;
            aws_secret_is_set: boolean;
            aws_region: string | null;
            aws_bucket: string | null;
            aws_url: string | null;
            aws_endpoint: string | null;
            hetzner_key: string | null;
            hetzner_secret: null;
            hetzner_secret_is_set: boolean;
            hetzner_region: string | null;
            hetzner_bucket: string | null;
            hetzner_endpoint: string | null;
            hetzner_url: string | null;
        };
    }

    const props = defineProps<Props>();

    const formRef = ref<InstanceType<typeof SkForm>>();

    const secretPlaceholder = (isSet: boolean) => (isSet ? '••••••••' : '');

    // Endpoint/URL inputs show a fixed `https://` addon; the scheme is stripped
    // here and re-added server-side (UpdateStorageSettingsRequest).
    const HTTPS = 'https://';
    const withoutScheme = (url: string | null) => (url ?? '').replace(/^https:\/\//i, '');

    // Card-style driver picker.
    const diskOptions = [
        { label: 'sk-setting.storage.local', value: 'local', description: 'sk-setting.storage.local_desc' },
        { label: 'sk-setting.storage.spaces', value: 'do', description: 'sk-setting.storage.spaces_desc' },
        { label: 'sk-setting.storage.hetzner', value: 'hetzner', description: 'sk-setting.storage.hetzner_desc' },
        { label: 'sk-setting.storage.s3', value: 's3', description: 'sk-setting.storage.s3_desc' },
    ];

    const doRegionOptions = [
        { label: 'NYC1 — New York', value: 'nyc1' },
        { label: 'NYC3 — New York', value: 'nyc3' },
        { label: 'AMS3 — Amsterdam', value: 'ams3' },
        { label: 'SFO2 — San Francisco', value: 'sfo2' },
        { label: 'SFO3 — San Francisco', value: 'sfo3' },
        { label: 'SGP1 — Singapore', value: 'sgp1' },
        { label: 'LON1 — London', value: 'lon1' },
        { label: 'FRA1 — Frankfurt', value: 'fra1' },
        { label: 'TOR1 — Toronto', value: 'tor1' },
        { label: 'BLR1 — Bangalore', value: 'blr1' },
        { label: 'SYD1 — Sydney', value: 'syd1' },
    ];

    const hetznerRegionOptions = [
        { label: 'FSN1 — Falkenstein', value: 'fsn1' },
        { label: 'NBG1 — Nuremberg', value: 'nbg1' },
        { label: 'HEL1 — Helsinki', value: 'hel1' },
    ];

    const awsRegions = [
        'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
        'ca-central-1', 'sa-east-1',
        'eu-central-1', 'eu-central-2', 'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-north-1', 'eu-south-1', 'eu-south-2',
        'me-south-1', 'me-central-1', 'il-central-1', 'af-south-1',
        'ap-east-1', 'ap-south-1', 'ap-south-2', 'ap-northeast-1', 'ap-northeast-2', 'ap-northeast-3',
        'ap-southeast-1', 'ap-southeast-2', 'ap-southeast-3', 'ap-southeast-4',
    ];
    // Keep a stored region that is not in the list selectable.
    if (props.settings.aws_region && !awsRegions.includes(props.settings.aws_region)) {
        awsRegions.push(props.settings.aws_region);
    }
    const awsRegionOptions = awsRegions.map((region) => ({ label: region, value: region }));

    // Region → endpoint host; region + bucket → public URL host
    // (virtual-hosted style: `<bucket>.<endpoint host>`).
    const providers = [
        { prefix: 'spaces', host: (region: string) => `${region}.digitaloceanspaces.com` },
        { prefix: 'hetzner', host: (region: string) => `${region}.your-objectstorage.com` },
        { prefix: 'aws', host: (region: string) => `s3.${region}.amazonaws.com` },
    ];

    for (const { prefix, host } of providers) {
        watch(
            () => {
                const values = formRef.value?.currentValues;
                return values ? [String(values[`${prefix}_region`] ?? ''), String(values[`${prefix}_bucket`] ?? '').trim()] : null;
            },
            (current, previous) => {
                // Skip the first read after mount — keep stored values intact.
                if (!current || !previous) return;

                const [region, bucket] = current;
                const [prevRegion, prevBucket] = previous;

                if (!region || (region === prevRegion && bucket === prevBucket)) return;

                if (region !== prevRegion) {
                    formRef.value?.setValue(`${prefix}_endpoint`, host(region));
                }
                formRef.value?.setValue(`${prefix}_url`, bucket ? `${bucket}.${host(region)}` : '');
            },
        );
    }

    // Section headers rendered by the shared slot template below.
    const providerHeaders = [
        { slot: 'spaces_header', title: 'sk-setting.storage.spaces_title', subtitle: 'sk-setting.storage.spaces_subtitle' },
        { slot: 'hetzner_header', title: 'sk-setting.storage.hetzner_title', subtitle: 'sk-setting.storage.hetzner_subtitle' },
        { slot: 's3_header', title: 'sk-setting.storage.s3_title', subtitle: 'sk-setting.storage.s3_subtitle' },
    ];

    const isLocal = (values: Record<string, unknown>) => values.media_disk === 'local';
    const isDo = (values: Record<string, unknown>) => values.media_disk === 'do';
    const isHetzner = (values: Record<string, unknown>) => values.media_disk === 'hetzner';
    const isS3 = (values: Record<string, unknown>) => values.media_disk === 's3';

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-setting.storage.title')
            .cardSubtitle('sk-setting.storage.subtitle')
            .initialData({
                media_disk: props.settings.media_disk ?? 'local',
                // Never prefill stored secrets — backend preserves them on
                // empty submissions.
                spaces_key: props.settings.spaces_key ?? '',
                spaces_secret: '',
                spaces_region: props.settings.spaces_region ?? '',
                spaces_bucket: props.settings.spaces_bucket ?? '',
                spaces_endpoint: withoutScheme(props.settings.spaces_endpoint),
                spaces_url: withoutScheme(props.settings.spaces_url),
                aws_key: props.settings.aws_key ?? '',
                aws_secret: '',
                aws_region: props.settings.aws_region ?? '',
                aws_bucket: props.settings.aws_bucket ?? '',
                aws_url: withoutScheme(props.settings.aws_url),
                aws_endpoint: withoutScheme(props.settings.aws_endpoint),
                hetzner_key: props.settings.hetzner_key ?? '',
                hetzner_secret: '',
                hetzner_region: props.settings.hetzner_region ?? '',
                hetzner_bucket: props.settings.hetzner_bucket ?? '',
                hetzner_endpoint: withoutScheme(props.settings.hetzner_endpoint),
                hetzner_url: withoutScheme(props.settings.hetzner_url),
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
                    .placeholder(secretPlaceholder(props.settings.spaces_secret_is_set)),
                FB.select().key('spaces_region').options(doRegionOptions).filter().visible(isDo).optional(),
                FB.inputText().key('spaces_bucket').visible(isDo).optional(),
                FB.inputText()
                    .key('spaces_endpoint')
                    .groupPrefix(HTTPS)
                    .placeholder('fra1.digitaloceanspaces.com')
                    .visible(isDo)
                    .optional(),
                FB.inputText()
                    .key('spaces_url')
                    .groupPrefix(HTTPS)
                    .placeholder('bucket.fra1.digitaloceanspaces.com')
                    .visible(isDo)
                    .optional(),

                // ── Hetzner Object Storage ──
                // Labels come from the vendor sk-setting bundle: the app-owned
                // validation.php of existing installs has no hetzner_* attributes.
                FB.slot().key('hetzner_header').visible(isHetzner).class('col-span-full'),
                FB.inputText()
                    .key('hetzner_key')
                    .label('sk-setting.storage.access_key')
                    .visible(isHetzner)
                    .optional(),
                FB.password()
                    .key('hetzner_secret')
                    .label('sk-setting.storage.secret_key')
                    .toggleMask()
                    .visible(isHetzner)
                    .optional()
                    .placeholder(secretPlaceholder(props.settings.hetzner_secret_is_set)),
                FB.select()
                    .key('hetzner_region')
                    .label('sk-setting.storage.region')
                    .options(hetznerRegionOptions)
                    .filter()
                    .visible(isHetzner)
                    .optional(),
                FB.inputText()
                    .key('hetzner_bucket')
                    .label('sk-setting.storage.bucket')
                    .visible(isHetzner)
                    .optional(),
                FB.inputText()
                    .key('hetzner_endpoint')
                    .label('sk-setting.storage.endpoint')
                    .groupPrefix(HTTPS)
                    .placeholder('fsn1.your-objectstorage.com')
                    .visible(isHetzner)
                    .optional(),
                FB.inputText()
                    .key('hetzner_url')
                    .label('sk-setting.storage.url')
                    .groupPrefix(HTTPS)
                    .placeholder('bucket.fsn1.your-objectstorage.com')
                    .visible(isHetzner)
                    .optional(),

                // ── Amazon S3 ──
                FB.slot().key('s3_header').visible(isS3).class('col-span-full'),
                FB.inputText().key('aws_key').visible(isS3).optional(),
                FB.password()
                    .key('aws_secret')
                    .toggleMask()
                    .visible(isS3)
                    .optional()
                    .placeholder(secretPlaceholder(props.settings.aws_secret_is_set)),
                FB.select().key('aws_region').options(awsRegionOptions).filter().visible(isS3).optional(),
                FB.inputText().key('aws_bucket').visible(isS3).optional(),
                FB.inputText()
                    .key('aws_endpoint')
                    .groupPrefix(HTTPS)
                    .placeholder('s3.eu-central-1.amazonaws.com')
                    .visible(isS3)
                    .optional(),
                FB.inputText()
                    .key('aws_url')
                    .groupPrefix(HTTPS)
                    .placeholder('bucket.s3.eu-central-1.amazonaws.com')
                    .visible(isS3)
                    .optional(),
            )
            .build(),
    );
</script>

<template>
    <SkForm ref="formRef" :config="formConfig">
        <!-- Provider section header — tinted icon tile + title + subtitle,
             matching the kit's section-header motif (.sk-secrow / .sk-testbox-head). -->
        <template v-for="header in providerHeaders" :key="header.slot" #[header.slot]>
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
                    <span class="sk-fb__title">{{ $t(header.title) }}</span>
                    <small class="sk-fb__hint mt-0.5 block text-xs leading-snug">
                        {{ $t(header.subtitle) }}
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
