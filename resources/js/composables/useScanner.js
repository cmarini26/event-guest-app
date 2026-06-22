import { Capacitor } from '@capacitor/core';

/**
 * QR barcode scanner wrapper.
 *
 * On native: uses @capacitor-community/barcode-scanner (camera hardware).
 * On web: falls back to a browser prompt so hosts can paste a token for testing.
 *
 * Returns the scanned RSVP token string, or null if cancelled.
 */
export async function scanRsvpToken() {
    if (Capacitor.isNativePlatform()) {
        const { BarcodeScanner } = await import('@capacitor-community/barcode-scanner');

        await BarcodeScanner.checkPermission({ force: true });

        // Hide the WebView so the camera feed shows through
        document.body.style.background = 'transparent';
        BarcodeScanner.hideBackground();

        const result = await BarcodeScanner.startScan();

        BarcodeScanner.showBackground();
        document.body.style.background = '';

        if (!result.hasContent) return null;

        // RSVP URLs are https://domain/rsvp/<uuid>
        const url = result.content;
        const match = url.match(/\/rsvp\/([0-9a-f-]{36})/i);
        return match ? match[1] : url;
    }

    // Web fallback — prompt for token (dev / desktop testing)
    const input = prompt('Paste the guest\'s RSVP token or URL:');
    if (!input) return null;
    const match = input.match(/\/rsvp\/([0-9a-f-]{36})/i);
    return match ? match[1] : input.trim();
}
