<script setup lang="ts">
    import { computed, ref } from 'vue';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        postman: {
            configured: boolean;
            workspace_id: string | null;
            api_key_is_set: boolean;
        };
        apidog: {
            configured: boolean;
            project_id: string | null;
            access_token_is_set: boolean;
        };
    }

    const props = defineProps<Props>();

    const postmanOpen = ref(props.postman.configured);
    const apidogOpen = ref(props.apidog.configured);

    const postmanFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .isCard(false)
            .initialData({
                workspace_id: props.postman.workspace_id ?? '',
                api_key: '',
            })
            .submit({
                url: adminSettings.update.postman.url(),
                method: 'put',
                preserveScroll: true,
            })
            .actionLabels({ submit: 'sk-button.save', submitIcon: 'pi pi-save' })
            .addFields(
                FB.password()
                    .key('api_key')
                    .label('sk-setting.postman.api_key_label')
                    .hint(trans('sk-setting.postman.api_key_hint'))
                    .toggleMask()
                    .icon('pi pi-key')
                    .placeholder(props.postman.api_key_is_set ? '••••••••' : 'PMAK-…'),
                FB.inputText()
                    .key('workspace_id')
                    .label('sk-setting.postman.workspace_id_label')
                    .hint(trans('sk-setting.postman.workspace_id_hint'))
                    .icon('pi pi-th-large')
                    .placeholder('00000000-0000-0000-0000-000000000000'),
            )
            .build(),
    );

    const apidogFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .isCard(false)
            .initialData({
                project_id: props.apidog.project_id ?? '',
                access_token: '',
            })
            .submit({
                url: adminSettings.update.apidog.url(),
                method: 'put',
                preserveScroll: true,
            })
            .actionLabels({ submit: 'sk-button.save', submitIcon: 'pi pi-save' })
            .addFields(
                FB.password()
                    .key('access_token')
                    .label('sk-setting.apidog.access_token_label')
                    .hint(trans('sk-setting.apidog.access_token_hint'))
                    .toggleMask()
                    .icon('pi pi-key')
                    .placeholder(props.apidog.access_token_is_set ? '••••••••' : 'APS-…'),
                FB.inputText()
                    .key('project_id')
                    .label('sk-setting.apidog.project_id_label')
                    .hint(trans('sk-setting.apidog.project_id_hint'))
                    .icon('pi pi-folder')
                    .placeholder(trans('sk-setting.apidog.project_id_placeholder')),
            )
            .build(),
    );

    const integrations = computed(() => [
        {
            key: 'postman',
            name: 'Postman',
            desc: trans('sk-api-route.integrations.postman_desc'),
            icon: 'pi pi-send',
            iconClass: 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
            configured: props.postman.configured,
            open: postmanOpen,
            formConfig: postmanFormConfig.value,
        },
        {
            key: 'apidog',
            name: 'Apidog',
            desc: trans('sk-api-route.integrations.apidog_desc'),
            icon: 'pi pi-book',
            iconClass: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
            configured: props.apidog.configured,
            open: apidogOpen,
            formConfig: apidogFormConfig.value,
        },
    ]);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-for="ig in integrations"
            :key="ig.key"
            class="overflow-hidden rounded-md border border-surface-200 dark:border-surface-700"
        >
            <div class="flex items-center gap-3.5 px-4 py-4">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-md text-[17px]"
                    :class="ig.iconClass"
                >
                    <i :class="ig.icon" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2.5">
                        <span class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            {{ ig.name }}
                        </span>
                        <span
                            class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-[11.5px] font-semibold"
                            :class="
                                ig.configured
                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                            "
                        >
                            <span class="size-1.5 rounded-full bg-current" />
                            {{
                                ig.configured
                                    ? $t('sk-api-route.integrations.active')
                                    : $t('sk-api-route.integrations.inactive')
                            }}
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                        {{ ig.desc }}
                    </div>
                </div>
                <ToggleSwitch v-model="ig.open.value" />
            </div>

            <div
                v-if="ig.open.value"
                class="border-t border-surface-200 bg-surface-50/50 px-4 py-4 dark:border-surface-700 dark:bg-surface-800/30"
            >
                <SkForm :config="ig.formConfig" />
            </div>
        </div>
    </div>
</template>
