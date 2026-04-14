# 📦 Noz Clic & Connect

![Version](https://img.shields.io/badge/version-2.1.0-blue)
![Symfony](https://img.shields.io/badge/Symfony-7.0%2B-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)
![PWA](https://img.shields.io/badge/PWA-Compatible-orange?logo=pwa)

**Noz Clic & Connect** est une plateforme moderne de réservation en ligne dédiée aux magasins **NOZ**. Spécialisée dans la gestion des **ventes en live**, elle permet une synchronisation parfaite entre les arrivages présentés en direct et les réservations clients.

---

## 🌟 Points Forts & Innovations

### ⚡ Temps Réel & Performance
- **Synchronisation Mercure** : Mise à jour instantanée des stocks, des prix et des compteurs sans rechargement de page.
- **Auto-Refresh Intelligent** : Les listes de réservations (client et employé) se rafraîchissent automatiquement via **Stimulus** et **Turbo** lors de chaque changement de statut.

### 🔔 Notifications Hybrides
- **Web Push (PWA)** : Système de notifications natif fonctionnant même lorsque le navigateur est fermé (compatible Chrome, Edge, Safari iOS/macOS).
- **Correctifs Windows** : Support robuste pour les environnements de développement Windows/XAMPP avec patch **OpenSSL 3.0** intégré.
- **E-mails Transactionnels** : Confirmations et alertes envoyées via Symfony Mailer.

### 🛡️ Logistique & Discipline
- **Gestion des "Strikes"** : Système de pénalités automatique pour les clients ne récupérant pas leurs commandes.
- **Réhabilitation** : Retrait automatique de pénalités après 3 collectes réussies consécutives.
- **Dashboards Segmentés** : Séparation claire entre les nouvelles demandes, les commandes prêtes, les annulations clients et les expirations.

---

## 🛠️ Stack Technique

- **Backend** : Symfony 7, Doctrine ORM, Symfony Messenger (file d'attente asynchrone).
- **Frontend** : Twig, Tailwind CSS, Stimulus, flowbite, Turbo.
- **Temps Réel** : Mercure Hub (Architecture événementielle).
- **Images** : Intervention Image (Optimisation auto vers WebP/AVIF).
- **Sécurité** : VAPID (Electronic Frontier Foundation standards), Rate Limiting.

---

## 📦 Installation & Configuration

### 1. Prérequis
- PHP 8.2+ (avec extensions OpenSSL, GD, Intl)
- MySQL 8.0+ ou PostgreSQL
- Mercure Hub (Caddy ou binaire autonome)

### 2. Initialisation
```bash
git clone https://github.com/votre-depot/Noz_ClicAndConnect.git
cd Noz_ClicAndConnect
composer install
cp .env .env.local  # Configurer DATABASE_URL et MERCURE_URL
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 3. VAPID (Notifications)
Générez vos clés pour le fichier `.env.local` :
```bash
# Via l'interface admin du site ou via openssl
MERCURE_JWT_SECRET="votre_secret"
MERCURE_URL="http://127.0.0.1:3000/.well-known/mercure"
VAPID_PUBLIC_KEY="votre_clé_publique_base64"
VAPID_PRIVATE_KEY="votre_clé_privée_base64"
```

### 4. Lancement
```bash
# Terminal 1 : Serveur Symfony
symfony serve
# Terminal 2 : Mercure Hub
.\mercure.exe run --config Caddyfile
# Terminal 3 : Worker de notifications (Async)
php bin/console messenger:consume async
```

---

## 📖 Architecture & Maintenance

- **`src/Controller/ReservationController.php`** : Cœur de la logique métier (Tri logistique, Stocks, Mercure).
- **`src/Service/PushNotificationService.php`** : Moteur d'envoi des notifications Push.
- **`assets/controllers/`** : Comportement dynamique (PWA, Temps réel, Notifications).
- **`public/sw.js`** : Service Worker gérant l'installation PWA et l'affichage des notifications en arrière-plan.

---

## 📝 Licence & Auteur

Propriété exclusive de **Véricel Grégory**. Toute reproduction ou utilisation sans autorisation est interdite.

[![LinkedIn](https://img.shields.io/badge/Contact-LinkedIn-0077B5?style=flat-square&logo=linkedin)](https://www.linkedin.com/in/gregory-vericel/)
