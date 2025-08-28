<template>
  <UiMainLayout>
    <template #header>
      <div class="px-6 py-4">
        <h1 class="text-2xl font-bold text-slate-800">
          Account Settings
        </h1>
        <p class="text-slate-600 mt-1">
          Manage your account information and preferences
        </p>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Profile Information -->
        <UiCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-slate-800">
                Profile Information
              </h3>
              <ActionButton
                type="edit"
                variant="ghost"
                size="sm"
                :label="editingProfile ? 'Cancel' : 'Edit'"
                @click="editingProfile = !editingProfile"
              />
            </div>
          </template>

          <form v-if="editingProfile" class="space-y-4" @submit.prevent="updateProfile">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <UiInput
                v-model="profileForm.firstName"
                label="First Name"
                required
                :error="profileErrors.firstName"
              />
              
              <UiInput
                v-model="profileForm.lastName"
                label="Last Name"
                required
                :error="profileErrors.lastName"
              />
            </div>

            <UiInput
              v-model="profileForm.email"
              label="Email Address"
              type="email"
              required
              :error="profileErrors.email"
            />

            <UiInput
              v-model="profileForm.phone"
              label="Phone Number"
              type="tel"
              :error="profileErrors.phone"
            />

            <div class="flex justify-end space-x-3 pt-4">
              <ActionButton
                type="cancel"
                variant="ghost"
                label="Cancel"
                @click="editingProfile = false"
              />
              <ActionButton
                type="save"
                variant="primary"
                :loading="savingProfile"
                label="Save Changes"
              />
            </div>
          </form>

          <div v-else class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-slate-700">First Name</label>
                <p class="mt-1 text-slate-800">{{ user.firstName || 'Not provided' }}</p>
              </div>
              
              <div>
                <label class="text-sm font-medium text-slate-700">Last Name</label>
                <p class="mt-1 text-slate-800">{{ user.lastName || 'Not provided' }}</p>
              </div>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-700">Email Address</label>
              <p class="mt-1 text-slate-800">{{ user.email }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-700">Phone Number</label>
              <p class="mt-1 text-slate-800">{{ user.phone || 'Not provided' }}</p>
            </div>
          </div>
        </UiCard>

        <!-- Change Password -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Change Password
            </h3>
          </template>

          <form class="space-y-4" @submit.prevent="updatePassword">
            <UiInput
              v-model="passwordForm.currentPassword"
              label="Current Password"
              type="password"
              required
              :error="passwordErrors.currentPassword"
            />

            <UiInput
              v-model="passwordForm.newPassword"
              label="New Password"
              type="password"
              required
              :error="passwordErrors.newPassword"
            />

            <UiInput
              v-model="passwordForm.confirmPassword"
              label="Confirm New Password"
              type="password"
              required
              :error="passwordErrors.confirmPassword"
            />

            <div class="flex justify-end pt-4">
              <ActionButton
                type="save"
                variant="primary"
                :loading="savingPassword"
                label="Update Password"
              />
            </div>
          </form>
        </UiCard>

        <!-- Notification Preferences -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Notification Preferences
            </h3>
          </template>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-sm font-medium text-slate-800">Email Notifications</label>
                <p class="text-sm text-slate-600">Receive updates about your demands via email</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  v-model="notifications.email"
                  type="checkbox"
                  class="sr-only peer"
                  @change="updateNotifications"
                />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <label class="text-sm font-medium text-slate-800">SMS Notifications</label>
                <p class="text-sm text-slate-600">Receive urgent updates via text message</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  v-model="notifications.sms"
                  type="checkbox"
                  class="sr-only peer"
                  @change="updateNotifications"
                />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Account Summary -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Account Summary
            </h3>
          </template>

          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Member Since</span>
              <span class="text-sm font-medium text-slate-800">
                {{ formatDate(user.createdAt) }}
              </span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Total Demands</span>
              <span class="text-sm font-medium text-slate-800">{{ user.demandsCount || 0 }}</span>
            </div>
            
            <div class="flex justify-between">
              <span class="text-sm text-slate-600">Account Status</span>
              <span class="text-sm font-medium text-green-600">Active</span>
            </div>
          </div>
        </UiCard>

        <!-- Quick Actions -->
        <UiCard>
          <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
              Quick Actions
            </h3>
          </template>

          <div class="space-y-2">
            <ActionButton
              type="view"
              variant="ghost"
              class="w-full justify-start"
              label="View My Demands"
              @click="$router.push('/ui/demands')"
            />

            <ActionButton
              type="edit"
              variant="ghost"
              class="w-full justify-start"
              label="Manage Subscription"
              @click="$router.push('/ui/subscription')"
            />

            <ActionButton
              type="download"
              variant="ghost"
              class="w-full justify-start"
              label="Download My Data"
              @click="downloadData"
            />
          </div>
        </UiCard>
      </div>
    </div>
  </UiMainLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { 
  FaSolidPenToSquare, 
  FaSolidFile, 
  FaSolidCreditCard, 
  FaSolidDownload 
} from 'danx-icon';
import { ActionButton } from 'quasar-ui-danx';
import { UiMainLayout, UiCard, UiInput } from '../../shared';

// Mock user data - in real app this would come from auth store
const user = reactive({
  firstName: 'John',
  lastName: 'Doe',
  email: 'john.doe@example.com',
  phone: '+1 (555) 123-4567',
  createdAt: '2024-01-15T00:00:00Z',
  demandsCount: 12,
});

const editingProfile = ref(false);
const savingProfile = ref(false);
const savingPassword = ref(false);

const profileForm = reactive({
  firstName: user.firstName,
  lastName: user.lastName,
  email: user.email,
  phone: user.phone,
});

const profileErrors = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
});

