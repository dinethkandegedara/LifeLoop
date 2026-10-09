import { ref } from 'vue';

export type ToastType = 'success' | 'warning' | 'danger' | 'info';

export interface ToastItem {
    id: string;
    title: string;
    description?: string;
    type?: ToastType;
    duration?: number;
}

const toasts = ref<ToastItem[]>([]);

export function useToast() {
    function add(item: Omit<ToastItem, 'id'>) {
        const id = Math.random().toString(36).substring(2, 9);
        const duration = item.duration ?? 4000;
        const toastItem: ToastItem = {
            id,
            type: item.type ?? 'info',
            duration,
            ...item,
        };

        toasts.value.push(toastItem);

        if (duration > 0) {
            setTimeout(() => {
                dismiss(id);
            }, duration);
        }

        return id;
    }

    function dismiss(id: string) {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    }

    return {
        toasts,
        add,
        dismiss,
        success: (title: string, description?: string) => add({ title, description, type: 'success' }),
        warning: (title: string, description?: string) => add({ title, description, type: 'warning' }),
        danger: (title: string, description?: string) => add({ title, description, type: 'danger' }),
        info: (title: string, description?: string) => add({ title, description, type: 'info' }),
    };
}
