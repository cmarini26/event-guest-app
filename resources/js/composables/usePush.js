import { Capacitor } from '@capacitor/core';
import axios from 'axios';

let pushInitialized = false;

/**
 * Register for push notifications and send the device token to the server.
 * Safe to call on web — exits immediately when not running in a native shell.
 */
export async function registerPush() {
    if (!Capacitor.isNativePlatform()) return;
    if (pushInitialized) return;

    const { PushNotifications } = await import('@capacitor/push-notifications');

    const permission = await PushNotifications.requestPermissions();
    if (permission.receive !== 'granted') return;

    await PushNotifications.register();

    PushNotifications.addListener('registration', async ({ value: token }) => {
        pushInitialized = true;
        const platform = Capacitor.getPlatform(); // 'ios' or 'android'
        try {
            await axios.post('/api/device-tokens', { token, platform });
        } catch {
            // best-effort; server will pick it up on next launch
        }
    });

    PushNotifications.addListener('pushNotificationReceived', notification => {
        console.debug('[push] foreground:', notification);
    });

    PushNotifications.addListener('pushNotificationActionPerformed', action => {
        const eventId = action.notification.data?.event_id;
        if (eventId) {
            window.location.hash = `/events/${eventId}`;
        }
    });
}
