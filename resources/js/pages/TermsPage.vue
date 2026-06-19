<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-white">
        <nav class="border-b border-gray-100 py-4 px-6">
            <div class="max-w-3xl mx-auto flex items-center justify-between">
                <RouterLink :to="{ name: 'home' }" class="font-bold text-gray-900 tracking-tight">
                    guestlist<span class="text-indigo-600">.</span>
                </RouterLink>
                <RouterLink :to="{ name: 'home' }" class="text-sm text-gray-500 hover:text-gray-900">← Back</RouterLink>
            </div>
        </nav>

        <div class="max-w-3xl mx-auto px-6 py-16">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Terms of Service</h1>
            <p class="text-sm text-gray-400 mb-10">Last updated: June 2026</p>

            <div class="space-y-8 text-sm leading-relaxed text-gray-700">

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">What guestlist. is</h2>
                    <p>guestlist. is a guest management tool for private events. It helps event hosts collect RSVPs, manage guest lists, send invitation emails, and track dietary and accessibility preferences.</p>
                    <p class="mt-2"><strong>guestlist. is not a ticketing platform and does not process payments to event attendees.</strong> The only financial transaction available is the one-time Event Pass upgrade ($19/event), which increases your guest limit for that specific event.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Acceptable use</h2>
                    <p>You may use guestlist. to manage guests for legitimate private events, corporate gatherings, and similar occasions. You agree not to:</p>
                    <ul class="list-disc list-inside space-y-1 mt-2">
                        <li>Use the service to send unsolicited email (spam)</li>
                        <li>Collect guest data without appropriate authorization</li>
                        <li>Attempt to circumvent plan limits or access controls</li>
                        <li>Resell or sublicense access to the service</li>
                        <li>Use the service for any unlawful purpose</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Your account</h2>
                    <p>You are responsible for maintaining the security of your account credentials. We are not liable for actions taken by unauthorized parties who access your account due to your failure to secure your credentials.</p>
                    <p class="mt-2">You may delete your account at any time from Account Settings. Deletion is immediate and permanent — all your events and guest data are removed.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Payments</h2>
                    <p>The Event Pass is a one-time, non-refundable purchase of $19 per event. It increases the guest limit for that specific event to 300. Payments are processed by Stripe and are subject to Stripe's terms.</p>
                    <p class="mt-2">The free plan is free forever with the limits described on the pricing page. We reserve the right to change pricing for new purchases with reasonable notice.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Your data</h2>
                    <p>You own the data you put into guestlist. — your events and guest lists. We store and process this data on your behalf to provide the service. See our <RouterLink :to="{ name: 'privacy' }" class="text-indigo-600 hover:underline">Privacy Policy</RouterLink> for details on how we handle it.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Service availability</h2>
                    <p>We aim for high availability but do not guarantee uptime. We may temporarily suspend the service for maintenance. We are not liable for losses arising from service interruptions.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Termination</h2>
                    <p>We reserve the right to suspend or terminate accounts that violate these terms. You may cancel your account at any time.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Contact</h2>
                    <p>Questions? Email <a href="mailto:hello@guestlist.io" class="text-indigo-600 hover:underline">hello@guestlist.io</a>.</p>
                </section>
            </div>
        </div>
    </div>
</template>
