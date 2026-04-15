<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

interface MailConfig {
    mailer: string;
    host: string;
    port: number;
    scheme: string;
    username: string | null;
    from_address: string;
    from_name: string;
}

const props = defineProps<{
    mailConfig: MailConfig;
    userEmail: string;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash);

const form = useForm({
    to: props.userEmail,
});

const submit = () => {
    form.post(route('settings.mail-test.send'), {
        preserveScroll: true,
    });
};

const schemeLabel = (scheme: string) => {
    const labels: Record<string, string> = {
        tls: 'TLS (STARTTLS)',
        ssl: 'SSL/TLS (Implicit)',
        none: 'Keine Verschluesselung',
    };
    return labels[scheme] || scheme;
};

const schemeColor = (scheme: string) => {
    if (scheme === 'tls' || scheme === 'ssl') return 'text-green-600 dark:text-green-400';
    return 'text-red-600 dark:text-red-400';
};
</script>

<template>
    <Head title="Mail-Test" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Mail-Konfiguration & Test
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Current Config -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Aktuelle Konfiguration</h3>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Mailer:</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-gray-100">{{ mailConfig.mailer }}</span>
                            <span v-if="mailConfig.mailer === 'log'" class="ml-2 text-xs text-amber-600 dark:text-amber-400">(nur Logdatei, kein Versand!)</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Host:</span>
                            <span class="ml-2 font-mono text-gray-900 dark:text-gray-100">{{ mailConfig.host }}:{{ mailConfig.port }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Verschluesselung:</span>
                            <span :class="['ml-2 font-medium', schemeColor(mailConfig.scheme)]">
                                {{ schemeLabel(mailConfig.scheme) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Username:</span>
                            <span class="ml-2 font-mono text-gray-900 dark:text-gray-100">{{ mailConfig.username || '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Absender:</span>
                            <span class="ml-2 text-gray-900 dark:text-gray-100">{{ mailConfig.from_name }} &lt;{{ mailConfig.from_address }}&gt;</span>
                        </div>
                    </div>

                    <!-- .env Help -->
                    <details class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <summary class="cursor-pointer p-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750">
                            .env Konfiguration anzeigen
                        </summary>
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                            <pre class="rounded-lg bg-gray-900 p-4 text-sm text-green-400 overflow-x-auto"><code>MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=geheim
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Claude Code Brain"</code></pre>

                            <div class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                <p><strong>MAIL_SCHEME Optionen:</strong></p>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">tls</code> — Port 587, STARTTLS (empfohlen)</li>
                                    <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">ssl</code> — Port 465, Implicit SSL/TLS</li>
                                    <li>Leer lassen — Port 25, unverschluesselt (nicht empfohlen)</li>
                                </ul>
                                <p class="mt-3"><strong>Typische Konfigurationen:</strong></p>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li><strong>Gmail:</strong> Host=smtp.gmail.com, Port=587, Scheme=tls</li>
                                    <li><strong>Outlook:</strong> Host=smtp.office365.com, Port=587, Scheme=tls</li>
                                    <li><strong>Mailgun:</strong> Host=smtp.mailgun.org, Port=587, Scheme=tls</li>
                                    <li><strong>All-Inkl:</strong> Host=smtp.strato.de, Port=465, Scheme=ssl</li>
                                </ul>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Test Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Test-Mail senden</h3>

                    <!-- Success -->
                    <div v-if="flash?.success" class="mb-4 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ flash.success }}</p>
                    </div>

                    <!-- Error -->
                    <div v-if="flash?.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4">
                        <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ flash.error }}</p>
                    </div>

                    <form @submit.prevent="submit" class="flex items-end gap-3">
                        <div class="flex-1">
                            <InputLabel for="test-email" value="Empfaenger" />
                            <TextInput
                                id="test-email"
                                v-model="form.to"
                                type="email"
                                class="w-full mt-1"
                                placeholder="test@example.com"
                                required
                            />
                            <InputError :message="form.errors.to" class="mt-1" />
                        </div>
                        <PrimaryButton :disabled="form.processing" class="shrink-0">
                            <span v-if="form.processing">Wird gesendet...</span>
                            <span v-else>Test-Mail senden</span>
                        </PrimaryButton>
                    </form>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Sendet eine einfache Test-Nachricht an die angegebene Adresse.
                        Fehlermeldungen werden direkt angezeigt.
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
