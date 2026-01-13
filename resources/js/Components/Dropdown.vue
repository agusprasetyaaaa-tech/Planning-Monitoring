<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        48: 'w-48',
        60: 'w-60',
        64: 'w-64',
        72: 'w-72',
        80: 'w-80',
        96: 'w-96',
        'responsive': 'w-auto md:w-80', // Auto width for fixed inset
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    // If responsive, only apply alignment on desktop
    const prefix = props.width === 'responsive' ? 'md:' : '';

    if (props.align === 'left') {
        return prefix + 'ltr:origin-top-left ' + prefix + 'rtl:origin-top-right ' + prefix + 'start-0';
    } else if (props.align === 'right') {
        return prefix + 'ltr:origin-top-right ' + prefix + 'rtl:origin-top-left ' + prefix + 'end-0';
    } else {
        return 'origin-top';
    }
});

const dropdownClasses = computed(() => {
    if (props.width === 'responsive') {
        return 'fixed inset-x-4 top-[68px] z-50 md:absolute md:z-50 md:inset-auto md:mt-2';
    }
    return 'absolute z-50 mt-2';
});

const open = ref(false);
</script>

<template>
    <div class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="rounded-md shadow-lg"
                :class="[widthClass, alignmentClasses, dropdownClasses]"
                style="display: none;" 
                v-show:original="open" 
                @click="open = false"
            >
                <div
                    class="rounded-md ring-1 ring-black ring-opacity-5"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
