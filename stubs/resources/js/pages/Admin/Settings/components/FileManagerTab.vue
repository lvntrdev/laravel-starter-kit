<script setup lang="ts">
    import adminSettings from '@/routes/settings';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import MimePickerField from '@lvntr/components/ui/MimePickerField.vue';
    import ToggleFeatureCard from '@lvntr/components/ui/ToggleFeatureCard.vue';
    import { computed } from 'vue';

    interface Props {
        settings: {
            max_size_kb: number;
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
                max_size_kb: props.settings.max_size_kb,
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
                FB.inputNumber().key('max_size_kb').min(1).max(1048576).class('col-span-1'),
                FB.multiselect().key('accepted_mimes').class('col-span-full'),
                FB.toggleSwitch().key('allow_video').label(false).class('col-span-1'),
                FB.toggleSwitch().key('allow_audio').label(false).class('col-span-1'),
            )
            .build(),
    );
</script>

<template>
    <SkForm :config="formConfig">
        <template #field-accepted_mimes="{ value, onUpdate }">
            <MimePickerField :model-value="(value as string[]) ?? []" @update:model-value="onUpdate" />
        </template>
        <template #field-allow_video="{ value, onUpdate }">
            <ToggleFeatureCard
                :model-value="(value as boolean) ?? false"
                :label="$t('sk-setting.file_manager.media_cards.video.label')"
                :description="$t('sk-setting.file_manager.media_cards.video.description')"
                icon="pi-video"
                @update:model-value="onUpdate"
            />
        </template>
        <template #field-allow_audio="{ value, onUpdate }">
            <ToggleFeatureCard
                :model-value="(value as boolean) ?? false"
                :label="$t('sk-setting.file_manager.media_cards.audio.label')"
                :description="$t('sk-setting.file_manager.media_cards.audio.description')"
                icon="pi-volume-up"
                @update:model-value="onUpdate"
            />
        </template>
    </SkForm>
</template>
