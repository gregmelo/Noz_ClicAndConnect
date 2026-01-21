import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        this.close(new Event('click')); // Ensure closed on load if needed, though CSS handles it
    }

    toggle(event) {
        event.stopPropagation();
        this.menuTarget.classList.toggle('hidden');
    }

    close(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
        }
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.menuTarget.classList.add('hidden');
        }
    }
}
