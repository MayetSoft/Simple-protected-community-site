# Simple Protected Community Site

[English](README.md) · **Français**

Système minimal en PHP pour publier une page d'informations protégée par un
mot de passe partagé, sans création de comptes ni base de données.
Hébergeable sur n'importe quel mutualisé Apache + PHP (o2switch, cPanel…).

Cas d'usage typiques : conseil syndical de copropriété, association,
club, amicale… Toute communauté qui veut partager des comptes-rendus,
annonces ou contacts utiles sans les rendre publics.

## Fonctionnalités

- Une page de contenu, protégée par un **code d'accès partagé**.
- Une page d'administration avec éditeur riche (TinyMCE), images intégrées.
- Mode maintenance (page indisponible) et mode lecture seule.
- Aucune base de données : tout est stocké dans des fichiers.

## Prérequis

- PHP ≥ 7.4 (extension `mbstring` recommandée).
- Apache ≥ 2.4 avec `mod_rewrite` et `.htaccess` autorisé.
- Un certificat HTTPS (Let's Encrypt suffit).

## Fichiers

```
site/
├── index.php      Page utilisateur : code d'accès partagé, affichage du contenu
├── admin.php      Administration : installation, éditeur TinyMCE, paramètres
├── common.php     Fonctions partagées et réglages (non accessible via le web)
├── .htaccess      Protège les fichiers de données, force HTTPS
└── robots.txt     Interdit l'indexation par les moteurs de recherche
```

Fichiers créés à l'exécution (exclus du dépôt via `.gitignore`) :

```
├── config.json    Hash des mots de passe, options
├── content.html   Contenu publié (servi uniquement via index.php)
└── throttle.json  Compteur de tentatives de connexion
```

## Sécurité intégrée

- Aucun mot de passe par défaut : l'installation (premier accès à `admin.php`)
  impose de choisir les deux mots de passe (admin ≥ 10 caractères,
  utilisateur ≥ 8 caractères, avec confirmation).
- Mots de passe stockés hachés (bcrypt via `password_hash`).
- `content.html` et `config.json` sont **inaccessibles directement** :
  le contenu n'est servi qu'après saisie du mot de passe, via `index.php`.
- Jetons anti-CSRF sur tous les formulaires.
- Limitation des tentatives de connexion : 5 échecs par IP → blocage 15 minutes.
- Cookies de session `HttpOnly`, `SameSite=Lax`, `Secure` (si HTTPS).
- En-têtes `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.

## Déploiement (exemple : o2switch / cPanel)

1. **Téléverser** le dossier `site/` dans `public_html/`. Vous pouvez le
   renommer librement (par exemple `infos/`) : ce nom apparaîtra dans
   l'URL.
2. **Renommer `admin.php`** en un nom difficile à deviner, par exemple
   `gestion-3fA9kP.php`. Le code suit automatiquement le nouveau nom,
   aucune modification n'est nécessaire.
3. **Activer HTTPS** (Let's Encrypt) depuis cPanel. Le `.htaccess` redirige
   ensuite tout le trafic vers HTTPS.
4. **Ouvrir immédiatement** la page d'administration pour faire
   l'installation (choix des mots de passe). ⚠️ Tant que l'installation
   n'est pas faite, le premier visiteur qui trouve la page d'administration
   peut choisir les mots de passe : ne pas laisser traîner un site non
   initialisé.
5. Vérifier que `https://votresite/site/content.html` et
   `https://votresite/site/config.json` renvoient bien une erreur
   « 403 Forbidden ».

## Personnalisation

Les réglages se trouvent en tête de `common.php` :

```php
define('SITE_TITLE', "Espace d'informations"); // titre affiché
define('ADMIN_PWD_MIN', 10);                   // longueur minimale admin
define('USER_PWD_MIN', 8);                     // longueur minimale utilisateur
```

### Option : données hors du dossier web

Par défaut, les données (`config.json`, `content.html`, `throttle.json`)
sont stockées dans le dossier de l'application, protégées par `.htaccess`.
Pour une protection indépendante du `.htaccess`, éditez `common.php` :

```php
define('DATA_DIR', '/home/VOTRELOGIN/site_data');
```

Créez ce dossier au préalable (hors de `public_html/`), accessible en
écriture par PHP.

## Limites connues

- L'éditeur TinyMCE est chargé depuis un CDN (version épinglée). L'accès à
  Internet est nécessaire pour la page d'administration, et il n'y a pas de
  hachage d'intégrité (SRI) car TinyMCE charge dynamiquement d'autres
  ressources depuis ce même CDN.
- Le mot de passe utilisateur est partagé entre tous les membres : il ne
  protège que des curieux, pas d'un membre qui le diffuse. Ne pas y
  publier de données sensibles (le système est prévu pour des
  comptes-rendus, annonces, contacts utiles…).
- Les images sont intégrées en Base64 dans le contenu : éviter les images
  volumineuses (limite de 2 Mo par image côté éditeur).
- L'interface est en français uniquement.

## Sécurité

Pour signaler une vulnérabilité, voir [SECURITY.md](SECURITY.md).

## Licence

[MIT](LICENSE)