const passwordForm = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
});

const passwordErrors = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
});

const notifications = reactive({
  email: true,
  sms: false,
});

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const updateProfile = async () => {
  // Reset errors
  Object.keys(profileErrors).forEach(key => {
    profileErrors[key as keyof typeof profileErrors] = '';
  });

  // Validate
  if (!profileForm.firstName.trim()) {
    profileErrors.firstName = 'First name is required';
    return;
  }

  if (!profileForm.lastName.trim()) {
    profileErrors.lastName = 'Last name is required';
    return;
  }

  if (!profileForm.email.trim()) {
    profileErrors.email = 'Email is required';
    return;
  }

  try {
    savingProfile.value = true;
    
    // TODO: API call to update profile
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Update user data
    Object.assign(user, profileForm);
    editingProfile.value = false;
    
  } catch (error) {
    console.error('Error updating profile:', error);
  } finally {
    savingProfile.value = false;
  }
};

const updatePassword = async () => {
  // Reset errors
  Object.keys(passwordErrors).forEach(key => {
    passwordErrors[key as keyof typeof passwordErrors] = '';
  });

  // Validate
  if (!passwordForm.currentPassword) {
    passwordErrors.currentPassword = 'Current password is required';
    return;
  }

  if (!passwordForm.newPassword) {
    passwordErrors.newPassword = 'New password is required';
    return;
  }

  if (passwordForm.newPassword.length < 8) {
    passwordErrors.newPassword = 'Password must be at least 8 characters';
    return;
  }

  if (passwordForm.newPassword !== passwordForm.confirmPassword) {
    passwordErrors.confirmPassword = 'Passwords do not match';
    return;
  }

  try {
    savingPassword.value = true;
    
    // TODO: API call to update password
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Reset form
    Object.assign(passwordForm, {
      currentPassword: '',
      newPassword: '',
      confirmPassword: '',
    });
    
  } catch (error) {
    console.error('Error updating password:', error);
  } finally {
    savingPassword.value = false;
  }
};

const updateNotifications = async () => {
  try {
    // TODO: API call to update notification preferences
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch (error) {
    console.error('Error updating notifications:', error);
  }
};

const downloadData = () => {
  // TODO: Implement data download
};
</script>