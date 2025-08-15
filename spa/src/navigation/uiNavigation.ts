import { markRaw } from 'vue';
import { 
  FaSolidFile, 
  FaSolidClipboard
} from 'danx-icon';
import type { UiNavigation } from '../ui/shared/types';

export const uiNavigation: UiNavigation[] = [
  {
    title: 'My Demands',
    icon: markRaw(FaSolidFile),
    route: '/ui/demands',
  },
  {
    title: 'Demand Templates',
    icon: markRaw(FaSolidClipboard),
    route: '/ui/templates',
  },
];