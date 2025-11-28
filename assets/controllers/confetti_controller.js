import { Controller } from '@hotwired/stimulus';
import confetti from 'canvas-confetti';

export default class extends Controller {
    static values = {
        amount: Number
    }

    connect() {
        console.log('Confetti controller connected. Amount:', this.amountValue);
        this.checkConfetti();
    }

    checkConfetti() {
        const amount = this.amountValue;
        const currentMilestone = Math.floor(amount / 1000) * 1000;
        const lastCelebrated = Number(localStorage.getItem('noz_confetti_milestone')) || 0;

        // If we reached a new milestone (e.g. 1000, 2000...) and it's greater than 0
        if (currentMilestone >= 1000 && currentMilestone > lastCelebrated) {
            console.log('Firing confetti! Milestone:', currentMilestone);
            this.fireConfetti();
            localStorage.setItem('noz_confetti_milestone', currentMilestone);
            
            // Optional: Show a toast or specific notification via JS if needed, 
            // but the template handles the static message.
        } else {
            console.log('No confetti. Current:', currentMilestone, 'Last:', lastCelebrated);
        }
    }

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
            // since particles fall down, start a bit higher than random
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: random(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: random(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
    }
}
