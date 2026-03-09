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
    <Head title="Email Configuration" />

    <div class="space-y-6 pb-12">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Email Configuration</h2>
                <p class="text-sm text-gray-500 font-medium">Manage SMTP server settings for system notifications and correspondence.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Side: Main SMTP Settings -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-emerald-600 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-7-4h.01M7 16h.01" />
                        </svg>
                        Server Connection
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Mail Driver</label>
                            <select v-model="form.mail_mailer" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all cursor-pointer">
                                <option value="smtp">SMTP</option>
                                <option value="mailgun">Mailgun</option>
                                <option value="ses">Amazon SES</option>
                                <option value="postmark">Postmark</option>
                                <option value="sendmail">Sendmail</option>
                                <option value="log">Log (For Testing)</option>
                            </select>
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_mailer">{{ form.errors.mail_mailer }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Encryption Type</label>
                            <select v-model="form.mail_encryption" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all cursor-pointer">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_encryption">{{ form.errors.mail_encryption }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">SMTP Host</label>
                            <input type="text" v-model="form.mail_host" placeholder="smtp.gmail.com" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_host">{{ form.errors.mail_host }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">SMTP Port</label>
                            <input type="number" v-model="form.mail_port" placeholder="587" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_port">{{ form.errors.mail_port }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-blue-600 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Sender Identity
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">From Address</label>
                            <input type="email" v-model="form.mail_from_address" placeholder="noreply@nexus.com" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_from_address">{{ form.errors.mail_from_address }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">From Name</label>
                            <input type="text" v-model="form.mail_from_name" placeholder="Nexus System" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_from_name">{{ form.errors.mail_from_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Authentication & Help -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-orange-600 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Authentication
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Username</label>
                            <input type="text" v-model="form.mail_username" placeholder="API Key or Email" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_username">{{ form.errors.mail_username }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Password</label>
                            <input type="password" v-model="form.mail_password" placeholder="••••••••••••" class="w-full bg-gray-50/50 border border-gray-200 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block p-3 transition-all" />
                            <p class="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-tight" v-if="form.errors.mail_password">{{ form.errors.mail_password }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-emerald-50 rounded-2xl border border-emerald-100">
                    <h4 class="font-bold text-emerald-900 text-sm mb-2">Need help?</h4>
                    <p class="text-xs text-emerald-700 leading-relaxed font-medium">
                        Ensure your SMTP provider allows connections from this server's IP. For Gmail, you may need an 'App Password'.
                    </p>
                </div>

                <div class="pt-4">
                    <button type="submit" :disabled="form.processing" class="w-full px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold shadow-sm hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed group">
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <span>{{ form.processing ? 'Updating...' : 'Save Configuration' }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
