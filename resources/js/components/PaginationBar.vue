<template>
    <nav v-if="lastPage > 1" class="flex items-center justify-center gap-1 mt-6">
        <!-- Prev -->
        <button
            @click="goTo(currentPage - 1)"
            :disabled="currentPage === 1"
            class="pagination-btn"
            :class="{ 'opacity-40 cursor-not-allowed': currentPage === 1 }"
        >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <!-- Page numbers -->
        <template v-for="page in visiblePages" :key="page">
            <span v-if="page === '...'" class="px-1 text-sm" style="color: #9FA2B4;">...</span>
            <button
                v-else
                @click="goTo(page)"
                class="pagination-btn"
                :class="{ 'pagination-btn--active': page === currentPage }"
            >
                {{ page }}
            </button>
        </template>

        <!-- Next -->
        <button
            @click="goTo(currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="pagination-btn"
            :class="{ 'opacity-40 cursor-not-allowed': currentPage === lastPage }"
        >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <!-- Total info -->
        <span class="ml-3 text-xs" style="color: #9FA2B4;">
            {{ total }} отзывов
        </span>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, default: 0 },
});

const emit = defineEmits(['page-change']);

const goTo = (page) => {
    if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
        emit('page-change', page);
    }
};

const visiblePages = computed(() => {
    const pages = [];
    const current = props.currentPage;
    const last = props.lastPage;

    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
        return pages;
    }

    pages.push(1);

    if (current > 3) pages.push('...');

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let i = start; i <= end; i++) pages.push(i);

    if (current < last - 2) pages.push('...');

    pages.push(last);

    return pages;
});
</script>

<style scoped>
.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #363740;
    background: #FFFFFF;
    border: 1px solid #DCE4EA;
    cursor: pointer;
    transition: all 0.15s;
}
.pagination-btn:hover:not(:disabled):not(.pagination-btn--active) {
    background: #F6F8FA;
    border-color: #C5CCD3;
}
.pagination-btn--active {
    background: #3751FF;
    border-color: #3751FF;
    color: #FFFFFF;
}
</style>
