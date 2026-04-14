import { Controller } from '@hotwired/stimulus';
import confetti from 'canvas-confetti';

/**
 * Contrôleur Stimulus pour le déclenchement des célébrations (confettis).
 * Active une animation de confettis lorsque certains paliers de revenus sont atteints.
 */
export default class extends Controller {
    static values = {
        amount: Number
    }

    connect() {
        // Vérification automatique au chargement de la page (Stats de l'employé)
        this.checkConfetti();
    }

    /**
     * Compare le montant actuel avec le dernier palier célébré.
     */
    checkConfetti() {
        const amount = this.amountValue;
        // Calcul du palier de 1000€ le plus proche (ex: 1050 -> 1000, 2100 -> 2000)
        const currentMilestone = Math.floor(amount / 1000) * 1000;
        const lastCelebrated = Number(localStorage.getItem('noz_confetti_milestone')) || 0;

        // Déclenchement si un nouveau palier de 1000€ est franchi
        if (currentMilestone >= 1000 && currentMilestone > lastCelebrated) {
            this.fireConfetti();
            // Sauvegarde locale pour éviter les répétitions infinies au rafraîchissement
            localStorage.setItem('noz_confetti_milestone', currentMilestone);
        }
    }

    /**
     * Lance l'animation de confettis pendant quelques secondes de chaque côté de l'écran.
     */
    fireConfetti() {
        var duration = 3 * 1000;
        var animationEnd = Date.now() + duration;
        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

        function random(min, max) {
            return Math.random() * (max - min) + min;
        }

        var interval = setInterval(function() {
            var timeLeft = animationEnd - Date.now();

            if (timeLeft <= 0) {
                return clearInterval(interval);
            }

            var particleCount = 50 * (timeLeft / duration);
            
            // Lancer de confettis à gauche
            confetti(Object.assign({}, defaults, { 
                particleCount, 
                origin: { x: random(0.1, 0.3), y: Math.random() - 0.2 } 
            }));
            
            // Lancer de confettis à droite
            confetti(Object.assign({}, defaults, { 
                particleCount, 
                origin: { x: random(0.7, 0.9), y: Math.random() - 0.2 } 
            }));
        }, 250);
    }
}
