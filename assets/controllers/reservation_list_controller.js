import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mercureUrl: String
    }

    connect() {
        console.log("DEBUG: Reservation List Real-time Controller Connected");
        if (this.hasMercureUrlValue) {
            this.subscribeToUpdates();
        }
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    subscribeToUpdates() {
        if (!this.mercureUrlValue) return;

        console.log("DEBUG: Subscribing to reservation updates via Mercure:", this.mercureUrlValue);
        this.eventSource = new EventSource(this.mercureUrlValue);

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log("DEBUG: Reservation Update Event Received:", data);
                
                if (data.event === 'reservation_updated') {
                    console.log("DEBUG: Reservation updated. Reloading list via Turbo...");
                    // @ts-ignore
                    if (window.Turbo) {
                        // @ts-ignore
                        window.Turbo.visit(window.location.href, { action: 'replace' });
                    } else {
                        window.location.reload();
                    }
                }
            } catch (error) {
                console.error("Error parsing Mercure data:", error);
            }
        };

        this.eventSource.onerror = (e) => {
            console.error("Mercure connection lost for reservation updates. Reconnecting...");
            // EventSource will automatically reconnect, but we can log it.
        };
    }
}
