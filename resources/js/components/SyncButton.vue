<template>
    <div class="flex items-center gap-3">
        <span v-if="lastSyncedAt && !isBusy" class="text-xs" style="color: #9FA2B4;">
            Обновлено: {{ formatDate(lastSyncedAt) }}
        </span>

        <!-- Progress bar + count when syncing -->
        <div v-if="isBusy" class="flex items-center gap-2">
            <div class="w-[120px] h-[6px] rounded-full bg-gray-200 overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500 ease-out"
                    :style="{ width: progressPercent + '%', background: '#3751FF' }"
                ></div>
            </div>
            <span class="text-xs font-medium whitespace-nowrap" style="color: #9FA2B4;">
                {{ progressText }}
            </span>
        </div>

        <button
            @click="startSync"
            :disabled="isBusy"
            class="inline-flex items-center gap-1.5 h-[30px] px-3 rounded-[8px] text-xs font-medium transition-colors"
            :class="isBusy
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-200'
                : 'bg-white text-[#363740] border border-[#DCE4EA] hover:bg-[#F6F8FA] cursor-pointer'"
        >
            <svg v-if="isBusy" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 2v6h-6M3 22v-6h6M21 13A9 9 0 0 1 6.36 20.13M3 11A9 9 0 0 1 17.64 3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ buttonLabel }}
        </button>
    </div>

    <!-- Status messages for failed / completed -->
    <div v-if="statusMessage && syncStatus === 'failed'" class="mt-2 text-xs px-3 py-1.5 rounded-lg bg-red-50 border border-red-100 text-red-600">
        {{ statusMessage }}
    </div>
    <div v-if="statusMessage && syncStatus === 'completed' && showCompleted" class="mt-2 text-xs px-3 py-1.5 rounded-lg bg-green-50 border border-green-100 text-green-600">
        {{ statusMessage }}
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    autoStart: { type: Boolean, default: false },
});

const emit = defineEmits(['sync-completed']);

const syncStatus = ref('idle');
const statusMessage = ref('');
const lastSyncedAt = ref(null);
const totalReviews = ref(null);
const showCompleted = ref(false);
let pollInterval = null;
let completedTimeout = null;

const isBusy = computed(() => ['pending', 'running'].includes(syncStatus.value));

const buttonLabel = computed(() => {
    if (syncStatus.value === 'pending') return 'В очереди...';
    if (syncStatus.value === 'running') return 'Синхронизация...';
    return 'Обновить отзывы';
});

const currentCount = computed(() => {
    const match = (statusMessage.value || '').match(/(\d+)/);
    return match ? parseInt(match[1]) : 0;
});

const progressPercent = computed(() => {
    if (!totalReviews.value || !currentCount.value) return 5;
    return Math.min(Math.round((currentCount.value / totalReviews.value) * 100), 99);
});

const progressText = computed(() => {
    if (syncStatus.value === 'pending') return 'В очереди...';
    if (!currentCount.value) return statusMessage.value || '...';
    if (totalReviews.value) return `${currentCount.value} / ${totalReviews.value}`;
    return `${currentCount.value} отзывов`;
});

const formatDate = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const fetchStatus = async () => {
    try {
        const { data } = await axios.get('/api/reviews/sync-status');
        syncStatus.value = data.syncStatus || 'idle';
        statusMessage.value = data.syncMessage || '';
        if (data.lastSyncedAt) lastSyncedAt.value = data.lastSyncedAt;
        if (data.totalReviews) totalReviews.value = data.totalReviews;

        if (!isBusy.value && pollInterval) {
            stopPolling();

            if (syncStatus.value === 'completed') {
                showCompleted.value = true;
                completedTimeout = setTimeout(() => { showCompleted.value = false; }, 5000);
                emit('sync-completed');
            }
        }
    } catch (e) {
        // silent
    }
};

const startSync = async () => {
    if (isBusy.value) return;

    try {
        const { data } = await axios.post('/api/reviews/sync');
        syncStatus.value = data.syncStatus || 'pending';
        statusMessage.value = data.message || '';
        startPolling();
    } catch (err) {
        statusMessage.value = err.response?.data?.message || 'Ошибка запуска синхронизации';
        syncStatus.value = 'failed';
    }
};

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(fetchStatus, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onMounted(async () => {
    await fetchStatus();
    if (isBusy.value) startPolling();
    if (props.autoStart && !isBusy.value) startSync();
});

onUnmounted(() => {
    stopPolling();
    if (completedTimeout) clearTimeout(completedTimeout);
});

defineExpose({ startSync, fetchStatus });
</script>
