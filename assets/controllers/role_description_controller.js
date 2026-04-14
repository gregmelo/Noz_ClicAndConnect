import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["select", "description"];

    connect() {
        this.update();
    }

    update() {
        if (!this.hasSelectTarget || !this.hasDescriptionTarget) {
            return;
        }

        const role = this.selectTarget.value;

        const descriptions = {
            'ROLE_CLIENT': "Accès standard. Peut parcourir les produits, gérer son panier et ses réservations.",
            'ROLE_EMPLOYEE': "Accès magasin. Peut gérer les stocks, préparer les commandes (picking) et valider les retraits clients.",
            'ROLE_ADMIN': "Accès gestion. Peut gérer les utilisateurs (Client/Employé), les catégories et consulter les logs d'audit.",
            'ROLE_SUPER_ADMIN': "Accès total (sauf technique). Peut gérer tous les niveaux d'utilisateurs et les paramètres critiques.",
            'ROLE_DEVELOPER': "Accès technique complet. Accès à tous les outils de maintenance, y compris la base de données."
        };

        const description = descriptions[role] || "Aucune description disponible pour ce rôle.";
        this.descriptionTarget.innerHTML = `<span class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            ${description}
        </span>`;
    }
}
