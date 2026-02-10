<template>
    <aside class="w-[280px] min-h-screen flex flex-col"
        style="background: #F6F8FA; box-shadow: 0px 4px 3px 0px #E5E5E5;">
        <!-- Logo -->
        <div class="pt-[30px] pl-[29px]">
            <img src="/images/logo.svg" alt="Daily Grow" class="w-[160px] h-[28px]" />
        </div>

        <!-- Account name -->
        <div class="pl-[29px] mt-[14px]">
            <span class="font-bold text-[16px] leading-[20px] tracking-[0.2px]" style="color: #363740;">
                {{ accountName }}
            </span>
        </div>

        <!-- Navigation -->
        <nav class="mt-6 px-[16px]">
            <!-- Main category button -->
            <button @click="isExpanded = !isExpanded"
                class="w-[249px] h-[48px] flex items-center gap-3 px-4 rounded-[12px] transition-all cursor-pointer"
                style="background: #FFFFFF; box-shadow: 0px 2px 1px 0px #00000005;">
                <img src="/images/icons/repair.svg" alt="" class="w-[24px] h-[24px]" />
                <span class="font-medium text-[16px] leading-[100%]" style="color: #363740;">
                    Отзывы
                </span>
            </button>

            <!-- Sub-items (collapsible) -->
            <transition name="collapse">
                <ul v-show="isExpanded" class="mt-2 space-y-1 pl-0">
                    <li>
                        <router-link :to="{ name: 'reviews' }"
                            class="block w-[249px] rounded-[12px] px-4 py-[4px] font-medium text-[12px] leading-[100%] transition-all"
                            :class="isActive('reviews') ? 'bg-white' : 'bg-transparent hover:bg-white/50'" :style="[
                                { color: '#363740' },
                                isActive('reviews') ? { boxShadow: '0px 2px 1px 0px #00000005' } : {}
                            ]">
                            Отзывы
                        </router-link>
                    </li>
                    <li>
                        <router-link :to="{ name: 'settings' }"
                            class="block w-[249px] rounded-[12px] px-4 py-[4px] font-medium text-[12px] leading-[100%] transition-all"
                            :class="isActive('settings') ? 'bg-white' : 'bg-transparent hover:bg-white/50'" :style="[
                                { color: '#363740' },
                                isActive('settings') ? { boxShadow: '0px 2px 1px 0px #00000005' } : {}
                            ]">
                            Настройки
                        </router-link>
                    </li>
                </ul>
            </transition>
        </nav>
    </aside>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();

const isExpanded = ref(true);
const accountName = computed(() => authStore.user?.name || 'Название аккаунта');

const isActive = (name) => route.name === name;
</script>

<style scoped>
.collapse-enter-active,
.collapse-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.collapse-enter-from,
.collapse-leave-to {
    opacity: 0;
    max-height: 0;
}

.collapse-enter-to,
.collapse-leave-from {
    opacity: 1;
    max-height: 200px;
}
</style>
