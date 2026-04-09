import { Controller } from "@hotwired/stimulus";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["container", "noProductMessage"];
    static values = {
        mercureUrl: String, // URL publique du hub Mercure
        initialUrl: String, // Endpoint pour charger les produits déjà en live
    };

    connect() {
        console.log("Live Display Controller Connected (Mercure)");
        // 1. Charger les produits déjà en live au chargement de la page
        this.loadInitialProducts();
        // 2. S'abonner à Mercure pour les mises à jour suivantes
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
            this.renderAll(products);
        } catch (error) {
            console.error("Erreur chargement initial:", error);
        }
    }

    // Ouvre une connexion SSE vers le hub Mercure (géré par Mercure, pas par PHP)
    subscribeToMercure() {
        // L'URL complète avec le topic est déjà dans la value (générée par Twig)
        this.eventSource = new EventSource(this.mercureUrlValue);

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.event === "product_activated") {
                    this.addProduct(data);
                } else if (data.event === "product_deactivated") {
                    this.removeProduct(data.id);
                } else if (data.event === "stock_updated") {
                    this.updateStock(data.id, data.stock);
                }
            } catch (error) {
                console.error("Erreur parsing Mercure:", error);
            }
        };

        this.eventSource.onerror = () => {
            console.warn("Mercure: reconnexion en cours...");
        };
    }

    // Affiche tous les produits (chargement initial)
    renderAll(products) {
        if (!this.hasContainerTarget) return;

        const countdownEl = document.getElementById("next-live-countdown");

        if (products.length === 0) {
            this.containerTarget.innerHTML = "";
            this.noProductMessageTarget?.classList.remove("hidden");
            countdownEl?.classList.remove("hidden");
            return;
        }

        this.noProductMessageTarget?.classList.add("hidden");
        countdownEl?.classList.add("hidden");

        this.containerTarget.innerHTML = products
            .map((p) => this.createProductCard(p))
            .join("");
    }

    // Ajoute un nouveau produit activé en temps réel (avec animation)
    addProduct(product) {
        if (!this.hasContainerTarget) return;

        // Masquer le message "pas de produits"
        this.noProductMessageTarget?.classList.add("hidden");
        document.getElementById("next-live-countdown")?.classList.add("hidden");

        // Ne pas ajouter si la carte existe déjà
        if (document.getElementById(`product-card-${product.id}`)) return;

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

        // Animation de disparition
        card.style.transition = "opacity 0.3s ease, transform 0.3s ease";
        card.style.opacity = "0";
        card.style.transform = "translateY(-10px)";

        setTimeout(() => {
            card.remove();
            // Si plus aucun produit, afficher le message
            if (this.containerTarget.children.length === 0) {
                this.noProductMessageTarget?.classList.remove("hidden");
            }
        }, 300);
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
