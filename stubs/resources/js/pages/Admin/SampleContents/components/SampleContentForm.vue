<script setup lang="ts">
    import sampleContents from '@/routes/sample-contents';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import { trans } from 'laravel-vue-i18n';

    interface SampleContentResource {
        id: number;
        title: { translations: Record<string, string>; current: string };
        description: { translations: Record<string, string>; current: string };
        content: { translations: Record<string, string>; current: string };
    }

    interface Props {
        resource?: SampleContentResource | null;
        inDialog?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        resource: null,
        inDialog: false,
    });

    const emit = defineEmits<{
        success: [];
        cancel: [];
    }>();

    const formRef = ref<InstanceType<typeof SkForm>>();
    const isEdit = computed(() => !!props.resource);

    /** Flat locale map oluşturur — TranslatableInput Record<string,string> bekliyor. */
    const flatInitialData = computed<Record<string, Record<string, string>> | undefined>(() => {
        if (!props.resource) return undefined;
        return {
            title: props.resource.title.translations,
            description: props.resource.description.translations,
            content: props.resource.content.translations,
        };
    });

    const formConfig = computed(() => {
        const builder = FB.form()
            .layout('vertical')
            .cols(1)
            .submit({
                url: isEdit.value
                    ? sampleContents.update.url({ sample_content: props.resource!.id })
                    : sampleContents.store.url(),
                method: isEdit.value ? 'put' : 'post',
            })
            .inDialog(props.inDialog)
            .actionsPosition('bottom');

        if (flatInitialData.value) {
            builder.initialData(flatInitialData.value);
        }

        return builder
            .addFields(
                FB.translatableText()
                    .key('title')
                    .label(trans('sample-contents.fields.title'))
                    .trans(false)
                    .required(),
                FB.translatableTextarea()
                    .key('description')
                    .label(trans('sample-contents.fields.description'))
                    .trans(false)
                    .optional()
                    .rows(3),
                FB.translatableEditor()
                    .key('content')
                    .label(trans('sample-contents.fields.content'))
                    .trans(false)
                    .optional(),
            )
            .build();
    });

    defineExpose({ reset: () => formRef.value?.reset() });
</script>

<template>
    <SkForm ref="formRef" :config="formConfig" @success="emit('success')" @cancel="emit('cancel')" />
</template>
