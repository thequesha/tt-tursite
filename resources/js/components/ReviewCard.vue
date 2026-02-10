<template>
    <div class="rounded-[12px] py-[14px] px-[15px]"
        style="background: #FFFFFF; border: 1px solid #E0E7EC; box-shadow: 0px 3px 6px 0px #5C656F4D;">
        <!-- Inner body -->
        <div class="rounded-[12px] p-3"
            style="background: #F6F8FA; border-bottom: 1px solid #E0E7EC; box-shadow: 0px 2px 1px 0px #00000005;">
            <!-- Row 1: Date, Branch, Stars -->
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2 text-[12px]" style="color: #363740;">
                    <span class="font-medium">{{ formattedDate }}</span>
                    <template v-if="review.branch">
                        <span class="font-bold">{{ review.branch }}</span>
                        <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                clip-rule="evenodd" />
                        </svg>
                    </template>
                </div>
                <StarRating :rating="review.rating" :size="14" />
            </div>

            <!-- Row 2: Author, Phone -->
            <div class="flex items-center gap-2 mb-2">
                <span class="font-bold text-[12px]" style="color: #363740;">{{ review.author }}</span>
                <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                        clip-rule="evenodd" />
                </svg>
                <span v-if="review.phone" class="text-[12px]" style="color: #363740;">{{ review.phone }}</span>
            </div>

            <!-- Row 3: Review text -->
            <p class="text-[12px] leading-relaxed" style="color: #363740;">{{ review.text }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import StarRating from './StarRating.vue';

const props = defineProps({
    review: {
        type: Object,
        required: true,
    },
});

const formattedDate = computed(() => {
    if (!props.review.date) return '';
    try {
        const date = new Date(props.review.date);
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return props.review.date;
    }
});
</script>
