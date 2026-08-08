# Site d'informations protégé — copropriété / conseil syndical

Système minimal en PHP pour publier une page d'informations protégée par un
mot de passe partagé (sans création de comptes), hébergeable sur un mutualisé
type o2switch.

## Fichiers

```
residence/
├── index.php      Page utilisateur : mot de passe partagé, affichage du contenu
├── admin.php      Administration : installation, éditeur TinyMCE, paramètres
├── common.php     Fonctions partagées (non accessible via le web)
├── content.html   Contenu publié (non accessible directement — servi via index.php)
├── config.json    Créé à l'installation : hash des mots de passe, options
├── .htaccess      Protège les fichiers de données, force HTTPS
└── robots.txt     Interdit l'indexation par les moteurs de recherche
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

## Déploiement (o2switch / cPanel)

1. **Téléverser** le dossier `residence/` dans `public_html/`.
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
5. Vérifier que `https://votresite/residence/content.html` et
   `https://votresite/residence/config.json` renvoient bien une erreur
   « 403 Forbidden ».

## Option : données hors du dossier web

Par défaut, les données (`config.json`, `content.html`, `throttle.json`)
sont stockées dans le dossier `residence/`, protégées par `.htaccess`.
Pour une protection indépendante du `.htaccess`, éditez `common.php` :

```php
define('DATA_DIR', '/home/VOTRELOGIN/residence_data');
```

Créez ce dossier au préalable (hors de `public_html/`), accessible en
écriture par PHP.

## Limites connues

- L'éditeur TinyMCE est chargé depuis un CDN (version épinglée). L'accès à
  Internet est nécessaire pour la page d'administration, et il n'y a pas de
  hachage d'intégrité (SRI) car TinyMCE charge dynamiquement d'autres
  ressources depuis ce même CDN.
- Le mot de passe utilisateur est partagé entre tous les résidents : il ne
  protège que des curieux, pas d'un résident qui le diffuse. Ne pas y
  publier de données sensibles (le système est prévu pour des
  comptes-rendus, annonces, contacts utiles…).
- Les images sont intégrées en Base64 dans le contenu : éviter les images
  volumineuses (limite de 2 Mo par image côté éditeur).
