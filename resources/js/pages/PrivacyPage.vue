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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
            <p class="text-sm text-gray-400 mb-10">Last updated: June 2026</p>

            <div class="prose prose-gray max-w-none space-y-8 text-sm leading-relaxed text-gray-700">

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">What we collect</h2>
                    <p>When you create an account, we collect your name and email address. If you sign in with Google, we receive your Google profile name and email. We do not collect your Google password.</p>
                    <p class="mt-2">When you create events, we store the event details you provide: name, date, venue, description, and RSVP settings. When you add guests, we store their names, email addresses, phone numbers (if provided), and RSVP responses including dietary preferences, accessibility needs, and seating preferences.</p>
                    <p class="mt-2">We collect standard server logs (IP addresses, request timestamps, user agent) for security and debugging purposes.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">How we use it</h2>
                    <ul class="list-disc list-inside space-y-1">
                        <li>To provide the guestlist. service — creating events, managing guests, sending invitation emails</li>
                        <li>To send transactional emails: RSVP invitations and notifications you explicitly trigger</li>
                        <li>To process payments for the Event Pass upgrade via Stripe</li>
                        <li>To authenticate you securely with your email/password or Google account</li>
                        <li>To respond to support requests</li>
                    </ul>
                    <p class="mt-2">We do not sell your data. We do not use your data for advertising.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Third-party services</h2>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Resend</strong> — transactional email delivery. Guest invitation emails and RSVP notifications pass through Resend's infrastructure.</li>
                        <li><strong>Stripe</strong> — payment processing for the Event Pass. We do not store your full card number; Stripe holds payment information under their own privacy policy.</li>
                        <li><strong>Google OAuth</strong> — optional sign-in method. If you choose "Continue with Google," Google's privacy policy governs that authentication step.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Guest data</h2>
                    <p>Your guests' data (names, emails, RSVP responses, preferences) is stored on your behalf and remains yours. You are responsible for having appropriate permission to collect and store your guests' information. We process this data only to provide the service to you.</p>
                    <p class="mt-2">Guests who receive RSVP links may submit their own data through the public RSVP form. That data is associated with the event you created and visible to you as the event host.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Data retention and deletion</h2>
                    <p>Your data is retained for as long as your account exists. When you delete your account via Account Settings, all your events, guest lists, and RSVP data are permanently deleted immediately. This action cannot be undone.</p>
                    <p class="mt-2">Authentication tokens expire after 30 days of inactivity. Expired tokens are pruned daily.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Security</h2>
                    <p>Passwords are hashed using bcrypt and never stored in plaintext. All traffic is served over HTTPS. Authentication tokens are generated using cryptographically secure random bytes.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Your rights</h2>
                    <p>You can export your guest list data at any time as a CSV from any event page. You can update your name and email in Account Settings. You can delete your account and all associated data in Account Settings under Danger Zone.</p>
                    <p class="mt-2">For any other privacy requests, contact us at privacy@guestlist.io.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Contact</h2>
                    <p>Questions about this policy? Email <a href="mailto:privacy@guestlist.io" class="text-indigo-600 hover:underline">privacy@guestlist.io</a>.</p>
                </section>
            </div>
        </div>
    </div>
</template>
