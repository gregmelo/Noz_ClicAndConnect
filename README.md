# Noz Clic & Connect

Noz Clic & Connect est une plateforme de réservation en ligne pour les magasins NOZ. Elle permet aux clients de parcourir le catalogue de produits, d'ajouter des articles à leur panier et de valider des réservations à récupérer ultérieurement en magasin. La plateforme offre également une gestion administrative complète pour les employés et administrateurs.

## 📚 Sommaire

- [🚀 Fonctionnalités](#-fonctionnalités)
- [🛠️ Stack Technique](#️-stack-technique)
- [📦 Installation](#-installation)
- [🏗️ Architecture des Dossiers](#️-architecture-des-dossiers)
- [📄 Licence](#-licence)

## 🚀 Fonctionnalités

### 🛒 Client (ROLE_CLIENT)
- **Catalogue de produits** : Recherche par mots-clés, filtrage par catégorie, prix, disponibilité et promotions.
- **Panier** : Gestion dynamique des articles avant réservation.
- **Réservations** : Suivi des réservations en cours, historique et annulation possible.
- **Notifications e-mail & push** :
   - E-mails de confirmation et d'information lorsque la commande est prête.
   - Notifications **push PWA** (si l'utilisateur a activé les notifications mobiles) pour la confirmation de réservation et lorsque la commande passe au statut "prête".
- **Application installable (PWA)** :
   - Bandeau d'installation automatique sur la page d'accueil lorsque le navigateur le permet.
   - Conseils d'installation depuis le profil (Android / iOS) pour ajouter le site à l'écran d'accueil.
- **Expérience Live** :
   - Mise en avant des **arrivages du jour** avec badge EN LIVE.
   - Affichage d'un prochain live avec compte à rebours dynamique lorsqu'une date de live est planifiée.
- **Fiches produits enrichies** : Support de plusieurs images par produit avec galerie/carrousel sur la page de détail.

### 💼 Employé (ROLE_EMPLOYEE)
- **Tableau de Bord** : Statistiques en temps réel, alertes de stock bas et suivi du chiffre d'affaires.
- **Gestion des Produits** : Création, modification (avec optimisation automatique des images) et suppression (soft delete via audit logs).
- **Gestion des Réservations** : Préparation des listes de préparation groupées, validation des paniers et marquage des commandes comme prêtes ou récupérées.
- **Optimisation Logistique** : Calcul automatique des dates d'expiration selon l'heure de préparation.
- **Live Dashboard** : Pilotage des produits en live, activation/désactivation rapide et suivi du live en cours.
- **Notifications push équipe** : Réception de notifications web push en temps réel à chaque nouvelle réservation (pour les rôles employé / admin / super admin).

### 🛡️ Administrateur (ROLE_ADMIN / ROLE_SUPER_ADMIN)
- **Gestion des Utilisateurs** : Création de comptes employés/admins, gestion des sanctions (strikes) et bannissements automatiques.
- **Gestion des Catégories** : Organisation du catalogue.
- **Logs d'Activité** : Audit complet des actions réalisées sur la plateforme pour la traçabilité.

## 🛠️ Stack Technique

- **Framework** : Symfony 7+
- **Frontend** : Twig, Tailwind CSS, Stimulus, Flowbite, AssetMapper
- **Base de données** : MySQL / PostgreSQL (via Doctrine ORM)
- **Uploads** : Intervention Image (optimisation et redimensionnement)
- **Interactions JS** : Stimulus Controllers (Live, Confetti, Optimisation Image, Alertes temps réel, bandeau PWA, notifications mobiles)
- **PWA** : Service Worker, manifest et application installable (Add to Home Screen / Install app).
- **Notifications Web Push** : Minishlink/WebPush avec clés VAPID, côté client via l'API Push et côté serveur avec Symfony.
- **E-mails** : Symfony Mailer configuré via `MAILER_DSN` (voir `docs/EMAIL_CONFIGURATION.md`).

## 📦 Installation

1. **Clonage du projet**
   ```bash
   git clone [url-du-repo]
   cd Noz_ClicAndConnect
   ```

2. **Installation des dépendances**
   ```bash
   composer install
   npm install
   ```

3. **Configuration de l'environnement**
   Copiez le fichier `.env` en `.env.local` et configurez votre base de données :
   ```bash
   cp .env .env.local
   # Modifiez DATABASE_URL dans .env.local
   ```

4. **Base de données**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load # Pour des données de test
   ```

5. **Assets & PWA (front)**
   ```bash
   # Génération de la map d'assets (CSS/JS avec AssetMapper)
   php bin/console asset-map:compile
   ```

5. **Lancement du serveur**
   ```bash
   symfony serve
   # ou
   php -S localhost:8000 -t public
   ```

Pour le déploiement sur Alwaysdata (hébergement mutualisé), voir la documentation dédiée : `docs/DEPLOYMENT_ALWAYSDATA.md`.

## 🏗️ Architecture des Dossiers

- `src/Entity/` : Modèles de données (Product, User, Reservation, etc.).
- `src/Controller/` : Logique de routage et de traitement.
- `src/Service/` : Services métiers (Logger, CartService, EmailService).
- `assets/` : Ressources CSS et Stimulus controllers.
- `templates/` : Vues Twig organisées par fonctionnalité.

## 📄 Licence

Ce projet est la propriété de **Véricel Grégory**.

[![GitHub](https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white)](https://github.com/gregmelo)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/gregory-vericel/)
