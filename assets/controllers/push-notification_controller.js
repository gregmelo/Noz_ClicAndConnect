import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];

    connect() {
        this.checkSubscription();
    }

    async checkSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            this.element.style.display = 'none';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        this.updateUI(subscription !== null);
    }

    async toggleSubscription() {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await this.unsubscribe(subscription);
        } else {
            await this.subscribe(registration);
        }
    }

    async subscribe(registration) {
        try {
            const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]').content;
            const convertedVapidKey = this.urlBase64ToUint8Array(vapidPublicKey);

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });

            if (response.ok) {
                this.updateUI(true);
            }
        } catch (error) {
            console.error('Failed to subscribe:', error);
            alert('Impossible d\'activer les notifications. Assurez-vous d\'avoir autorisé les notifications dans votre navigateur.');
        }
    }

    async unsubscribe(subscription) {
        try {
            const response = await fetch('/api/push/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: subscription.endpoint })
            });

            if (response.ok) {
                await subscription.unsubscribe();
                this.updateUI(false);
            }
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
        }
    }

    updateUI(isSubscribed) {
        if (this.hasButtonTarget) {
            this.buttonTarget.textContent = isSubscribed ? 'Désactiver les alertes' : 'Activer les alertes';
            this.buttonTarget.classList.toggle('bg-red-600', isSubscribed);
            this.buttonTarget.classList.toggle('bg-green-600', !isSubscribed);
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = isSubscribed ? 'Notifications activées' : 'Notifications désactivées';
            this.statusTarget.classList.toggle('text-green-600', isSubscribed);
            this.statusTarget.classList.toggle('text-gray-500', !isSubscribed);
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}
