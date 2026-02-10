<template>
    <div class="w-[263px] rounded-[12px]" style="background: #FFFFFF; border: 1px solid #E0E7EC;">
        <!-- Rating section -->
        <div class="flex items-center justify-center gap-2 py-5 px-4">
            <span class="text-[40px] font-bold leading-none" style="color: #363740;">{{ displayRating }}</span>
            <StarRating :rating="Math.round(rating)" :size="28" />
        </div>

        <!-- Separator -->
        <div class="mx-4" style="border-top: 1px solid #E0E7EC;"></div>

        <!-- Total reviews -->
        <div class="py-4 px-4">
            <p class="text-[14px] italic" style="color: #363740;">
                Всего отзывов: <span class="font-semibold">{{ formattedTotal }}</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import StarRating from './StarRating.vue';

const props = defineProps({
    rating: {
        type: Number,
        default: 0,
    },
    totalReviews: {
        type: Number,
        default: 0,
    },
});

const displayRating = computed(() => {
    if (!props.rating) return '0';
    return props.rating.toFixed(1);
});

const formattedTotal = computed(() => {
    if (!props.totalReviews) return '0';
    return props.totalReviews.toLocaleString('ru-RU');
});
</script>
