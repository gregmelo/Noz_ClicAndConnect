# Guide de Déploiement sur Alwaysdata

Ce guide détaille les étapes pour déployer votre application Symfony **Noz ClicAndConnect** sur un hébergement mutualisé Alwaysdata.

## 1. Prérequis
- Un compte [Alwaysdata](https://www.alwaysdata.com/).
- Accès SSH activé sur votre compte.
- Git installé sur votre machine locale.

## 2. Préparation Locale
Avant de déployer, assurez-vous que votre application est prête :
1.  **Base de données** : Vérifiez que vos migrations sont à jour.
    ```bash
    php bin/console make:migration
    ```
2.  **Assets** : Assurez-vous que le fichier `importmap.php` est correct.
3.  **Git** : Tout doit être commité.
    ```bash
    git add .
    git commit -m "Prêt pour le déploiement"
    ```

## 3. Configuration sur Alwaysdata (Interface Administration)

### 3.1. Créer la Base de Données
1.  Allez dans **Bases de données** > **MySQL**.
2.  Ajoutez une nouvelle base de données (ex: `noz_db`).
3.  Ajoutez un utilisateur MySQL (ex: `noz_user`) avec un mot de passe fort. Donnez-lui tous les droits sur la base créée.
4.  **Notez bien** :
    - Hôte : `mysql-votrecompte.alwaysdata.net`
    - Nom de la base : `votrecompte_noz_db`
    - Utilisateur : `votrecompte_noz_user`
    - Mot de passe : `celui que vous avez choisi`

### 3.2. Configurer le Site Web
1.  Allez dans **Web** > **Sites**.
2.  Modifiez le site par défaut ou créez-en un nouveau.
3.  **Adresses** : `votre-domaine.alwaysdata.net` (ou votre domaine perso).
4.  **Type** : `PHP`.
5.  **Version PHP** : `8.2` (ou plus récent).
6.  **Racine du document (Document Root)** : `/home/votrecompte/www/Noz_ClicAndConnect/public`
    - *Attention : Le dossier `public` est important !*

### 3.3. Variables d'Environnement
Dans la configuration du site (onglet **Variables d'environnement**), ajoutez :
- `APP_ENV` = `prod`
- `APP_SECRET` = `une_chaine_aleatoire_tres_longue_et_secrete`
- `DATABASE_URL` = `mysql://user:password@host:3306/database_name?serverVersion=10.6.17-MariaDB&charset=utf8mb4`
    - Remplacez `user`, `password`, `host` et `database_name` par vos infos (voir 3.1).
- `MAILER_DSN` = `smtp://ACCOUNT_NAME:PASSWORD@smtp.alwaysdata.com:587`
    - **Important** : L'inscription envoie un email de confirmation. Vous devez configurer cela.
    - Si vous utilisez les emails Alwaysdata :
        - `ACCOUNT_NAME` : Votre nom de compte Alwaysdata (ou une adresse email créée dans l'interface).
        - `PASSWORD` : Le mot de passe associé.
    - Si vous utilisez Gmail : `gmail://USERNAME:PASSWORD@default` (nécessite un mot de passe d'application).

## 4. Transfert des Fichiers (via SSH/Git)

C'est la méthode la plus propre.

1.  Connectez-vous en SSH à votre compte Alwaysdata (infos dans **Accès distant** > **SSH**).
    ```bash
    ssh votrecompte@ssh-votrecompte.alwaysdata.net
    ```
2.  Allez dans le dossier `www`.
    ```bash
    cd www
    ```
3.  Clonez votre dépôt (si vous utilisez GitHub/GitLab) OU transférez les fichiers via FTP/SFTP dans un dossier `Noz_ClicAndConnect`.
    - *Si vous n'avez pas de dépôt distant*, utilisez FileZilla (SFTP) pour envoyer tout le contenu de votre projet local dans `/home/votrecompte/www/Noz_ClicAndConnect`. **N'envoyez pas** le dossier `vendor` ni `var`.

## 5. Installation des Dépendances

Toujours en SSH, dans le dossier du projet :

```bash
cd ~/www/Noz_ClicAndConnect
```

1.  **Installer les paquets PHP** :
    ```bash
    composer install --no-dev --optimize-autoloader
    ```
2.  **Exécuter les migrations** (crée les tables en prod) :
    ```bash
    php bin/console doctrine:migrations:migrate
    ```
3.  **Compiler les assets** (Tailwind & Stimulus) :
    ```bash
    php bin/console asset-map:compile
    ```
4.  **Vider le cache** :
    ```bash
    php bin/console cache:clear
    ```

## 6. Vérification
Ouvrez votre navigateur et allez sur l'URL de votre site.
- Si vous avez une erreur 500, vérifiez les logs dans `var/log/prod.log` ou via l'interface Alwaysdata (**Logs** > **HTTP**).

## 7. Mise à jour future
Pour mettre à jour le site après des modifications locales :
1.  `git pull` (ou transfert FTP des fichiers modifiés).
2.  `composer install --no-dev` (si nouvelles dépendances).
3.  `php bin/console doctrine:migrations:migrate` (si changement BDD).
4.  `php bin/console asset-map:compile` (si changement CSS/JS).
5.  `php bin/console cache:clear`.
