# Noz Clic & Connect

Noz Clic & Connect est une plateforme de réservation en ligne pour les magasins NOZ. Elle permet aux clients de parcourir le catalogue de produits, d'ajouter des articles à leur panier et de valider des réservations à récupérer ultérieurement en magasin. La plateforme offre également une gestion administrative complète pour les employés et administrateurs.

## � Sommaire

- [🚀 Fonctionnalités](#-fonctionnalités)
- [🛠️ Stack Technique](#️-stack-technique)
- [📦 Installation](#-installation)
- [🏗️ Architecture des Dossiers](#️-architecture-des-dossiers)
- [📄 Licence](#-licence)

## �🚀 Fonctionnalités

### 🛒 Client (ROLE_CLIENT)
- **Catalogue de produits** : Recherche par mots-clés, filtrage par catégorie, prix, disponibilité et promotions.
- **Panier** : Gestion dynamique des articles avant réservation.
- **Réservations** : Suivi des réservations en cours, historique et annulation possible.
- **Notifications** : Réception d'e-mails de confirmation et d'e-mails lorsque la commande est prête.

### 💼 Employé (ROLE_EMPLOYEE)
- **Tableau de Bord** : Statistiques en temps réel, alertes de stock bas et suivi du chiffre d'affaires.
- **Gestion des Produits** : Création, modification (avec optimisation automatique des images) et suppression (soft delete via audit logs).
- **Gestion des Réservations** : Préparation des listes de préparation groupées, validation des paniers et marquage des commandes comme prêtes ou récupérées.
- **Optimisation Logistique** : Calcul automatique des dates d'expiration selon l'heure de préparation.

### 🛡️ Administrateur (ROLE_ADMIN / ROLE_SUPER_ADMIN)
- **Gestion des Utilisateurs** : Création de comptes employés/admins, gestion des sanctions (strikes) et bannissements automatiques.
- **Gestion des Catégories** : Organisation du catalogue.
- **Logs d'Activité** : Audit complet des actions réalisées sur la plateforme pour la traçabilité.

## 🛠️ Stack Technique

- **Framework** : Symfony 7+
- **Frontend** : Twig, Tailwind CSS, Stimulus, Flowbite
- **Base de données** : MySQL / PostgreSQL (via Doctrine ORM)
- **Uploads** : Intervention Image (optimisation et redimensionnement)
- **Interactions JS** : Stimulus Controllers (Confetti, Optimisation Image, Alertes temps réel)

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

5. **Lancement du serveur**
   ```bash
   symfony serve
   # ou
   php -S localhost:8000 -t public
   ```

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
