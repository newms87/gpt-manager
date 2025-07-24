import { ref, computed } from 'vue';
import type { UiDemand } from '../../shared/types';

export function useDemandForm(initialData?: Partial<UiDemand>) {
  const formData = ref({
    title: initialData?.title || '',
    description: initialData?.description || '',
    files: initialData?.files || [],
  });

  const errors = ref<Record<string, string>>({});
  const isValid = computed(() => Object.keys(errors.value).length === 0);

  const validate = () => {
    errors.value = {};

    if (!formData.value.title.trim()) {
      errors.value.title = 'Title is required';
    }

    if (formData.value.title.length > 255) {
      errors.value.title = 'Title must be less than 255 characters';
    }

    if (formData.value.description && formData.value.description.length > 1000) {
      errors.value.description = 'Description must be less than 1000 characters';
    }

    return isValid.value;
  };

  const reset = () => {
    formData.value = {
      title: initialData?.title || '',
      description: initialData?.description || '',
      files: initialData?.files || [],
    };
    errors.value = {};
  };

  const setData = (data: Partial<UiDemand>) => {
    formData.value.title = data.title || '';
    formData.value.description = data.description || '';
    formData.value.files = data.files || [];
  };

  return {
    formData,
    errors,
    isValid,
    validate,
    reset,
    setData,
  };
}