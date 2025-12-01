import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        console.log('📱 Mobile Menu Controller connected');
    }

    toggle(event) {
        console.log('🍔 Mobile Menu Toggle clicked');
        if (event) event.preventDefault();
        this.menuTarget.classList.toggle('hidden');
    }
}
