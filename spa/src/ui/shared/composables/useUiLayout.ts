import { ref, computed } from 'vue';
import type { UiLayoutConfig } from '../types';

const layoutConfig = ref<UiLayoutConfig>({
  showSidebar: true,
  sidebarCollapsed: true, // Default to collapsed - expanded state overlays content
  navigation: [],
  theme: {
    name: 'modern-clean',
    colors: {
      primary: '#3B82F6',
      secondary: '#8B5CF6', 
      accent: '#06B6D4',
      background: '#F8FAFC',
      surface: '#FFFFFF',
      text: '#1E293B',
    }
  }
});

export function useUiLayout() {
  const config = computed(() => layoutConfig.value);
  
  const toggleSidebar = () => {
    layoutConfig.value.sidebarCollapsed = !layoutConfig.value.sidebarCollapsed;
  };
  
  const setSidebarCollapsed = (collapsed: boolean) => {
    layoutConfig.value.sidebarCollapsed = collapsed;
  };
  
  const setShowSidebar = (show: boolean) => {
    layoutConfig.value.showSidebar = show;
  };
  
  const updateConfig = (newConfig: Partial<UiLayoutConfig>) => {
    layoutConfig.value = { ...layoutConfig.value, ...newConfig };
  };
  
  return {
    config,
    toggleSidebar,
    setSidebarCollapsed,
    setShowSidebar,
    updateConfig,
  };
}