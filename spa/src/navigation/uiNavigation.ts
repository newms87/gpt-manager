import { markRaw } from 'vue';
import { 
  FaSolidFile, 
  FaSolidUser, 
  FaSolidCreditCard 
} from 'danx-icon';
import type { UiNavigation } from '../ui/shared/types';

export const uiNavigation: UiNavigation[] = [
  {
    title: 'My Demands',
    icon: markRaw(FaSolidFile),
    route: '/ui/demands',
  },
  {
    title: 'Account',
    icon: markRaw(FaSolidUser),
    route: '/ui/account',
  },
  {
    title: 'Subscription',
    icon: markRaw(FaSolidCreditCard),
    route: '/ui/subscription',
  },
];