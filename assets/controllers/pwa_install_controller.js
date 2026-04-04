import { Controller } from '@hotwired/stimulus';

// Bandeau d'installation PWA sur la page d'accueil
// S'affiche uniquement quand le navigateur propose l'installation
// et que l'utilisateur ne l'a pas déjà masqué.
export default class extends Controller {
    connect() {
        this.deferredPrompt = null;
        this.handleBeforeInstallPrompt = this.onBeforeInstallPrompt.bind(this);

        // Caché par défaut via la classe `hidden` dans le template
        this.hide();

        // Environnement non compatible ou déjà en mode "installé" -> on ne montre jamais le bandeau
        if (!('serviceWorker' in navigator)) {
            return;
        }

        // Détecte si l'appli est déjà installée (Chrome / iOS Safari)
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isStandalone) {
            return;
        }

        // L'utilisateur a déjà masqué le bandeau
        if (window.localStorage.getItem('pwa-install-dismissed') === '1') {
            return;
        }

        window.addEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);
    }

    disconnect() {
        window.removeEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);
    }

    onBeforeInstallPrompt(event) {
        // Empêche le prompt natif, on le déclenche sur clic du bouton
        event.preventDefault();
        this.deferredPrompt = event;
        this.show();
    }

    async install(event) {
        event.preventDefault();

        if (!this.deferredPrompt) {
            this.rememberDismissed();
            this.hide();
            return;
        }

        try {
            this.element.classList.add('pointer-events-none', 'opacity-80');

            this.deferredPrompt.prompt();
            const choiceResult = await this.deferredPrompt.userChoice;
            // Qu'il accepte ou refuse, on ne ré-affiche pas automatiquement le bandeau
            this.deferredPrompt = null;
            this.rememberDismissed();
            this.hide();
        } catch (e) {
            console.error('[PWA] Install prompt failed:', e);
            this.rememberDismissed();
            this.hide();
        }
    }

    dismiss(event) {
        if (event) {
            event.preventDefault();
        }
        this.rememberDismissed();
        this.hide();
    }

    show() {
        this.element.classList.remove('hidden');
    }

    hide() {
        this.element.classList.add('hidden');
    }

    rememberDismissed() {
        try {
            window.localStorage.setItem('pwa-install-dismissed', '1');
        } catch (e) {
            console.warn('[PWA] Unable to persist dismissal state:', e);
        }
    }
}
