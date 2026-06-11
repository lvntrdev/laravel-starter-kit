<script setup lang="ts">
    import adminSettings from '@/routes/settings';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import MimePickerField from '@lvntr/components/ui/MimePickerField.vue';
    import Checkbox from 'primevue/checkbox';
    import InputNumber from 'primevue/inputnumber';
    import ToggleSwitch from 'primevue/toggleswitch';
    import { computed } from 'vue';

    interface Props {
        settings: {
            max_size_mb: number;
            storage_quota_gb: number;
            accepted_mimes: string[];
            allow_video: boolean;
            allow_audio: boolean;
            enable_trash: boolean;
            trash_retention_days: number;
        };
    }

    const props = defineProps<Props>();

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .cardTitle('sk-setting.file_manager.title')
            .cardSubtitle('sk-setting.file_manager.subtitle')
            .initialData({
                max_size_mb: props.settings.max_size_mb,
                storage_quota_gb: props.settings.storage_quota_gb,
                accepted_mimes: props.settings.accepted_mimes,
                allow_video: props.settings.allow_video,
                allow_audio: props.settings.allow_audio,
                enable_trash: props.settings.enable_trash,
                trash_retention_days: props.settings.trash_retention_days,
            })
            .submit({
                url: adminSettings.update.fileManager.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                FB.checkbox().key('enable_trash').label(false).class('col-span-full'),
                FB.inputNumber()
                    .key('trash_retention_days')
                    .label(false)
                    .min(1)
                    .max(365)
                    .visible((values) => !!values.enable_trash)
                    .class('col-span-full -mt-5'),
                FB.inputNumber().key('max_size_mb').min(1).max(1024).class('col-span-1'),
                FB.inputNumber().key('storage_quota_gb').min(1).max(1024).class('col-span-1'),
                FB.multiselect().key('accepted_mimes').class('col-span-full'),
                FB.slot().key('media_section_title').class('col-span-full'),
                FB.checkbox().key('allow_video').label(false).class('col-span-1'),
                FB.checkbox().key('allow_audio').label(false).class('col-span-1'),
            )
            .build(),
    );
</script>

<template>
    <SkForm :config="formConfig">
        <!-- Çöp Kutusu — entegrasyon kartı başlığı (toggle) -->
        <template #field-enable_trash="{ value, onUpdate }">
            <div
                class="w-full rounded border p-4 transition-colors"
                :class="
                    (value as boolean)
                        ? 'rounded-b-none border-primary-500/30 bg-primary-500/[0.03] dark:bg-primary-500/[0.06]'
                        : 'border-surface-200 dark:border-surface-700'
                "
            >
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400"
                    >
                        <i class="pi pi-trash text-lg" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-semibold text-surface-800 dark:text-surface-100">
                                {{ $t('sk-setting.file_manager.trash.title') }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="
                                    (value as boolean)
                                        ? 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                "
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                {{
                                    (value as boolean)
                                        ? $t('sk-setting.file_manager.trash.active')
                                        : $t('sk-setting.file_manager.trash.inactive')
                                }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                            {{ $t('sk-setting.file_manager.trash.desc') }}
                        </p>
                    </div>
                    <ToggleSwitch
                        :model-value="(value as boolean) ?? false"
                        @update:model-value="onUpdate"
                    />
                </div>
            </div>
        </template>

        <!-- Çöp Kutusu — saklama süresi (yalnızca açıkken görünür, başlığa bitişik) -->
        <template #field-trash_retention_days="{ value, onUpdate }">
            <div
                class="w-full rounded-b rounded-t-none border border-t-0 border-primary-500/30 bg-primary-500/[0.03] p-4 dark:bg-primary-500/[0.06]"
            >
                <div class="flex max-w-sm flex-col gap-2">
                    <label class="text-xs font-semibold text-surface-700 dark:text-surface-200">
                        {{ $t('sk-setting.file_manager.trash.retention_label') }}
                        <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <i
                            class="pi pi-clock pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-surface-400"
                        />
                        <InputNumber
                            :model-value="(value as number)"
                            :min="1"
                            :max="365"
                            :use-grouping="false"
                            :suffix="' ' + $t('sk-setting.file_manager.trash.retention_suffix')"
                            input-class="!pl-9"
                            class="w-full"
                            @update:model-value="onUpdate"
                        />
                    </div>
                    <small class="text-xs text-surface-400 dark:text-surface-500">
                        {{ $t('sk-setting.file_manager.trash.retention_hint') }}
                    </small>
                </div>
            </div>
        </template>

        <template #field-accepted_mimes="{ value, onUpdate }">
            <MimePickerField :model-value="(value as string[]) ?? []" @update:model-value="onUpdate" />
        </template>

        <template #media_section_title>
            <h4
                class="border-b border-surface-200 pb-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-surface-500 dark:border-surface-700 dark:text-surface-400"
            >
                {{ $t('sk-setting.file_manager.media_section_title') }}
            </h4>
        </template>

        <template #field-allow_video="{ value, onUpdate }">
            <label
                class="flex w-full cursor-pointer items-center gap-2.5 rounded border bg-surface-0 p-4 transition-colors dark:bg-surface-900"
                :class="
                    (value as boolean)
                        ? 'border-primary-500 bg-primary-500/8 dark:bg-primary-500/15'
                        : 'border-surface-200 hover:border-primary-400 hover:bg-primary-500/5 dark:border-surface-700 dark:hover:border-primary-500 dark:hover:bg-primary-500/10'
                "
            >
                <Checkbox
                    :model-value="(value as boolean) ?? false"
                    :binary="true"
                    @update:model-value="onUpdate"
                />
                <i
                    :class="[
                        'pi pi-video shrink-0 text-lg',
                        (value as boolean) ? 'text-primary-500' : 'text-surface-500 dark:text-surface-400',
                    ]"
                />
                <div class="flex min-w-0 flex-col">
                    <span class="font-medium leading-tight text-surface-800 dark:text-surface-100">
                        {{ $t('sk-setting.file_manager.video_label') }}
                    </span>
                    <span class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.file_manager.video_hint') }}
                    </span>
                </div>
            </label>
        </template>

        <template #field-allow_audio="{ value, onUpdate }">
            <label
                class="flex w-full cursor-pointer items-center gap-2.5 rounded border bg-surface-0 p-4 transition-colors dark:bg-surface-900"
                :class="
                    (value as boolean)
                        ? 'border-primary-500 bg-primary-500/8 dark:bg-primary-500/15'
                        : 'border-surface-200 hover:border-primary-400 hover:bg-primary-500/5 dark:border-surface-700 dark:hover:border-primary-500 dark:hover:bg-primary-500/10'
                "
            >
                <Checkbox
                    :model-value="(value as boolean) ?? false"
                    :binary="true"
                    @update:model-value="onUpdate"
                />
                <i
                    :class="[
                        'pi pi-volume-up shrink-0 text-lg',
                        (value as boolean) ? 'text-primary-500' : 'text-surface-500 dark:text-surface-400',
                    ]"
                />
                <div class="flex min-w-0 flex-col">
                    <span class="font-medium leading-tight text-surface-800 dark:text-surface-100">
                        {{ $t('sk-setting.file_manager.audio_label') }}
                    </span>
                    <span class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.file_manager.audio_hint') }}
                    </span>
                </div>
            </label>
        </template>
    </SkForm>
</template>
