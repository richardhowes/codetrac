<script setup lang="ts">
import { ref, computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

interface Props {
    modelValue?: string | number | null;
    options: Array<{ value: string | number | null; label: string }>;
    placeholder?: string;
    disabled?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select an option',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

const isOpen = ref(false);

const selectedLabel = computed(() => {
    const selected = props.options.find(opt => {
        // Handle null values explicitly
        if (opt.value === null && props.modelValue === null) return true;
        if (opt.value === undefined && props.modelValue === undefined) return true;
        return opt.value === props.modelValue;
    });
    return selected?.label || props.placeholder;
});

function selectOption(value: string | number | null) {
    emit('update:modelValue', value);
    isOpen.value = false;
}

function toggleDropdown() {
    if (!props.disabled) {
        isOpen.value = !isOpen.value;
    }
}
</script>

<template>
    <div class="relative" :class="props.class">
        <button
            type="button"
            @click="toggleDropdown"
            :disabled="disabled"
            class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            :class="{ 'text-muted-foreground': !modelValue }"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <ChevronDown class="h-4 w-4 opacity-50" />
        </button>
        
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <button
                v-for="option in options"
                :key="option.value ?? 'null'"
                @click="selectOption(option.value)"
                class="relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                :class="{ 'bg-accent': (option.value === null && modelValue === null) || (option.value !== null && option.value === modelValue) }"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>