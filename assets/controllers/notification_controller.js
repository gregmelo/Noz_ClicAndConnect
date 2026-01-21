console.log('DEBUG: notification_controller.js loaded');
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];

    connect() {
        console.log('Notification controller connected');
        alert('DEBUG: Contrôleur Notification Détecté !');
        this.initializePush();
    }

    async initializePush() {
        try {
            console.log('[Push] Starting initialization process...');
            this.setStatus('🌐 Connexion...');

            if (!('serviceWorker' in navigator)) {
                console.warn('[Push] Browser does not support Service Workers');
                this.setError('Navigateur incompatible (SW)');
                return;
            }

            if (!('PushManager' in window)) {
                console.warn('[Push] Browser does not support Push Notifications');
                this.setError('Navigateur incompatible (Push)');
                return;
            }

            this.setStatus('⏳ En attente du Service Worker...');
            const registration = await Promise.race([
                navigator.serviceWorker.ready,
                new Promise((_, reject) => setTimeout(() => reject(new Error('Le Service Worker est trop long à démarrer.')), 8000))
            ]);
            
            console.log('[Push] Service Worker ready:', registration.scope);
            this.setStatus('🔍 Vérification de l\'abonnement...');
            
            const subscription = await registration.pushManager.getSubscription();
            console.log('[Push] Subscription status:', subscription ? 'Subscribed' : 'Not subscribed');
            
            this.updateUI(subscription !== null);

        } catch (error) {
            console.error('[Push] Initialization error:', error);
            this.setError(error.message);
        }
    }

    setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }

    setError(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = `🚫 ${message}`;
            this.statusTarget.classList.add('text-red-500');
        }
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
            this.buttonTarget.textContent = 'Indisponible';
            this.buttonTarget.classList.add('bg-gray-400');
        }
    }

    async toggleSubscription() {
        if (this.buttonTarget.disabled) return;
        
        // Final check before proceeding
        if (Notification.permission === 'denied') {
            alert('Vous avez bloqué les notifications. Veuillez les réactiver dans les paramètres de votre navigateur.');
            return;
        }

        this.buttonTarget.disabled = true;
        const originalText = this.buttonTarget.textContent;
        this.buttonTarget.textContent = 'Opération...';

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await this.unsubscribe(subscription);
            } else {
                await this.subscribe(registration);
            }
        } catch (error) {
            console.error('[Push] Toggle failed:', error);
            alert('Une erreur est survenue lors de l\'activation.');
            this.buttonTarget.textContent = originalText;
            this.buttonTarget.disabled = false;
        }
    }

    async subscribe(registration) {
        try {
            this.setStatus('🔔 Demande d\'autorisation...');
            const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
            if (!vapidMeta || !vapidMeta.content || vapidMeta.content.includes('{')) {
                throw new Error('Clé VAPID manquante ou invalide');
            }

            const convertedVapidKey = this.urlBase64ToUint8Array(vapidMeta.content);

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            this.setStatus('📡 Enregistrement sur le serveur...');
            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });

            if (response.ok) {
                console.log('[Push] Successfully subscribed and saved on server');
                this.updateUI(true);
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'Erreur serveur lors de l\'abonnement');
            }
        } catch (error) {
            console.error('[Push] Subscription failed:', error);
            if (error.name === 'NotAllowedError') {
                alert('Vous devez autoriser les notifications pour utiliser cette fonctionnalité.');
            } else {
                alert(`Erreur d'activation : ${error.message}`);
            }
            this.updateUI(false);
        }
    }

    async unsubscribe(subscription) {
        try {
            this.setStatus('🔕 Désactivation...');
            const response = await fetch('/api/push/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: subscription.endpoint })
            });

            if (response.ok) {
                await subscription.unsubscribe();
                console.log('[Push] Successfully unsubscribed');
                this.updateUI(false);
            } else {
                throw new Error('Erreur serveur lors de la désactivation');
            }
        } catch (error) {
            console.error('[Push] Unsubscribe failed:', error);
            alert(`Erreur de désactivation : ${error.message}`);
            this.updateUI(true); // Re-set UI to subscribed because server/local sync failed
        }
    }

    updateUI(isSubscribed) {
        let statusText = isSubscribed ? 'Notifications activées' : 'Alertes désactivées';
        let statusColor = isSubscribed ? 'text-green-600' : 'text-gray-500';

        if (window.Notification && Notification.permission === 'denied') {
            statusText = '🚫 Notifications bloquées';
            statusColor = 'text-red-500';
        }

        if (this.hasStatusTarget) {
            this.statusTarget.textContent = statusText;
            this.statusTarget.className = `text-sm font-medium ${statusColor}`;
            if (this.hasStatusTarget) {
                 // The pulse dot is purely cosmetic in HTML, but we could toggle it here if needed
            }
        }

        if (this.hasButtonTarget) {
            this.buttonTarget.textContent = isSubscribed ? 'Désactiver' : 'Activer';
            this.buttonTarget.classList.toggle('bg-red-600', isSubscribed);
            this.buttonTarget.classList.toggle('bg-green-600', !isSubscribed);
            this.buttonTarget.classList.remove('bg-gray-400');
            this.buttonTarget.disabled = false;
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
