import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour la gestion des notifications Push (Web Push).
 * Gère l'enregistrement du Service Worker, la souscription et la communication avec le serveur.
 */
export default class extends Controller {
    static targets = ['button', 'status'];

    connect() {
        // Initialisation automatique du service de push au chargement
        this.initializePush();
    }

    /**
     * Vérifie la compatibilité du navigateur et l'état actuel de l'abonnement.
     */
    async initializePush() {
        try {
            this.setStatus('🌐 Connexion...');

            // Vérification de la compatibilité du navigateur
            if (!('serviceWorker' in navigator)) {
                this.setError('Navigateur incompatible (SW)');
                return;
            }

            if (!('PushManager' in window)) {
                this.setError('Navigateur incompatible (Push)');
                return;
            }

            this.setStatus('⏳ En attente du Service Worker...');
            // On attend que le Service Worker soit prêt avec un timeout de sécurité
            const registration = await Promise.race([
                navigator.serviceWorker.ready,
                new Promise((_, reject) => setTimeout(() => reject(new Error('Le Service Worker est trop long à démarrer.')), 8000))
            ]);
            
            this.setStatus('🔍 Vérification de l\'abonnement...');
            
            // Récupération de l'abonnement existant
            const subscription = await registration.pushManager.getSubscription();
            
            this.updateUI(subscription !== null);

        } catch (error) {
            this.setError(error.message);
        }
    }

    /**
     * Met à jour le message d'état textuel.
     */
    setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }

    /**
     * Affiche une erreur et désactive les contrôles de notification.
     */
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

    /**
     * Alterne entre l'abonnement et le désabonnement lors du clic sur le bouton.
     */
    async toggleSubscription() {
        if (this.buttonTarget.disabled) return;
        
        // Vérification des permissions
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
            alert('Une erreur est survenue lors de l\'activation.');
            this.buttonTarget.textContent = originalText;
            this.buttonTarget.disabled = false;
        }
    }

    /**
     * Enregistre l'utilisateur auprès du service de Push du navigateur.
     */
    async subscribe(registration) {
        try {
            this.setStatus('🔔 Demande d\'autorisation...');
            
            // Force le renouvellement de l'abonnement si un ancien existe
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                await existingSubscription.unsubscribe();
            }

            // Récupération de la clé VAPID publique depuis les méta-données
            const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
            if (!vapidMeta || !vapidMeta.content) {
                throw new Error('Clé VAPID manquante');
            }

            // Nettoyage de la clé (suppression des quotes éventuelles)
            const rawKey = vapidMeta.content.trim().replace(/['"]/g, '');

            if (rawKey.length < 80) {
                throw new Error(`Clé VAPID invalide (trop courte)`);
            }

            const convertedVapidKey = this.urlBase64ToUint8Array(rawKey);

            // Souscription auprès du service push (browser side)
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            this.setStatus('📡 Enregistrement sur le serveur...');
            // Transmission de l'abonnement au serveur Symfony
            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });

            if (response.ok) {
                this.updateUI(true);
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'Erreur serveur lors de l\'abonnement');
            }
        } catch (error) {
            if (error.name === 'NotAllowedError') {
                alert('Vous devez autoriser les notifications pour utiliser cette fonctionnalité.');
            } else {
                alert(`Erreur d'activation : ${error.message}`);
            }
            this.updateUI(false);
        }
    }

    /**
     * Désinscrit l'utilisateur du service Push.
     */
    async unsubscribe(subscription) {
        try {
            this.setStatus('🔕 Désactivation en cours...');

            // Informer le serveur pour supprimer l'abonnement de la base de données
            const response = await fetch('/api/push/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'Erreur serveur lors de la désinscription');
            }

            // Se désabonner côté navigateur
            await subscription.unsubscribe();

            this.setStatus('🔕 Notifications désactivées');
            this.updateUI(false);
        } catch (error) {
            alert(`Erreur lors de la désactivation : ${error.message}`);
            this.updateUI(true);
        }
    }

    /**
     * Met à jour l'interface graphique (boutons et états).
     */
    updateUI(isSubscribed) {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = false;

            if (isSubscribed) {
                this.buttonTarget.textContent = 'Désactiver';
                this.buttonTarget.classList.remove('bg-gray-400');
                this.buttonTarget.classList.add('bg-noz-btn');
            } else {
                this.buttonTarget.textContent = 'Activer';
                this.buttonTarget.classList.remove('bg-noz-btn');
                this.buttonTarget.classList.add('bg-gray-400');
            }
        }

        if (this.hasStatusTarget) {
            this.statusTarget.classList.remove('text-red-500');
            if (isSubscribed) {
                this.statusTarget.textContent = '✅ Notifications activées';
                this.statusTarget.classList.add('text-green-600');
            } else {
                this.statusTarget.textContent = '🔕 Notifications désactivées';
                this.statusTarget.classList.remove('text-green-600');
                this.statusTarget.classList.add('text-blue-600');
            }
        }
    }

    /**
     * Utilitaire : Convertit une chaîne Base64 (VAPID) en Uint8Array pour le navigateur.
     */
    urlBase64ToUint8Array(base64String) {
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
        return outputArray;
    }
}
