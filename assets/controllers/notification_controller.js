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
            
            // --- NEW: Force fresh subscription by unsubscribing existing one first ---
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                console.log('[Push] Existing subscription found, unsubscribing to force fresh token...');
                await existingSubscription.unsubscribe();
            }
            // -----------------------------------------------------------------------

            const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
            if (!vapidMeta || !vapidMeta.content) {
                throw new Error('Clé VAPID manquante');
            }

            // Remove all whitespace and potential quotes (can happen with some template engines)
            const rawKey = vapidMeta.content.trim().replace(/['"]/g, '');
            console.log(`[Push] Original VAPID Key: ${rawKey}`);
            console.log(`[Push] Key Length: ${rawKey.length}`);

            if (rawKey.length < 80) {
                throw new Error(`Clé VAPID invalide (trop courte: ${rawKey.length} chars)`);
            }

            const convertedVapidKey = this.urlBase64ToUint8Array(rawKey);
            console.log(`[Push] Converted bytes length: ${convertedVapidKey.length}`);
            console.log(`[Push] First byte: ${convertedVapidKey[0]} (Should be 4 for P-256)`);

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
            } else if (error.message.includes('P-256')) {
                alert(`Erreur Apple (P-256) : La clé VAPID semble invalide pour Safari. Longueur convertie: ${this.lastByteLength || '?'}`);
            } else {
                alert(`Erreur d'activation : ${error.message}`);
            }
            this.updateUI(false);
        }
    }

    // ... (rest of methods)

    urlBase64ToUint8Array(base64String) {
        // Cleaning the string again to be safe
        const base64Clean = base64String.trim().replace(/['"]/g, '');
        const padding = '='.repeat((4 - base64Clean.length % 4) % 4);
        const base64 = (base64Clean + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        this.lastByteLength = outputArray.length;
        return outputArray;
    }
}
