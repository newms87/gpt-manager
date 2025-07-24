import { RouteRecordRaw } from 'vue-router';
import { UiAppLayout } from '../ui/shared/layouts';
import { 
  DemandsView, 
  DemandDetailView, 
  AccountView, 
  SubscriptionView 
} from '../ui/insurance-demands/views';

export const uiRoutes: RouteRecordRaw[] = [
  {
    path: '/ui',
    component: UiAppLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/ui/demands',
      },
      {
        path: 'demands',
        name: 'ui.demands',
        component: DemandsView,
        meta: {
          title: 'Insurance Demands',
        },
      },
      {
        path: 'demands/:id',
        name: 'ui.demands.detail',
        component: DemandDetailView,
        meta: {
          title: 'Demand Details',
        },
      },
      {
        path: 'demands/:id/edit',
        name: 'ui.demands.edit',
        component: DemandDetailView,
        meta: {
          title: 'Edit Demand',
        },
      },
      {
        path: 'account',
        name: 'ui.account',
        component: AccountView,
        meta: {
          title: 'Account Settings',
        },
      },
      {
        path: 'subscription',
        name: 'ui.subscription',
        component: SubscriptionView,
        meta: {
          title: 'Subscription & Billing',
        },
      },
    ],
  },
];