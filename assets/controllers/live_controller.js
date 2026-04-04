import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["product", "status", "activateBtn", "deactivateBtn", "stock"];

    connect() {
        console.log("Live Dashboard Controller Connected");
    }

    async toggleFromSwitch(event) {
        const checkbox = event.currentTarget;
        const productId = checkbox.dataset.productId;
        const url = checkbox.checked ? checkbox.dataset.activateUrl : checkbox.dataset.deactivateUrl;
        const token = checkbox.checked ? checkbox.dataset.activateToken : checkbox.dataset.deactivateToken;
        const action = checkbox.checked ? 'activate' : 'deactivate';

        // Optimistic UI: disable during request
        checkbox.disabled = true;

        try {
            const formData = new FormData();
            formData.append('_token', token);

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.updateUI(productId, action);
                this.showToast(result.message, 'success');
            } else {
                this.showToast(result.error || 'Une erreur est survenue.', 'error');
                // revert state if server refused
                checkbox.checked = !checkbox.checked;
            }
        } catch (error) {
            console.error(error);
            this.showToast('Erreur de connexion au serveur.', 'error');
            checkbox.checked = !checkbox.checked;
        } finally {
            checkbox.disabled = false;
        }
    }

    async toggle(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const url = button.dataset.url;
        const productId = button.dataset.productId;
        const action = button.dataset.actionType;
        const token = button.dataset.token;

        // Visual feedback immediately
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');

        try {
            const formData = new FormData();
            formData.append('_token', token);

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.updateUI(productId, action);
                this.showToast(result.message, 'success');
            } else {
                this.showToast(result.error || 'Une erreur est survenue.', 'error');
            }
        } catch (error) {
            console.error(error);
            this.showToast('Erreur de connexion au serveur.', 'error');
        } finally {
            button.disabled = false;
            button.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    updateUI(productId, action) {
        // Find the row or element for this product
        const row = this.element.querySelector(`[data-product-id="${productId}"]`);
        if (!row) return;

        const activateBtn = row.querySelector('[data-action-type="activate"]');
        const deactivateBtn = row.querySelector('[data-action-type="deactivate"]');
        const statusBadge = row.querySelector('[data-live-target="status"]');

        if (action === 'activate') {
            if (activateBtn) {
                activateBtn.classList.add('hidden');
            }
            if (deactivateBtn) {
                deactivateBtn.classList.remove('hidden');
            }
            if (statusBadge) {
                statusBadge.textContent = 'En ligne';
                statusBadge.classList.replace('bg-gray-100', 'bg-green-100');
                statusBadge.classList.replace('text-gray-800', 'text-green-800');
            }
            row.classList.add('bg-green-50');
        } else {
            if (activateBtn) {
                activateBtn.classList.remove('hidden');
            }
            if (deactivateBtn) {
                deactivateBtn.classList.add('hidden');
            }
            if (statusBadge) {
                statusBadge.textContent = 'Hors ligne';
                statusBadge.classList.replace('bg-green-100', 'bg-gray-100');
                statusBadge.classList.replace('text-green-800', 'text-gray-800');
            }
            row.classList.remove('bg-green-50');
        }
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.textContent = message;

        toast.style.position = 'fixed';
        toast.style.bottom = '1.5rem';
        toast.style.right = '1.5rem';
        toast.style.maxWidth = '320px';
        toast.style.padding = '0.5rem 0.75rem';
        toast.style.borderRadius = '0.375rem';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)';
        toast.style.fontSize = '0.875rem';
        toast.style.color = '#ffffff';
        toast.style.zIndex = '9999';
        toast.style.backgroundColor = (type === 'success') ? '#15803d' : '#b91c1c';
        toast.style.transform = 'translateY(10px)';
        toast.style.opacity = '0';
        toast.style.transition = 'all 0.3s ease';

        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        });

        setTimeout(() => {
            toast.style.transform = 'translateY(10px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
