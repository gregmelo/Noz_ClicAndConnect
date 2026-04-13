import { Controller } from "@hotwired/stimulus";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["container", "noProductMessage", "count", "badge", "countdownContainer"];
    static values = {
        mercureUrl: String, // URL publique du hub Mercure
        initialUrl: String, // Endpoint pour charger les produits déjà en live
    };

    connect() {
        console.log("Live Display Controller Connected (Mercure)");
        console.log("DEBUG: Container target found?", this.hasContainerTarget);
        console.log("DEBUG: Count target found?", this.hasCountTarget);
        console.log("DEBUG: Badge target found?", this.hasBadgeTarget);
        console.log("DEBUG: CountdownContainer target found?", this.hasCountdownContainerTarget);
        
        this.productCount = 0;
        this.loadInitialProducts();
        this.subscribeToMercure();
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    // Charge les produits déjà actifs via un simple fetch (1 requête, pas de boucle)
    async loadInitialProducts() {
        try {
            const res = await fetch(this.initialUrlValue);
            if (!res.ok) return;
            const products = await res.json();
            this.productCount = products.length;
            console.log("DEBUG: Initial product count:", this.productCount);
            this.renderAll(products);
            this.updateGlobalUI();
        } catch (error) {
            console.error("Erreur chargement initial:", error);
        }
    }

    // Ouvre une connexion SSE vers le hub Mercure (géré par Mercure, pas par PHP)
subscribeToMercure() {
    this.eventSource = new EventSource(this.mercureUrlValue);

    this.eventSource.onmessage = (event) => {
        if (!event || !event.data) return;
        
        console.log("Mercure message reçu:", event.data);
        try {
            const data = JSON.parse(event.data);
            console.log("Data parsée:", data);
            if (data.event === "product_activated") {
                console.log("DEBUG: Activating product", data.id);
                this.addProduct(data);
            } else if (data.event === "product_deactivated") {
                console.log("DEBUG: Deactivating product", data.id);
                this.removeProduct(data.id);
            } else if (data.event === "stock_updated") {
                this.updateStock(data.id, data.stock);
            } else if (data.event === "live_schedule_updated") {
                this.updateSchedule(data);
            }
        } catch (error) {
            console.error("Erreur parsing Mercure:", error);
        }
    };

    this.eventSource.onerror = (error) => {
        console.warn("Mercure: reconnexion en cours...", error);
    };
}

    // Affiche tous les produits (chargement initial)
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

    // Ajoute un nouveau produit activé en temps réel (avec animation)
    addProduct(product) {
        if (!this.hasContainerTarget) return;

        // Ne pas ajouter si la carte existe déjà
        if (document.getElementById(`product-card-${product.id}`)) return;

        this.productCount++;
        console.log("DEBUG: Incremented count to:", this.productCount);
        this.updateGlobalUI();

        // Masquer le message "pas de produits"
        this.noProductMessageTarget?.classList.add("hidden");

        const div = document.createElement("div");
        div.innerHTML = this.createProductCard(product);
        const card = div.firstElementChild;

        // Animation d'apparition
        card.style.opacity = "0";
        card.style.transform = "translateY(20px)";
        card.style.transition = "opacity 0.4s ease, transform 0.4s ease";

        this.containerTarget.prepend(card);

        requestAnimationFrame(() => {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        });
    }

    // Supprime un produit désactivé en temps réel
    removeProduct(id) {
        const card = document.getElementById(`product-card-${id}`);
        if (!card) return;

        this.productCount = Math.max(0, this.productCount - 1);
        console.log("DEBUG: Decremented count to:", this.productCount);
        this.updateGlobalUI();

        // Animation de disparition
        card.style.transition = "opacity 0.3s ease, transform 0.3s ease";
        card.style.opacity = "0";
        card.style.transform = "translateY(-10px)";

        setTimeout(() => {
            card.remove();
            // Si plus aucun produit, afficher le message
            if (this.productCount === 0) {
                this.noProductMessageTarget?.classList.remove("hidden");
            }
        }, 300);
    }

    // Met à jour les éléments de l'en-tête (compteur, badge live, etc.)
    updateGlobalUI() {
        console.log("DEBUG: updateGlobalUI called with count:", this.productCount);
        
        // Mettre à jour le compteur
        if (this.hasCountTarget) {
            console.log("DEBUG: Updating count target element");
            this.countTarget.textContent = this.productCount;
        } else {
            console.log("DEBUG: Count target NOT FOUND in DOM");
        }

        // Gérer le badge "Live en cours" vs "Compte à rebours"
        if (this.productCount > 0) {
            this.badgeTarget?.classList.remove("hidden");
            this.countdownContainerTarget?.classList.add("hidden");
        } else {
            this.badgeTarget?.classList.add("hidden");
            this.countdownContainerTarget?.classList.remove("hidden");
        }
    }

    // Met à jour la programmation du live sans recharger la page
    updateSchedule(data) {
        if (!this.hasCountdownContainerTarget) return;

        if (!data.nextLiveAt) {
            this.countdownContainerTarget.innerHTML = '<div class="text-sm md:text-base text-gray-500 italic">Pas de live prévu pour le moment.</div>';
            return;
        }

        // Créer le nouveau compte à rebours
        const nextLiveIso = data.nextLiveAt;
        this.countdownContainerTarget.innerHTML = `
            <div id="next-live-countdown" data-next-live="${nextLiveIso}" class="flex items-center gap-3 text-base md:text-lg bg-blue-50 border border-noz-blue text-noz-blue px-4 py-3 rounded-xl shadow-sm" aria-live="polite">
                <span class="font-semibold">Prochain live&nbsp;: </span>
                <span class="font-mono next-live-countdown-value text-xl md:text-2xl">calcul en cours...</span>
            </div>
        `;

        // Note: Le script global dans index.html.twig gère l'intervalle 
        // mais il ne trouvera peut-être pas le nouvel élément. 
        // Idéalement, on redéclencherait le script ou on gère le countdown ici.
        // Pour faire simple, on va juste forcer un rafraîchissement si la date change radicalement
        // ou laisser l'utilisateur actualiser pour le countdown PRÉCIS, mais au moins la bannière change.
    }

    // Met à jour le stock d'une carte existante sans tout re-rendre
    updateStock(id, stock) {
        const card = document.getElementById(`product-card-${id}`);
        if (!card) return;

        const stockEl = card.querySelector("[data-stock-display]");
        if (stockEl) {
            stockEl.textContent = stock > 0 ? `Stock : ${stock}` : "Rupture de stock";
            stockEl.className =
                stock <= 5 ? "text-red-600 font-bold" : "text-gray-500";
        }
    }

    createProductCard(p) {
        const price = new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(p.price);
        const originalPriceHtml = p.originalPrice
            ? `<span class="text-sm line-through text-gray-400 ml-2">${new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(p.originalPrice)}</span>`
            : "";
        const imagePath = p.image
            ? `/uploads/images/${p.image}`
            : "/uploads/images/no-image.png";

        return `
            <div id="product-card-${p.id}" class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100 transition-all hover:shadow-xl group">
                <div class="relative h-48 sm:h-64 overflow-hidden">
                    <img src="${imagePath}" alt="${p.name}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                    <div class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse flex items-center gap-1">
                        <span class="w-2 h-2 bg-white rounded-full"></span> EN LIVE
                    </div>
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
                            <span data-stock-display class="${p.stock <= 5 ? "text-red-600 font-bold" : "text-gray-500"}">
                                ${p.stock > 0 ? `Stock : ${p.stock}` : "Rupture de stock"}
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
