<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    setting: {
        type: Object,
        required: true,
    }
});

defineOptions({ layout: NexusLayout });

const form = useForm({
    mail_mailer: props.setting.mail_mailer || 'smtp',
    mail_host: props.setting.mail_host || '',
    mail_port: props.setting.mail_port || 587,
    mail_username: props.setting.mail_username || '',
    mail_password: props.setting.mail_password || '',
    mail_encryption: props.setting.mail_encryption || 'tls',
    mail_from_address: props.setting.mail_from_address || '',
    mail_from_name: props.setting.mail_from_name || '',
});

const submit = () => {
    form.post(route('settings.email.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Email Settings" />

    <div class="p-6 sm:p-8 max-w-4xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 pb-6 border-b border-gray-100">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 mb-1 flex items-center gap-2">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Email Configuration
                </h1>
                <p class="text-sm text-gray-500 font-medium">Manage SMTP server settings for system notifications and forgot password operations.</p>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 p-8 sm:p-10">
            
            <div v-if="$page.props.flash?.success" class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-emerald-800">{{ $page.props.flash.success }}</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Mailer & Encryption -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mail Driver / Mailer</label>
                        <select v-model="form.mail_mailer" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors cursor-pointer">
                            <option value="smtp">SMTP</option>
                            <option value="mailgun">Mailgun</option>
                            <option value="ses">Amazon SES</option>
                            <option value="postmark">Postmark</option>
                            <option value="sendmail">Sendmail</option>
                            <option value="log">Log (For Testing)</option>
                        </select>
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_mailer">{{ form.errors.mail_mailer }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Encryption Type</label>
                        <select v-model="form.mail_encryption" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors cursor-pointer">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="">None</option>
                        </select>
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_encryption">{{ form.errors.mail_encryption }}</p>
                    </div>
                </div>

                <!-- Host & Port -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Host</label>
                        <input type="text" v-model="form.mail_host" placeholder="smtp.mailtrap.io or smtp.gmail.com" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_host">{{ form.errors.mail_host }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Port</label>
                        <input type="number" v-model="form.mail_port" placeholder="587, 465, or 2525" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_port">{{ form.errors.mail_port }}</p>
                    </div>
                </div>

                <!-- Username & Password -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                        <input type="text" v-model="form.mail_username" placeholder="user@example.com" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_username">{{ form.errors.mail_username }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password / App Password</label>
                        <input type="password" v-model="form.mail_password" placeholder="••••••••••••••••" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_password">{{ form.errors.mail_password }}</p>
                    </div>
                </div>

                <div class="py-4">
                    <hr class="border-gray-100" />
                </div>

                <!-- From Address & Name -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Address</label>
                        <input type="email" v-model="form.mail_from_address" placeholder="noreply@yourdomain.com" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_from_address">{{ form.errors.mail_from_address }}</p>
                        <p class="mt-1 text-xs text-gray-500">The email address the system will use as the sender.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Name</label>
                        <input type="text" v-model="form.mail_from_name" placeholder="Planly Notification System" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3.5 transition-colors" />
                        <p class="mt-1.5 text-xs text-red-500" v-if="form.errors.mail_from_name">{{ form.errors.mail_from_name }}</p>
                        <p class="mt-1 text-xs text-gray-500">The human-readable sender name.</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition-all duration-300 shadow-lg shadow-emerald-900/10 hover:shadow-emerald-900/20 active:scale-95 disabled:opacity-50 min-w-[200px]">
                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        {{ form.processing ? 'Saving Configuration...' : 'Save Configuration' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
