import { ref, computed } from 'vue';
import type { UiNavigation } from '../types';

const navigationItems = ref<UiNavigation[]>([]);
const currentRoute = ref<string>('');

export function useUiNavigation() {
  const navigation = computed(() => navigationItems.value);
  const activeRoute = computed(() => currentRoute.value);
  
  const setNavigation = (items: UiNavigation[]) => {
    navigationItems.value = items;
  };
  
  const setActiveRoute = (route: string) => {
    currentRoute.value = route;
  };
  
  const isActiveRoute = (route: string): boolean => {
    return currentRoute.value.startsWith(route);
  };
  
  const findNavigationItem = (route: string): UiNavigation | undefined => {
    const findInItems = (items: UiNavigation[]): UiNavigation | undefined => {
      for (const item of items) {
        if (item.route === route) return item;
        if (item.children) {
          const found = findInItems(item.children);
          if (found) return found;
        }
      }
      return undefined;
    };
    
    return findInItems(navigationItems.value);
  };
  
  return {
    navigation,
    activeRoute,
    setNavigation,
    setActiveRoute,
    isActiveRoute,
    findNavigationItem,
  };
}