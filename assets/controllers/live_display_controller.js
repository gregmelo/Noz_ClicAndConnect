import { Controller } from "@hotwired/stimulus";

/**
 * Contrôleur Stimulus pour l'affichage dynamique des produits "EN LIVE".
 * Gère le chargement initial et les mises à jour en temps réel via Mercure Hub.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["container", "noProductMessage", "count", "badge", "countdownContainer"];
    static values = {
        mercureUrl: String,
        initialUrl: String,
        imagesPath: String,
    };

    connect() {
        // Initialisation de la vue live
        this.productCount = 0;
        this.loadInitialProducts();
        this.subscribeToMercure();
        this.startViewerPing();
    }

    disconnect() {
        // Fermeture de la connexion Mercure pour libérer les ressources
        if (this.eventSource) {
            this.eventSource.close();
        }
        this.stopViewerPing();
    }

    startViewerPing() {
        this.pingViewers();
        this.pingInterval = setInterval(() => this.pingViewers(), 30000);
    }

    stopViewerPing() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
        }
    }

    pingViewers() {
        fetch('/api/live/ping', { method: 'POST' }).catch(() => {});
    }

    /**
     * Charge les produits déjà marqués comme actifs via une API REST.
     */
    async loadInitialProducts() {
        try {
            const res = await fetch(this.initialUrlValue);
            if (!res.ok) return;
            const products = await res.json();

            this.productCount = products.length;
            this.renderAll(products);
            this.updateGlobalUI();
        } catch (error) {
            // Silence en production, erreur métier gérée par l'absence d'affichage
        }
    }

    /**
     * S'abonne aux événements Mercure pour réagir aux activations/désactivations de produits.
     */
    subscribeToMercure() {
        if (!this.mercureUrlValue) return;

        this.eventSource = new EventSource(this.mercureUrlValue);

        this.eventSource.onmessage = (event) => {
            if (!event || !event.data) return;

            try {
                const data = JSON.parse(event.data);

                // Routage des événements Mercure
                if (data.event === "product_activated") {
                    this.addProduct(data);
                } else if (data.event === "product_deactivated") {
                    this.removeProduct(data.id);
                } else if (data.event === "stock_updated") {
                    this.updateStock(data.id, data.stock);
                } else if (data.event === "live_schedule_updated") {
                    this.updateSchedule(data);
                }
            } catch (error) {
                // Erreur de parsing JSON ignorée car structurelle
            }
        };

        this.eventSource.onerror = (error) => {
            // Géré par le mécanisme de reconnexion auto de EventSource
        };
    }

    /**
     * Rendu initial du catalogue complet.
     */
    renderAll(products) {
        if (!this.hasContainerTarget) return;

        if (products.length === 0) {
            this.containerTarget.innerHTML = "";
            this.noProductMessageTarget?.classList.remove("hidden");
            return;
        }

        this.noProductMessageTarget?.classList.add("hidden");

        this.containerTarget.innerHTML = products
            .map((p) => this.createProductCard(p))
            .join("");
    }

    /**
     * Ajoute fluidement un nouveau produit activé sans recharger la page.
     */
    addProduct(product) {
        if (!this.hasContainerTarget) return;

        // Évite les doublons
        if (document.getElementById(`product-card-${product.id}`)) return;

        this.productCount++;
        this.updateGlobalUI();

        this.noProductMessageTarget?.classList.add("hidden");

        // Masquer le message "en attente du premier produit"
        const waitingMsg = document.getElementById('waiting-first-product');
        if (waitingMsg) waitingMsg.classList.add('hidden');

        const div = document.createElement("div");
        div.innerHTML = this.createProductCard(product);
        const card = div.firstElementChild;

        // Animation d'apparition CSS
        card.style.opacity = "0";
        card.style.transform = "translateY(20px)";
        card.style.transition = "opacity 0.4s ease, transform 0.4s ease";

        this.containerTarget.prepend(card);

        // Déclenchement de l'animation au prochain cycle de rendu
        requestAnimationFrame(() => {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        });
    }

    /**
     * Retire un produit désactivé avec une animation de sortie.
     */
    removeProduct(id) {
        const card = document.getElementById(`product-card-${id}`);
        if (!card) return;

        this.productCount = Math.max(0, this.productCount - 1);
        this.updateGlobalUI();

        card.style.transition = "opacity 0.3s ease, transform 0.3s ease";
        card.style.opacity = "0";
        card.style.transform = "translateY(-10px)";

        setTimeout(() => {
            card.remove();
            if (this.productCount === 0) {
                this.noProductMessageTarget?.classList.remove("hidden");
            }
        }, 300);
    }

    /**
     * Met à jour les éléments globaux de l'interface (compteurs et badges).
     */
    updateGlobalUI() {
        if (this.hasCountTarget) {
            this.countTarget.textContent = this.productCount;
        }

        // Gestion de l'affichage du badge "Live" vs "Compte à rebours"
        if (this.productCount > 0) {
            this.badgeTarget?.classList.remove("hidden");
            this.countdownContainerTarget?.classList.add("hidden");
        } else {
            this.badgeTarget?.classList.add("hidden");
            this.countdownContainerTarget?.classList.remove("hidden");
        }
    }

    /**
     * Met à jour dynamiquement la programmation du live.
     */
    updateSchedule(data) {
        if (!this.hasCountdownContainerTarget) return;

        if (!data.nextLiveAt) {
            this.countdownContainerTarget.innerHTML = '<div class="text-sm md:text-base text-gray-500 italic">Pas de live prévu pour le moment.</div>';
            return;
        }

        const nextLiveIso = data.nextLiveAt;
        this.countdownContainerTarget.innerHTML = `
            <div id="next-live-countdown" data-next-live="${nextLiveIso}" class="flex items-center gap-3 text-base md:text-lg bg-blue-50 border border-noz-blue text-noz-blue px-4 py-3 rounded-xl shadow-sm" aria-live="polite">
                <span class="font-semibold">Prochain live&nbsp;: </span>
                <span class="font-mono next-live-countdown-value text-xl md:text-2xl">calcul en cours...</span>
            </div>
        `;
    }

    /**
     * Met à jour le stock d'un produit spécifique de manière chirurgicale.
     */
    updateStock(id, stock) {
        const card = document.getElementById(`product-card-${id}`);
        if (!card) return;

        const stockEl = card.querySelector("[data-stock-display]");
        if (stockEl) {
            stockEl.textContent = stock > 0 ? "En stock" : "Rupture de stock";
            stockEl.className = stock <= 0 ? "text-red-600 font-bold" : "text-gray-500";
        }
    }

    /**
     * Génère le HTML d'une carte produit.
     */
    createProductCard(p) {
        const price = new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(p.price);

        const originalPriceHtml = p.originalPrice
            ? `<span class="text-sm line-through text-gray-400 ml-2">${new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(p.originalPrice)}</span>`
            : "";

        const imagePath = p.image
            ? `${this.imagesPathValue}${p.image}`
            : `${this.imagesPathValue}placeholder.svg`;

        return `
            <div id="product-card-${p.id}" class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100 transition-all hover:shadow-xl group">
                <div class="relative h-48 sm:h-64 overflow-hidden">
                    <img src="${imagePath}" alt="${p.name}" style="height:220px;width:100%;object-fit:contain;padding:8px;" class="transition-transform duration-500 group-hover:scale-105">
                    ${/* Badge "EN LIVE" désactivé — décommenter pour le réactiver
                    '<div class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse flex items-center gap-1">' +
                    '<span class="w-2 h-2 bg-white rounded-full"></span> EN LIVE' +
                    '</div>'
                    */ ''}
                    ${p.stock <= 0 ? '<div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white font-bold text-xl uppercase tracking-widest">Épuisé</div>' : ""}
                </div>
                <div class="p-5">
                    <h3 class="text-lg font-bold text-gray-900 mb-1 group-hover:text-noz-blue transition-colors">${p.name}</h3>
                    <div class="flex items-baseline mb-4">
                        <span class="text-2xl font-black text-noz-blue">${price}</span>
                        ${originalPriceHtml}
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <span data-stock-display class="${p.stock <= 0 ? "text-red-600 font-bold" : "text-gray-500"}">
                                ${p.stock > 0 ? "En stock" : "Rupture de stock"}
                            </span>
                        </div>
                        <a href="/produit/${p.id}" class="bg-noz-yellow text-noz-black font-bold px-4 py-2 rounded-lg hover:bg-yellow-400 transition-all flex items-center gap-2 ${p.stock <= 0 ? "opacity-50 pointer-events-none grayscale" : ""}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Détail
                        </a>
                    </div>
                </div>
            </div>
        `;
    }
}
