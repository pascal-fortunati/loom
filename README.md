# Loom

> Réseau social dédié au partage et à la découverte de passions.

Loom est une application web développée dans le cadre de la formation **Développeur Web et Web Mobile (DWWM)**.

Le projet permet à chaque utilisateur de créer des espaces consacrés à ses passions, d'y publier du contenu et de découvrir les centres d'intérêt d'autres membres.

L'objectif de Loom est de proposer une expérience proche d'un réseau social classique tout en plaçant les **passions et les communautés** au centre de l'application.

---

## Sommaire

* [Présentation](#présentation)
* [Fonctionnalités](#fonctionnalités)
* [Technologies utilisées](#technologies-utilisées)
* [Architecture](#architecture)
* [Prérequis](#prérequis)
* [Installation avec Docker](#installation-avec-docker)
* [Accès à l'application](#accès-à-lapplication)
* [Configuration](#configuration)
* [Base de données](#base-de-données)
* [Développement](#développement)
* [Commandes Docker](#commandes-docker)
* [Déploiement](#déploiement)
* [Sécurité](#sécurité)
* [Contexte du projet](#contexte-du-projet)

---

# Présentation

Loom est un réseau social permettant aux utilisateurs de créer et de suivre des pages consacrées à leurs centres d'intérêt.

Une passion peut par exemple concerner :

* un loisir ;
* un univers culturel ;
* une activité artistique ;
* un jeu ;
* une série ;
* un sport ;
* une technologie ;
* ou tout autre centre d'intérêt.

Les membres peuvent publier du contenu sur leurs pages, découvrir celles d'autres utilisateurs et interagir avec leurs publications.

L'application repose sur une architecture séparant :

* un **frontend Vue.js** ;
* une **API REST PHP** ;
* une **base de données MySQL**.

L'ensemble peut être exécuté à l'aide de **Docker Compose**, afin d'obtenir un environnement reproductible et facilement déployable.

---

# Fonctionnalités

## Authentification

* création d'un compte ;
* connexion ;
* authentification avec JSON Web Token (JWT) ;
* gestion de la session utilisateur.

## Profil utilisateur

* consultation du profil ;
* modification des informations du profil ;
* gestion de la visibilité du profil ;
* affichage des passions associées à l'utilisateur.

## Pages de passions

* création d'une page de passion ;
* consultation des pages ;
* exploration des passions existantes ;
* abonnement à une page.

## Publications

* création de publications ;
* ajout d'images ;
* consultation des publications ;
* affichage d'un fil personnalisé ;
* affichage des publications de ses propres passions ;
* exploration des publications publiques.

## Interactions sociales

* likes ;
* commentaires ;
* abonnements aux pages ;
* notifications.

## Messagerie

* messagerie privée entre utilisateurs.

## Recherche

* recherche de contenu et de passions.

## Statistiques

* affichage de statistiques liées à l'utilisation de la plateforme.

## Interface

* application responsive ;
* thème clair ;
* thème sombre ;
* navigation avec Vue Router.

---

# Technologies utilisées

## Frontend

* **Vue 3**
* **TypeScript**
* **Vue Router**
* **Vite**
* **Tailwind CSS**
* **DaisyUI**
* **Materialize CSS**

## Backend

* **PHP 8.2**
* API REST développée sans framework PHP externe
* **PDO**
* authentification **JWT**
* Apache
* réécriture des URLs avec `.htaccess`

## Base de données

* **MySQL 8.4**
* scripts SQL versionnés
* système de migrations

## Infrastructure

* **Docker**
* **Docker Compose**
* **Nginx**

Nginx est utilisé pour servir le build de l'application Vue et transmettre les requêtes destinées au backend.

---

# Architecture

L'application utilise une architecture frontend/backend séparée.

```text
                     ┌─────────────────────┐
                     │     Navigateur      │
                     └──────────┬──────────┘
                                │
                                ▼
                     ┌─────────────────────┐
                     │        Nginx        │
                     │     Frontend Vue    │
                     │        :8080        │
                     └──────────┬──────────┘
                                │
                       /backend │
                                ▼
                     ┌─────────────────────┐
                     │      API PHP        │
                     │       Apache        │
                     │        :8081        │
                     └──────────┬──────────┘
                                │
                                ▼
                     ┌─────────────────────┐
                     │     MySQL 8.4       │
                     │      database       │
                     └─────────────────────┘
```

## Organisation du dépôt

```text
.
├── backend/
│   ├── controllers/
│   ├── models/
│   ├── routes/
│   └── ...
│
├── database/
│   ├── migrations/
│   └── ...
│
├── frontend/
│   ├── src/
│   ├── public/
│   ├── package.json
│   └── vite.config.*
│
├── docker-compose.yml
├── .env.example
└── README.md
```

### `backend/`

Contient l'API REST PHP ainsi que la logique serveur de l'application :

* contrôleurs ;
* modèles ;
* routes ;
* authentification ;
* accès à la base de données ;
* gestion des fichiers envoyés.

### `frontend/`

Contient l'application Vue 3 et son interface utilisateur.

Le projet est développé avec TypeScript puis compilé avec Vite.

### `database/`

Contient le schéma SQL et les migrations utilisées par l'application.

### `docker-compose.yml`

Décrit les différents services nécessaires au fonctionnement de Loom.

---

# Prérequis

Pour utiliser la méthode recommandée, seuls les outils suivants sont nécessaires :

* Docker Engine ou Docker Desktop ;
* Docker Compose v2 ;
* Git.

Il n'est pas nécessaire d'installer manuellement :

* PHP ;
* Apache ;
* Node.js ;
* Nginx ;
* MySQL.

Ces composants sont directement fournis par les conteneurs Docker.

---

# Installation avec Docker

## 1. Cloner le dépôt

```bash
git clone <URL_DU_DEPOT>
cd <DOSSIER_DU_PROJET>
```

## 2. Créer le fichier d'environnement

Sous Linux ou macOS :

```bash
cp .env.example .env
```

Sous PowerShell :

```powershell
Copy-Item .env.example .env
```

Modifiez ensuite le fichier `.env`.

Il est notamment recommandé de changer :

```env
DB_PASS=
DB_ROOT_PASS=
JWT_SECRET=
```

Utilisez des valeurs suffisamment longues et aléatoires.

## 3. Construire et démarrer l'application

```bash
docker compose up -d --build
```

## 4. Vérifier les conteneurs

```bash
docker compose ps
```

Les différents services de Loom doivent apparaître comme démarrés.

---

# Accès à l'application

Une fois les conteneurs démarrés :

| Service          | Adresse                                       |
| ---------------- | --------------------------------------------- |
| Application Loom | http://localhost:8080                         |
| API PHP          | http://localhost:8081                         |
| API via Nginx    | http://localhost:8080/backend/                |
| MySQL            | accessible uniquement depuis le réseau Docker |

La base MySQL n'est volontairement pas publiée directement sur la machine hôte dans la configuration par défaut.

Dans le réseau Docker, elle est accessible sous le nom :

```text
database
```

---

# Configuration

La configuration Docker est définie dans le fichier `.env`.

Un modèle sans secrets est disponible dans :

```text
.env.example
```

| Variable          | Rôle                                   | Exemple                 |
| ----------------- | -------------------------------------- | ----------------------- |
| `DB_NAME`         | Nom de la base MySQL                   | `loom`                  |
| `DB_USER`         | Utilisateur MySQL utilisé par Loom     | `loom`                  |
| `DB_PASS`         | Mot de passe MySQL de l'application    | à définir               |
| `DB_ROOT_PASS`    | Mot de passe administrateur MySQL      | à définir               |
| `JWT_SECRET`      | Secret utilisé pour signer les JWT     | à générer               |
| `JWT_ALGORITHM`   | Algorithme de signature JWT            | `HS256`                 |
| `JWT_EXPIRATION`  | Durée de validité du token en secondes | `86400`                 |
| `APP_ENV`         | Environnement d'exécution PHP          | `production`            |
| `ALLOWED_ORIGINS` | Origines autorisées pour CORS          | `http://localhost:8080` |
| `FRONTEND_PORT`   | Port exposé par le frontend            | `8080`                  |
| `BACKEND_PORT`    | Port exposé par le backend             | `8081`                  |

> Le fichier `.env` ne doit jamais être ajouté au dépôt Git.

---

# Base de données

Loom utilise **MySQL 8.4**.

Lors du premier démarrage avec un volume Docker vide, les scripts SQL nécessaires à l'initialisation de la base sont exécutés automatiquement.

Ils comprennent notamment :

* le schéma principal ;
* les tables nécessaires à la messagerie ;
* les notifications ;
* la gestion de la visibilité du profil ;
* les migrations présentes dans le projet.

Les données sont conservées dans un volume Docker :

```text
db_data
```

Les fichiers envoyés par les utilisateurs sont également persistés dans :

```text
uploads_data
```

Cela signifie qu'un simple arrêt ou redémarrage des conteneurs ne supprime pas les données.

---

# Persistance des données

La commande :

```bash
docker compose down
```

arrête et supprime les conteneurs, mais **conserve les volumes**.

Les données restent donc disponibles au prochain démarrage.

En revanche :

```bash
docker compose down -v
```

supprime également les volumes Docker.

Cette commande peut donc entraîner la suppression de la base de données locale et des fichiers persistés.

---

# Développement

## Frontend

Pour travailler directement avec le serveur de développement Vite :

```bash
cd frontend
npm install
npm run dev
```

Le rechargement à chaud de Vite permet de voir immédiatement les modifications apportées à l'interface.

Pour vérifier la compilation de production :

```bash
npm run build
```

---

## Backend

Le backend est une API PHP fonctionnant avec Apache.

Il peut être exécuté :

* avec Docker ;
* ou avec un environnement PHP/Apache local.

Lors d'une exécution hors Docker, il peut être nécessaire d'adapter :

```env
ALLOWED_ORIGINS=
```

ainsi que l'adresse utilisée par le frontend pour contacter l'API.

---

# Commandes Docker

## Démarrer les services

```bash
docker compose up -d
```

## Reconstruire les images

```bash
docker compose up -d --build
```

## Voir l'état des services

```bash
docker compose ps
```

## Voir les logs

```bash
docker compose logs -f
```

## Logs du backend

```bash
docker compose logs -f backend
```

## Logs du frontend

```bash
docker compose logs -f frontend
```

## Arrêter Loom

```bash
docker compose down
```

## Reconstruire complètement les conteneurs

```bash
docker compose build --no-cache
docker compose up -d
```

---

# Communication entre les services

Docker Compose crée automatiquement un réseau interne permettant aux services de communiquer entre eux.

Par exemple, le backend n'utilise pas :

```text
localhost
```

pour contacter MySQL.

Il utilise le nom du service Docker :

```text
database
```

Dans un conteneur Docker, `localhost` représente uniquement le conteneur lui-même.

---

# Déploiement

La stack a également été conçue pour pouvoir être déployée sur un serveur Linux.

Elle peut notamment être utilisée dans un environnement :

```text
Proxmox
   │
   └── LXC Debian
          │
          └── Docker
                │
                └── Docker Compose
                      │
                      ├── Loom Frontend
                      ├── Loom Backend
                      └── MySQL
```

Sur un serveur Debian :

```bash
git clone <URL_DU_DEPOT>
cd <DOSSIER_DU_PROJET>

cp .env.example .env
nano .env

docker compose up -d --build
```

Pour vérifier le déploiement :

```bash
docker compose ps
docker compose logs -f
```

Pour mettre à jour l'application :

```bash
git pull
docker compose up -d --build
```

Les volumes existants ne sont pas supprimés lors de cette opération.

En production, il est recommandé d'exposer uniquement le frontend derrière :

* un reverse proxy ;
* HTTPS ;
* un nom de domaine.

La base de données ne doit pas être exposée publiquement.

---

# Sécurité

Plusieurs précautions doivent être respectées lors du déploiement :

* ne jamais versionner `.env` ;
* utiliser un `JWT_SECRET` long et aléatoire ;
* utiliser des mots de passe MySQL différents des valeurs d'exemple ;
* ne pas exposer MySQL directement sur Internet ;
* utiliser HTTPS en production ;
* limiter les origines autorisées par CORS ;
* effectuer régulièrement des sauvegardes des volumes Docker.

---

# Sauvegarde

Les principales données persistantes de Loom sont contenues dans :

```text
db_data
uploads_data
```

Dans un environnement de production, ces volumes doivent faire partie de la stratégie de sauvegarde du serveur.

---

# Contexte du projet

Loom a été réalisé dans le cadre de la formation :

**Développeur Web et Web Mobile — DWWM**

Le projet permet de mettre en pratique plusieurs compétences liées au développement d'une application web complète :

* conception d'une base de données relationnelle ;
* développement d'une API REST ;
* programmation backend avec PHP ;
* utilisation de PDO ;
* authentification JWT ;
* développement d'une SPA avec Vue.js ;
* utilisation de TypeScript ;
* gestion des interactions frontend/backend ;
* responsive design ;
* gestion des données persistantes ;
* conteneurisation avec Docker ;
* orchestration avec Docker Compose ;
* préparation au déploiement sur un serveur Linux.

---

# Auteur

Projet réalisé dans le cadre de la certification **Développeur Web et Web Mobile (DWWM)**.
