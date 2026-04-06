# 🎯 Ghrami Web

**Plateforme sociale d'apprentissage connectant les personnes à travers les loisirs partagés, les compétences et l'éducation.**

---

## 📱 À Propos

Ghrami est une application web moderne qui aide les utilisateurs à :
- Se connecter avec des personnes partageant leurs intérêts
- Suivre leurs progrès personnels et leurs loisirs
- Échanger des compétences via des rencontres
- Réserver des cours auprès d'instructeurs vérifiés
- Créer et partager du contenu social
- Gagner des badges pour leurs accomplissements

---

## ✨ Modules

| Module | Statut | Fonctionnalités |
|--------|--------|-----------------|
| **Gestion Utilisateurs** | ✅ Complet | Inscription, authentification, profils avec photos, amis, badges, tableau de bord admin |
| **Réseaux Sociaux** | ✅ Complet | Publications, commentaires, fil d'actualité, interactions sociales |
| **Suivi de Loisirs** | ✅ Complet | Gestion de loisirs, suivi de progrès, jalons, statistiques |
| **Mise en Correspondance** | ✅ Complet | Algorithme de matching intelligent, échange de compétences, planification de rendez-vous |
| **Cours & Réservations** | ✅ Complet | Marché de cours, tableau de bord instructeur, réservations, paiements, évaluations |

---

## 🛠️ Stack Technique

- **PHP 8.2+** + **Symfony 7**
- **Twig** (moteur de templates)
- **MySQL 8.0+**
- **Doctrine ORM**
- **Composer** (gestionnaire de dépendances)
- **Webpack Encore** (assets)
- **Architecture MVC**

---

## 🚀 Démarrage Rapide

### Prérequis
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & npm

### Installation

1. **Cloner le dépôt**
```bash
git clone https://github.com/yourusername/ghrami-web.git
cd ghrami-web
```

2. **Installer les dépendances**
```bash
composer install
npm install && npm run build
```

3. **Configurer l'environnement**

Éditez le fichier `.env` :
```env
DATABASE_URL="mysql://root:votre_mot_de_passe@127.0.0.1:3306/ghrami_db"
APP_ENV=dev
APP_SECRET=votre_secret
```

4. **Créer la base de données**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

5. **Lancer le serveur**
```bash
symfony server:start
```

### Connexion Admin par Défaut
```
Email: chahine@ghrami.tn
Mot de passe: admin123
```

---

## 🎨 Guide de Style

### Couleurs
- **Primary**: `#667eea` — Actions principales, liens
- **Secondary**: `#764ba2` — Accents, gradients
- **Success**: `#4CAF50` — Confirmations, états positifs
- **Warning**: `#FF9800` — Avertissements, actions en attente
- **Danger**: `#f44336` — Erreurs, suppressions
- **Background**: `#f0f2f5` — Fond de page

### Composants UI
- **Border Radius**: 15–25px pour cartes, 20px pour boutons
- **Shadows**: subtiles avec `box-shadow` gaussienne
- **Padding**: 20–30px pour cartes, 10–20px pour boutons
- **Font Sizes**: titres 28–32px, texte courant 13–14px

---

## 🗄️ Base de Données

**15 tables réparties sur 5 modules :**

- **Module 1 :** `users`, `friendships`, `badges`
- **Module 2 :** `posts`, `comments`
- **Module 3 :** `hobbies`, `progress`, `milestones`
- **Module 4 :** `connections`, `meetings`, `meeting_participants`
- **Module 5 :** `class_providers`, `classes`, `bookings`

---

## 🚧 Améliorations Futures

- [ ] Notifications en temps réel
- [ ] Chat en direct entre utilisateurs
- [ ] Système de recommandation de cours
- [ ] Mode sombre
- [ ] Application mobile (Android/iOS)
- [ ] Intégration de paiement en ligne
- [ ] Visioconférence pour cours en ligne

---

## 🤝 Contribution

1. Forkez le dépôt
2. Créez une branche (`git checkout -b feature/NouvelleFonctionnalite`)
3. Commitez vos changements (`git commit -m 'Ajout NouvelleFonctionnalite'`)
4. Pushez (`git push origin feature/NouvelleFonctionnalite`)
5. Ouvrez une Pull Request

---

## 📄 Licence

MIT License — voir le fichier [LICENSE](LICENSE)

---

## 👥 Équipe

**Développé avec ❤️ par l'équipe OPGG**

Support : support@ghrami.tn

---

**Version:** 1.0.0 | **Dernière mise à jour:** Avril 2026