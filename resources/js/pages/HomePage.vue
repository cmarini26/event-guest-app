<script setup>
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth.js';
import {
    UsersRound,
    MailCheck,
    ClipboardList,
    CheckCircle2,
    ArrowRight,
    Zap,
    ShieldCheck,
    Calendar,
} from 'lucide-vue-next';

const auth = useAuthStore();

const features = [
    {
        icon: UsersRound,
        title: 'Guest management',
        description: 'Add guests manually or in bulk. Track dietary needs, accessibility requirements, seating preferences, and plus-ones in one place.',
    },
    {
        icon: MailCheck,
        title: 'Email invitations',
        description: 'Send personalized RSVP invitations with one click. Each guest gets a unique link — no login required on their end.',
    },
    {
        icon: ClipboardList,
        title: 'Real-time RSVP tracking',
        description: 'Watch responses come in live. See who\'s attending, who declined, and who\'s on the waitlist — exportable to CSV anytime.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Create your event',
        description: 'Set your date, venue, guest limit, and RSVP deadline. Configure which details you want to collect.',
    },
    {
        number: '02',
        title: 'Add and invite guests',
        description: 'Add guests individually or in bulk. Send invitation emails with a single click — each guest gets a personal RSVP link.',
    },
    {
        number: '03',
        title: 'Track responses',
        description: 'Monitor RSVPs in real time. Export your guest list to CSV whenever you need it.',
    },
];

const plans = [
    {
        name: 'Free',
        price: '$0',
        period: 'forever',
        description: 'Perfect for personal events.',
        features: [
            'Up to 3 active events',
            '50 guests per event',
            'Email invitations',
            'RSVP tracking',
            'CSV export',
            'Dietary & accessibility preferences',
        ],
        cta: 'Get started free',
        ctaTo: { name: 'register' },
        highlight: false,
    },
    {
        name: 'Event Pass',
        price: '$19',
        period: 'per event',
        description: 'For larger gatherings that need more room.',
        features: [
            'Everything in Free',
            '300 guests per event',
            'One-time purchase per event',
            'No subscription required',
        ],
        cta: 'Get started free',
        ctaTo: { name: 'register' },
        highlight: true,
        badge: 'Most popular',
    },
    {
        name: 'Pro',
        price: null,
        period: null,
        description: 'Unlimited events and guests for power users.',
        features: [
            'Unlimited active events',
            'Unlimited guests',
            'Sub-event support',
            'Custom domain',
            'Priority support',
        ],
        cta: 'Coming soon',
        ctaTo: null,
        highlight: false,
        comingSoon: true,
    },
];
</script>

