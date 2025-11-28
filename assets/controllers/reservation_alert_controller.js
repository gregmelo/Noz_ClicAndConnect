import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        refreshInterval: { type: Number, default: 30000 }
    }

    static targets = ["badge", "count"]

    connect() {
        if (this.hasUrlValue) {
            this.checkNewReservations();
            this.startPolling();
        }
    }

    disconnect() {
        this.stopPolling();
    }

    startPolling() {
        this.timer = setInterval(() => {
            this.checkNewReservations();
        }, this.refreshIntervalValue);
    }

    stopPolling() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    }

    async checkNewReservations() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();
            this.updateBadge(data.count);
        } catch (error) {
            console.error('Error checking new reservations:', error);
        }
    }

    updateBadge(count) {
        if (count > 0) {
            this.badgeTarget.classList.remove('hidden');
            this.countTarget.textContent = count;
            
            // Optional: Play sound or animate if count increased (requires tracking previous count)
            // For now, just showing the badge is enough as per MVP.
        } else {
            this.badgeTarget.classList.add('hidden');
        }
    }
}
