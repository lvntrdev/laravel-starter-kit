<script setup lang="ts"></script>

<template>
    <Toast position="bottom-center" group="bc">
        <template #container="{ message, closeCallback }">
            <section class="toast-container" :class="message.severity">
                <div v-if="message.severity !== 'light' || message.severity !== 'dark'" class="toast-icon">
                    <i v-if="message.severity === 'error'" class="pi pi-exclamation-triangle" />
                    <i v-if="message.severity === 'success'" class="pi pi-check" />
                    <i v-if="message.severity === 'info'" class="pi pi-info-circle" />
                    <i v-if="message.severity === 'warn'" class="pi pi-exclamation-circle" />
                </div>

                <div class="toast-message">
                    <span v-if="message.summary" class="text-xl font-bold">{{ message.summary }}</span>
                    <p v-if="message.detail" class="mt-2">
                        {{ message.detail }}
                    </p>
                </div>

                <div class="toast-close">
                    <span>|</span>
                    <i class="pi pi-times" @click="closeCallback" />
                </div>
            </section>
        </template>
    </Toast>
</template>

<style scoped></style>
