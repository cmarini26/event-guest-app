import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.guestlist.app',
    appName: 'guestlist.',
    // Points to Vite's output directory — run `npm run build` before `npx cap sync`
    webDir: 'public/build',
    server: {
        // During development, point to your local Laravel dev server so hot-reload works.
        // Comment this out for production builds.
        // url: 'http://192.168.1.x:8000',
        // cleartext: true,
    },
    plugins: {
        PushNotifications: {
            presentationOptions: ['badge', 'sound', 'alert'],
        },
    },
    ios: {
        // Set your team ID after creating an Apple Developer account.
        // Run: npx cap open ios  → set Signing & Capabilities in Xcode
    },
    android: {
        // google-services.json must be placed at android/app/google-services.json
        // after downloading it from Firebase Console.
    },
};

export default config;
