import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mercureUrl: String,
        url: String, // Fallback / Initial load
        refreshInterval: { type: Number, default: 60000 } // Poll less frequently if Mercure is active
    }

    static targets = ["badge", "count"]

    connect() {
        console.log("DEBUG: Reservation Alert Controller Connected");
        this.checkNewReservations();
        
        if (this.hasMercureUrlValue) {
            this.subscribeToMercure();
        } else {
            this.startPolling();
        }
    }

    disconnect() {
        this.stopPolling();
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    subscribeToMercure() {
        if (!this.mercureUrlValue) return;

        console.log("DEBUG: Subscribing to reservation stats via Mercure:", this.mercureUrlValue);
        this.eventSource = new EventSource(this.mercureUrlValue);

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log("DEBUG: Reservation Mercure event:", data);
                if (data.event === 'reservation_count_updated') {
                    this.updateBadge(data.count);
                }
            } catch (error) {
                console.error("Error parsing Mercure data:", error);
            }
        };

        this.eventSource.onerror = () => {
            console.error("Mercure connection lost for reservations. Falling back to polling.");
            this.eventSource.close();
            this.startPolling();
        };
    }

    startPolling() {
        this.stopPolling();
        this.timer = setInterval(() => {
            this.checkNewReservations();
        }, this.refreshIntervalValue);
    }

    stopPolling() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    async checkNewReservations() {
        if (!this.hasUrlValue) return;
        
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            this.updateBadge(data.count);
        } catch (error) {
            console.error('Error checking new reservations:', error);
        }
    }

    updateBadge(count) {
        if (this.hasCountTarget) {
            this.countTarget.textContent = count;
        }
        
        if (this.hasBadgeTarget) {
            if (count > 0) {
                this.badgeTarget.classList.remove('hidden');
                // Optional: Add a subtle animation on increment
                this.badgeTarget.classList.add('animate-pulse');
                setTimeout(() => this.badgeTarget.classList.remove('animate-pulse'), 2000);
            } else {
                this.badgeTarget.classList.add('hidden');
            }
        }
    }
}
