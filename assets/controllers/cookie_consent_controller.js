import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['banner'];

    connect() {
        const consent = this.getConsent();
        if (!consent) {
            this.bannerTarget.classList.remove('hidden');
        }
    }

    accept() {
        this.setConsent('accepted');
        this.bannerTarget.classList.add('hidden');
    }

    reject() {
        this.setConsent('rejected');
        this.bannerTarget.classList.add('hidden');
    }

    setConsent(value) {
        const now = new Date();
        // 13 months in milliseconds: 13 * 30.44 * 24 * 60 * 60 * 1000 (approx)
        // Or more simply: set month + 13
        const expiry = new Date(now.setMonth(now.getMonth() + 13)).getTime();
        
        const item = {
            value: value,
            expiry: expiry
        };
        
        localStorage.setItem('cookie_consent', JSON.stringify(item));
    }

    getConsent() {
        const itemStr = localStorage.getItem('cookie_consent');
        if (!itemStr) {
            return null;
        }

        try {
            const item = JSON.parse(itemStr);
            const now = new Date();

            if (now.getTime() > item.expiry) {
                localStorage.removeItem('cookie_consent');
                return null;
            }

            return item.value;
        } catch (e) {
            // In case of parsing error or old format
            localStorage.removeItem('cookie_consent');
            return null;
        }
    }
}
