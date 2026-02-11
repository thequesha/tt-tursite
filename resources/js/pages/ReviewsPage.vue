<template>
    <div>
        <!-- Header badge + sync button -->
        <div class="flex items-center justify-between mb-4">
            <div class="inline-flex items-center gap-[6px] h-[25px] px-2 rounded-[8px]"
                style="background: #FFFFFF; border: 1px solid #DCE4EA;">
                <img src="/images/icons/yandex-maps-icon.png" alt="" class="w-4 h-4" />
                <span class="font-medium text-[12px] leading-[100%]" style="color: #363740;">Яндекс Карты</span>
            </div>

            <SyncButton ref="syncBtn" @sync-completed="onSyncCompleted" />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="flex items-center gap-3" style="color: #363740;">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span class="text-sm">Загрузка отзывов...</span>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg p-4">
            {{ error }}
            <router-link v-if="isNotConfigured" :to="{ name: 'settings' }"
                class="block mt-2 text-blue-500 hover:underline">
                Перейти в настройки
            </router-link>
        </div>

        <!-- Content: 3:1 grid -->
        <div v-else class="grid grid-cols-[3fr_1fr] gap-6">
            <!-- Reviews column -->
            <div class="flex flex-col gap-[20px]">
                <div v-if="reviews.length === 0" class="text-sm py-8 text-center" style="color: #363740;">
                    Отзывы не найдены. Нажмите «Обновить» для синхронизации.
                </div>
                <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />

                <!-- Pagination -->
                <PaginationBar
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :total="pagination.total"
                    @page-change="goToPage"
                />
            </div>

            <!-- Rating column -->
            <div v-if="rating !== null" class="flex-shrink-0">
                <div class="sticky top-6">
                    <RatingWidget :rating="rating" :total-reviews="totalReviews" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import ReviewCard from '@/components/ReviewCard.vue';
import RatingWidget from '@/components/RatingWidget.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import SyncButton from '@/components/SyncButton.vue';

const loading = ref(true);
const error = ref('');
const reviews = ref([]);
const rating = ref(null);
const totalReviews = ref(null);
const isNotConfigured = ref(false);
const syncBtn = ref(null);
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
});

const fetchReviews = async (page = 1) => {
    loading.value = true;
    error.value = '';
    isNotConfigured.value = false;

    try {
        const response = await axios.get('/api/reviews', {
            params: { page, per_page: pagination.value.perPage },
        });
        reviews.value = response.data.reviews || [];
        rating.value = response.data.rating;
        totalReviews.value = response.data.totalReviews;

        if (response.data.pagination) {
            pagination.value = response.data.pagination;
        }
    } catch (err) {
        const status = err.response?.status;
        if (status === 422) {
            isNotConfigured.value = true;
            error.value = err.response.data.message;
        } else if (status === 401) {
            error.value = 'Сессия истекла. Пожалуйста, войдите снова.';
        } else {
            error.value = err.response?.data?.message || `Не удалось загрузить отзывы (${status || 'сеть'}).`;
            console.error('Reviews fetch error:', status, err.message, err.response?.data);
        }
    } finally {
        loading.value = false;
    }
};

const goToPage = (page) => {
    fetchReviews(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const onSyncCompleted = () => {
    fetchReviews(1);
};

onMounted(() => fetchReviews(1));
</script>
