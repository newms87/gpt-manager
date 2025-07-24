import { ref, computed } from 'vue';
import type { UiTheme } from '../types';

const defaultTheme: UiTheme = {
  name: 'modern-clean',
  colors: {
    primary: '#3B82F6',      // Blue-500
    secondary: '#8B5CF6',    // Violet-500  
    accent: '#06B6D4',       // Cyan-500
    background: '#F8FAFC',   // Slate-50
    surface: '#FFFFFF',      // White
    text: '#1E293B',         // Slate-800
  }
};

const currentTheme = ref<UiTheme>(defaultTheme);

export function useUiTheme() {
  const theme = computed(() => currentTheme.value);
  
  const setTheme = (newTheme: UiTheme) => {
    currentTheme.value = newTheme;
    
    // Apply CSS custom properties
    const root = document.documentElement;
    Object.entries(newTheme.colors).forEach(([key, value]) => {
      root.style.setProperty(`--ui-${key}`, value);
    });
  };
  
  const applyTheme = () => {
    setTheme(currentTheme.value);
  };
  
  return {
    theme,
    setTheme,
    applyTheme,
  };
}