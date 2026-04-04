import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["container", "noProductMessage"];
    static values = {
        url: String
    };

    connect() {
        console.log("Live Display Controller Connected to: " + this.urlValue);
        this.initializeSSE();
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    initializeSSE() {
        this.eventSource = new EventSource(this.urlValue);

        this.eventSource.onmessage = (event) => {
            try {
                const products = JSON.parse(event.data);
                this.updateDisplay(products);
            } catch (error) {
                console.error("Error parsing SSE data", error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error("SSE Error:", error);
            // Browser usually reconnects automatically, but we can log it.
        };
    }

    updateDisplay(products) {
        if (!this.hasContainerTarget) return;

        const countdownEl = document.getElementById('next-live-countdown');

        if (products.length === 0) {
            this.containerTarget.innerHTML = '';
            if (this.hasNoProductMessageTarget) {
                this.noProductMessageTarget.classList.remove('hidden');
            }
            if (countdownEl) {
                countdownEl.classList.remove('hidden');
            }
            return;
        }

        if (this.hasNoProductMessageTarget) {
            this.noProductMessageTarget.classList.add('hidden');
        }

        if (countdownEl) {
            countdownEl.classList.add('hidden');
        }

        // Simple approach: Re-render the list or update existing cards
        // For a smoother experience, we'll check which ones changed
        // But for now, we'll do a robust update of the container
        
        // Note: In a real production app, using a template system or morphing would be better
        // Here we'll update the stock display for existing ones and add new ones if they appear
        
        // Basic implementation: replace content if counts differ or just always for now
        // since we want "instant" feel and we don't have many live products at once.
        let html = '';
        products.forEach(p => {
            html += this.createProductCard(p);
        });
        
        this.containerTarget.innerHTML = html;
    }

    createProductCard(p) {
        const price = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(p.price);
        const originalPriceHtml = p.originalPrice ? `<span class="text-sm line-through text-gray-400 ml-2">${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(p.originalPrice)}</span>` : '';
        
        const imagePath = p.image ? `/uploads/images/${p.image}` : '/uploads/images/no-image.png';

        return `
            <div class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100 transition-all hover:shadow-xl group">
                <div class="relative h-48 sm:h-64 overflow-hidden">
                    <img src="${imagePath}" alt="${p.name}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                    <div class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse flex items-center gap-1">
                        <span class="w-2 h-2 bg-white rounded-full"></span> EN LIVE
                    </div>
                    ${p.stock <= 0 ? '<div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white font-bold text-xl uppercase tracking-widest">Épuisé</div>' : ''}
                </div>
                <div class="p-5">
                    <h3 class="text-lg font-bold text-gray-900 mb-1 group-hover:text-noz-blue transition-colors">${p.name}</h3>
                    <div class="flex items-baseline mb-4">
                        <span class="text-2xl font-black text-noz-blue">${price}</span>
                        ${originalPriceHtml}
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <span class="${p.stock <= 5 ? 'text-red-600 font-bold' : 'text-gray-500'}">
                                ${p.stock > 0 ? `Stock : ${p.stock}` : 'Rupture de stock'}
                            </span>
                        </div>
                        <a href="/produit/${p.id}" class="bg-noz-yellow text-noz-black font-bold px-4 py-2 rounded-lg hover:bg-yellow-400 transition-all flex items-center gap-2 ${p.stock <= 0 ? 'opacity-50 pointer-events-none grayscale' : ''}">
                           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                           Détail
                        </a>
                    </div>
                </div>
            </div>
        `;
    }
}