<template>
    <div class="bg-white">
        <!-- Nav -->
        <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur border-b border-gray-100">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <RouterLink :to="{ name: 'home' }" class="font-bold text-gray-900 text-lg tracking-tight">
                    guestlist<span class="text-indigo-600">.</span>
                </RouterLink>
                <div class="flex items-center gap-3">
                    <RouterLink
                        v-if="auth.isAuthenticated"
                        :to="{ name: 'dashboard' }"
                        class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
                    >
                        Go to Dashboard
                    </RouterLink>
                    <template v-else>
                        <RouterLink
                            :to="{ name: 'login' }"
                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            Sign in
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'register' }"
                            class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
                        >
                            Get started free
                        </RouterLink>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="relative overflow-hidden pt-24 pb-28 px-6">
            <!-- Subtle grid background -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#f0f0f0_1px,transparent_1px),linear-gradient(to_bottom,#f0f0f0_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_80%_60%_at_50%_0%,black_40%,transparent_100%)]" />

            <div class="relative max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-50 border border-indigo-100 rounded-full text-xs font-medium text-indigo-700 mb-8">
                    <Zap class="w-3 h-3" />
                    Guest management — not ticketing, not payments
                </div>

                <h1 class="text-5xl sm:text-6xl font-bold text-gray-900 tracking-tight leading-tight mb-6">
                    The simplest way to<br class="hidden sm:block" />
                    <span class="text-indigo-600">manage your event guests</span>
                </h1>

                <p class="text-lg sm:text-xl text-gray-500 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Collect RSVPs, track dietary preferences, send invitation emails, and export your guest list — all from one clean dashboard.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <RouterLink
                        v-if="auth.isAuthenticated"
                        :to="{ name: 'dashboard' }"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition-colors"
                    >
                        Go to Dashboard
                        <ArrowRight class="w-4 h-4" />
                    </RouterLink>
                    <template v-else>
                        <RouterLink
                            :to="{ name: 'register' }"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition-colors"
                        >
                            Get started free
                            <ArrowRight class="w-4 h-4" />
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'login' }"
                            class="inline-flex items-center justify-center px-6 py-3 bg-white text-gray-700 font-medium rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors"
                        >
                            Sign in
                        </RouterLink>
                    </template>
                </div>

                <p class="mt-5 text-sm text-gray-400">Free forever · No credit card required</p>
            </div>
        </section>

        <!-- Trust bar -->
        <div class="border-y border-gray-100 bg-gray-50/50 py-4">
            <div class="max-w-4xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-center gap-6 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <ShieldCheck class="w-4 h-4 text-green-500" />
                    No login required for guests
                </div>
                <div class="hidden sm:block w-px h-4 bg-gray-200" />
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 text-green-500" />
                    Invitations delivered in seconds
                </div>
                <div class="hidden sm:block w-px h-4 bg-gray-200" />
                <div class="flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-green-500" />
                    Works for any size event
                </div>
            </div>
        </div>

        <!-- Features -->
        <section class="py-24 px-6">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Everything you need. Nothing you don't.</h2>
                    <p class="text-gray-500 max-w-xl mx-auto">Built specifically for hosts who want a clean, private guest experience — not a public ticket marketplace.</p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="group p-8 rounded-2xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all"
                    >
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center mb-5 group-hover:bg-indigo-100 transition-colors">
                            <component :is="feature.icon" class="w-5 h-5 text-indigo-600" />
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">{{ feature.title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="py-24 px-6 bg-gray-50">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Up and running in minutes</h2>
                    <p class="text-gray-500">No complex setup. No learning curve.</p>
                </div>

                <div class="grid md:grid-cols-3 gap-12">
                    <div v-for="step in steps" :key="step.number" class="relative">
                        <div class="text-5xl font-bold text-gray-100 mb-4 select-none">{{ step.number }}</div>
                        <h3 class="text-base font-semibold text-gray-900 mb-2">{{ step.title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ step.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section class="py-24 px-6">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Simple, transparent pricing</h2>
                    <p class="text-gray-500">Start free. Pay only when you need more room.</p>
                </div>

                <div class="grid md:grid-cols-3 gap-6 items-start">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        :class="[
                            'rounded-2xl border p-8 flex flex-col',
                            plan.highlight
                                ? 'border-indigo-200 bg-indigo-50 ring-1 ring-indigo-200'
                                : 'border-gray-200 bg-white',
                        ]"
                    >
                        <div class="flex items-start justify-between mb-1">
                            <h3 class="font-semibold text-gray-900">{{ plan.name }}</h3>
                            <span
                                v-if="plan.badge"
                                class="text-xs font-medium bg-indigo-600 text-white px-2 py-0.5 rounded-full"
                            >
                                {{ plan.badge }}
                            </span>
                        </div>

                        <div class="mb-4">
                            <template v-if="plan.price">
                                <span class="text-3xl font-bold text-gray-900">{{ plan.price }}</span>
                                <span class="text-sm text-gray-500 ml-1">/ {{ plan.period }}</span>
                            </template>
                            <span v-else class="text-3xl font-bold text-gray-400">TBA</span>
                        </div>

                        <p class="text-sm text-gray-500 mb-6">{{ plan.description }}</p>

                        <ul class="space-y-2.5 mb-8 flex-1">
                            <li
                                v-for="item in plan.features"
                                :key="item"
                                class="flex items-start gap-2.5 text-sm text-gray-600"
                            >
                                <CheckCircle2 class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                                {{ item }}
                            </li>
                        </ul>

                        <RouterLink
                            v-if="plan.ctaTo && !plan.comingSoon"
                            :to="plan.ctaTo"
                            :class="[
                                'text-center py-2.5 rounded-xl text-sm font-medium transition-colors',
                                plan.highlight
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                    : 'bg-gray-900 text-white hover:bg-gray-800',
                            ]"
                        >
                            {{ plan.cta }}
                        </RouterLink>
                        <div
                            v-else
                            class="text-center py-2.5 rounded-xl text-sm font-medium bg-gray-100 text-gray-400 cursor-default"
                        >
                            {{ plan.cta }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="py-24 px-6 bg-gray-900">
            <div class="max-w-2xl mx-auto text-center">
                <h2 class="text-3xl font-bold text-white mb-4">Ready to run a better event?</h2>
                <p class="text-gray-400 mb-8">Set up your first event in under two minutes. Free, no card required.</p>
                <RouterLink
                    v-if="auth.isAuthenticated"
                    :to="{ name: 'dashboard' }"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-white text-gray-900 font-medium rounded-xl hover:bg-gray-100 transition-colors"
                >
                    Go to Dashboard
                    <ArrowRight class="w-4 h-4" />
                </RouterLink>
                <RouterLink
                    v-else
                    :to="{ name: 'register' }"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-white text-gray-900 font-medium rounded-xl hover:bg-gray-100 transition-colors"
                >
                    Get started free
                    <ArrowRight class="w-4 h-4" />
                </RouterLink>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-100 bg-white py-8 px-6">
            <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <span class="font-bold text-gray-900 text-sm tracking-tight">
                    guestlist<span class="text-indigo-600">.</span>
                </span>
                <div class="flex items-center gap-6 text-sm text-gray-500">
                    <RouterLink :to="{ name: 'login' }" class="hover:text-gray-900 transition-colors">Sign in</RouterLink>
                    <RouterLink :to="{ name: 'register' }" class="hover:text-gray-900 transition-colors">Register</RouterLink>
                </div>
            </div>
        </footer>
    </div>
</template>
