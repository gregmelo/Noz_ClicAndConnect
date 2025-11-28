import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        // console.log('Autosubmit controller connected');
    }

    submit() {
        this.element.requestSubmit();
    }

    debouncedSubmit() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.submit();
        }, 400);
    }
}
