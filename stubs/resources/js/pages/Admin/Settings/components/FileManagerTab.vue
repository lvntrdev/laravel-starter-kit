<script setup lang="ts">
    import adminSettings from '@/routes/settings';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import MimePickerField from '@lvntr/components/ui/MimePickerField.vue';
    import Checkbox from 'primevue/checkbox';
    import { computed } from 'vue';

    interface Props {
        settings: {
            max_size_mb: number;
            storage_quota_gb: number;
            accepted_mimes: string[];
            allow_video: boolean;
            allow_audio: boolean;
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
            })
            .submit({
                url: adminSettings.update.fileManager.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
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
