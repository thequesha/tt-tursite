<template>
    <div class="max-w-2xl">
        <h1 class="text-lg font-semibold text-gray-900 mb-4">Подключить Яндекс</h1>

        <p class="text-sm text-gray-500 mb-3">
            Укажите ссылку на Яндекс, пример:
            <a href="https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/" target="_blank"
                class="text-blue-500 hover:underline break-all">
                https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/
            </a>
        </p>

        <div v-if="settingsStore.error"
            class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg p-3 mb-3">
            {{ settingsStore.error }}
        </div>

        <div v-if="settingsStore.success"
            class="bg-green-50 border border-green-200 text-green-600 text-sm rounded-lg p-3 mb-3">
            Настройки сохранены успешно.
        </div>

        <form @submit.prevent="handleSave" class="space-y-4">
            <div>
                <input v-model="yandexUrl" type="url" required
                    class="w-full max-w-lg px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                    placeholder="https://yandex.ru/maps/org/company_name/1234567890/reviews/" />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" :disabled="settingsStore.loading"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded-lg text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span v-if="settingsStore.loading">Сохранение...</span>
                    <span v-else>Сохранить</span>
                </button>

                <SyncButton ref="syncBtn" @sync-completed="onSyncCompleted" />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import SyncButton from '@/components/SyncButton.vue';

const settingsStore = useSettingsStore();
const yandexUrl = ref('');
const syncBtn = ref(null);

onMounted(async () => {
    await settingsStore.fetchSettings();
    yandexUrl.value = settingsStore.yandexUrl;
});

const handleSave = async () => {
    await settingsStore.saveSettings(yandexUrl.value);
    if (settingsStore.success && syncBtn.value) {
        syncBtn.value.startSync();
    }
};

const onSyncCompleted = () => {
    // Could refresh settings data if needed
};
</script>
