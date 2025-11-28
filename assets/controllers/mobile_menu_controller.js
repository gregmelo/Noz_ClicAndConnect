import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        // Le menu est caché par défaut via la classe 'hidden' dans le HTML
    }

    toggle() {
        this.menuTarget.classList.toggle('hidden');
    }
}
