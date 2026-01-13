<script setup>
import NexusSidebar from '@/Components/NexusSidebar.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Toast from '@/Components/Toast.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const isSidebarCollapsed = ref(false);
const isMobileSidebarOpen = ref(false);

const page = usePage();

// Notification Logic
const markRead = (notification) => {
    const visit = () => {
        if (notification.data && notification.data.link) {
            router.visit(notification.data.link);
        }
    };

    if (!notification.read_at) {
        router.post(route('notifications.read', notification.id), {}, { 
            preserveScroll: true,
            onSuccess: () => visit()
        });
    } else {
        visit();
    }
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

// Logic Check Role
const isSuperAdmin = computed(() => {
    // Check if roles exists and includes Super Admin
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('Super Admin');
});

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

// Close mobile sidebar on route change
watch(() => page.url, () => {
    isMobileSidebarOpen.value = false;
});

// Toast Logic
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const lastShownMessage = ref(''); // Track last shown message to prevent duplicates

watch(() => page.props.flash, (flash) => {
    // Prevent showing the same message multiple times (e.g., from auto-refresh)
    const currentMessage = flash?.success || flash?.error || '';
    
    if (currentMessage && currentMessage !== lastShownMessage.value) {
        if (flash?.success) {
            toastMessage.value = flash.success;
            toastType.value = 'success';
            showToast.value = true;
            lastShownMessage.value = flash.success;
        } else if (flash?.error) {
            toastMessage.value = flash.error;
            toastType.value = 'error';
            showToast.value = true;
            lastShownMessage.value = flash.error;
        }
    }
    
    // Clear lastShownMessage when flash is empty (for next action)
    if (!currentMessage) {
        lastShownMessage.value = '';
    }
}, { deep: true, immediate: true });
</script>

<template>
  <div class="bg-gray-50 min-h-screen font-sans">
      
      <!-- Layout for Super Admin -->
      <template v-if="isSuperAdmin">
          <!-- Mobile Backdrop -->
          <div v-show="isMobileSidebarOpen" class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden backdrop-blur-sm transition-opacity" @click="isMobileSidebarOpen = false"></div>
    
          <NexusSidebar 
            :is-collapsed="isSidebarCollapsed" 
            :mobile-open="isMobileSidebarOpen" 
            @toggle="toggleSidebar" 
            @close-mobile="isMobileSidebarOpen = false"
          />
          <!-- Main Content -->
          <main class="min-h-screen flex flex-col transition-all duration-300" 
                :class="isSidebarCollapsed ? 'lg:ml-20' : 'lg:ml-72'">
              <!-- Header -->
              <header class="h-16 bg-white border-b border-gray-200 sticky top-0 z-40 px-6 flex items-center justify-between">
                  <!-- Left: Breadcrumbs or Title -->
                  <div class="flex items-center gap-4">
                      <!-- Mobile Sidebar Toggle -->
                      <button @click="isMobileSidebarOpen = true" class="lg:hidden p-1 text-gray-500 hover:bg-gray-100 rounded-lg -ml-2">
                           <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                           </svg>
                      </button>
                      
                  </div>
    
                  <!-- Right: Actions -->
                  <div class="flex items-center gap-3">
                       <!-- Notifications -->
                       <!-- Notifications -->
                       <div class="relative">
                           <Dropdown align="right" width="responsive" contentClasses="py-1 bg-white ring-1 ring-black ring-opacity-5">
                               <template #trigger>
                                   <button class="p-2 text-gray-500 hover:bg-gray-50 rounded-lg hover:text-gray-900 relative">
                                       <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                       <!-- Unread Badge -->
                                       <span v-if="$page.props.unreadCount > 0" class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white animate-pulse"></span>
                                   </button>
                               </template>

                               <template #content>
                                   <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                       <h3 class="font-bold text-gray-700 text-sm">Notifications</h3>
                                       <button v-if="$page.props.unreadCount > 0" @click="markAllRead" class="text-xs text-blue-600 hover:text-blue-800 font-medium hover:underline">Mark all read</button>
                                   </div>

                                   <div v-if="!$page.props.notifications || $page.props.notifications.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
                                       <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                       </svg>
                                       No new notifications
                                   </div>

                                   <div v-else class="max-h-80 overflow-y-auto custom-scrollbar">
                                       <div v-for="notification in $page.props.notifications" :key="notification.id" 
                                            class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors flex gap-3 cursor-pointer relative group"
                                            :class="{'bg-blue-50/40': !notification.read_at}"
                                            @click="markRead(notification)"
                                       >
                                           <!-- Icon Section -->
                                           <div class="flex-shrink-0 mt-1">
                                               <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-600': notification.data.color === 'emerald',
                                                        'bg-red-100 text-red-600': notification.data.color === 'red',
                                                        'bg-amber-100 text-amber-600': notification.data.color === 'amber',
                                                        'bg-blue-100 text-blue-600': !notification.data.color
                                                    }"
                                               >
                                                    <svg v-if="notification.data.icon === 'check'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                    <svg v-else-if="notification.data.icon === 'x'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    <svg v-else-if="notification.data.icon === 'clock'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                               </div>
                                           </div>
                                           
                                           <!-- Content Section -->
                                           <div class="ml-3 flex-1 text-left">
                                               <p class="text-sm font-bold text-gray-900">{{ notification.data.title }}</p>
                                               <p class="text-xs text-gray-700 mt-0.5 line-clamp-2 leading-relaxed">{{ notification.data.message }}</p>
                                               <p class="text-xs text-gray-500 mt-1.5 flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    {{ notification.created_at }} ago
                                               </p>
                                           </div>
                                           
                                           <!-- Unread Indicator -->
                                           <div v-if="!notification.read_at" class="absolute top-4 right-4 w-2 h-2 rounded-full bg-blue-500 ring-2 ring-white"></div>
                                       </div>
                                   </div>
                               </template>
                           </Dropdown>
                       </div>
    
                       <div class="h-8 w-px bg-gray-200 mx-1"></div>
    
                       <!-- User Dropdown -->
                       <div class="pl-1">
                            <Dropdown align="right" width="64">
                                <template #trigger>
                                    <button class="flex items-center gap-2 cursor-pointer focus:outline-none transition-opacity hover:opacity-80">
                                        <img :src="$page.props.auth.user.avatar_url" class="w-8 h-8 rounded-full border border-gray-200 object-cover" />
                                        <div class="hidden md:block text-left">
                                            <p class="text-sm font-semibold text-gray-800 leading-none">{{ $page.props.auth.user.name }}</p>
                                            <p class="text-xs text-gray-500 leading-none mt-1">
                                                {{ $page.props.auth.user.roles && $page.props.auth.user.roles.length ? $page.props.auth.user.roles[0] : 'User' }}
                                            </p>
                                        </div>
                                        <svg class="ms-2 -me-0.5 h-4 w-4 hidden md:block text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <!-- User Info Header -->
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-bold text-gray-900">{{ $page.props.auth.user.name }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $page.props.auth.user.email }}</p>
                                    </div>
                                    
                                    <!-- Profile Link -->
                                    <DropdownLink :href="route('profile.edit')" class="flex items-center gap-3 py-2.5">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Profile</span>
                                    </DropdownLink>
                                    
                                    <!-- Logout Link -->
                                    <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-3 py-2.5 text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors border-t border-gray-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        <span>Logout</span>
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                       </div>
                  </div>
              </header>
    
              <!-- Content -->
              <div class="items-start justify-center p-6">
                  <slot />
              </div>
          </main>
      </template>
      
      <!-- Layout for Other Roles (Mobile Optimized, No Sidebar/Navbar) -->
      <template v-else>
          <div class="min-h-screen bg-gray-50 w-full relative pb-20 lg:pb-0">
              <!-- Header Removed -->

              <slot />

              <!-- Mobile Bottom Navigation (Curved/Pop-up Style) -->
              <!-- Mobile Bottom Navigation (Flat, Simple, No Animation) -->
              <nav class="lg:hidden fixed bottom-0 left-0 z-50 w-full bg-white border-t border-gray-200 pb-6">
                <div class="grid h-16 w-full grid-cols-3 mx-auto">
                    
                    <!-- Planning -->
                    <Link :href="route('planning.index')" 
                          class="flex flex-col items-center justify-center w-full h-full gap-0.5 active:bg-gray-50">
                        <svg class="w-6 h-6" 
                             :class="$page.url.startsWith('/planning') && !$page.url.startsWith('/planning-report') ? 'text-[#0aa573]' : 'text-gray-400'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-[10px] font-medium"
                              :class="$page.url.startsWith('/planning') && !$page.url.startsWith('/planning-report') ? 'text-[#0aa573]' : 'text-gray-500'">
                            Planning
                        </span>
                    </Link>

                    <!-- Home -->
                    <Link :href="route('dashboard')" 
                          class="flex flex-col items-center justify-center w-full h-full gap-0.5 active:bg-gray-50">
                         <svg class="w-6 h-6" 
                             :class="$page.url === '/dashboard' || $page.url === '/' ? 'text-[#0aa573]' : 'text-gray-400'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-[10px] font-medium"
                              :class="$page.url === '/dashboard' || $page.url === '/' ? 'text-[#0aa573]' : 'text-gray-500'">
                            Home
                        </span>
                    </Link>
                    
                    <!-- Report -->
                    <Link :href="route('planning-report.index')" 
                          class="flex flex-col items-center justify-center w-full h-full gap-0.5 active:bg-gray-50">
                        <svg class="w-6 h-6" 
                             :class="$page.url.startsWith('/planning-report') ? 'text-[#0aa573]' : 'text-gray-400'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                        </svg>
                        <span class="text-[10px] font-medium"
                              :class="$page.url.startsWith('/planning-report') ? 'text-[#0aa573]' : 'text-gray-500'">
                            Report
                        </span>
                    </Link>

                </div>
              </nav>
          </div>
      </template>
      
      <!-- Global Toast Notification -->
      <Toast 
        :show="showToast" 
        :message="toastMessage" 
        :type="toastType" 
        @close="showToast = false" 
      />
  </div>
</template>
