# 🎯 Ghrami Web

**A Social Learning Platform Connecting People Through Shared Hobbies, Skill Exchange & Local Education**

*Plateforme sociale d'apprentissage connectant les personnes à travers les loisirs partagés, les compétences et l'éducation.*

---

## 📱 About / À Propos

Ghrami is a modern web application that empowers users to:
- 🎯 **Track hobbies and celebrate progress** with milestones and badges
- 🤝 **Connect with people who share similar interests** through intelligent discovery
- 🔄 **Exchange skills through smart matching** and schedule meetings
- 📚 **Book classes with verified instructors** from a curated marketplace
- 💬 **Share experiences on a social feed** with the community
- 🤖 **Get AI-powered daily insights** with personalized summaries and recommendations

---

## ✨ Core Modules (5 Modules, 15 Tables)

| Module | Status | Features |
|--------|--------|----------|
| **👥 User Management** | ✅ Complete | Registration, authentication, profile management, friend lists, badge system, admin dashboard |
| **📱 Social Network** | ✅ Complete | Posts, comments, likes, stories, social feed, real-time interactions, @mentions |
| **🎯 Hobby Tracking** | ✅ Complete | Hobby management, progress tracking, milestones, statistics, achievement badges |
| **🔗 Smart Matching** | ✅ Complete | Intelligent matching algorithm, skill exchange, connection requests, meeting scheduling, discovery system |
| **📚 Classes & Booking** | ✅ Complete | Class marketplace, instructor dashboard, booking system, payment tracking, ratings & reviews |

---

## 🛠️ Tech Stack

**Backend:**
- **PHP 8.2.12** with **Symfony 7.0.5** framework
- **Doctrine ORM 3.6.3** for database abstraction
- **Groq API Integration** (llama-3.1-8b-instant) for AI-powered daily summaries

**Frontend:**
- **Twig 3.x** templating engine
- **Bootstrap 5** + custom CSS for responsive UI
- **JavaScript (Vanilla)** for interactive features

**Database:**
- **MySQL 8.0+** with **UTF-8MB4** encoding for multilingual support

**Tools & Utilities:**
- **Composer** for dependency management
- **Webpack Encore** for asset bundling
- **PHPUnit 11.5.55** for testing (101 tests, 182+ assertions)
- **PHPStan** for static analysis (Level 5)

**Architecture:**
- **MVC Pattern** with service layer
- **Dependency Injection Container**
- **Entity-Repository Pattern**

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

## 🗄️ Database Schema (15 Tables)

**Module 1: User Management** (3 tables)
- `users` — User profiles, authentication, basic info
- `friendships` — Friend connections with status tracking (PENDING/ACCEPTED/BLOCKED)
- `badges` — Badge definitions and user achievement tracking

**Module 2: Social Network** (4 tables)
- `posts` — User posts/articles with rich content
- `comments` — Comments on posts with threaded replies
- `post_likes` — Like tracking for posts and comments
- `stories` — Temporary story content (24-hour expiry concept)

**Module 3: Hobby Tracking** (3 tables)
- `hobbies` — User hobby definitions and preferences
- `progress` — Daily/weekly hobby progress entries with timestamps
- `milestones` — Achievement milestones with completion tracking

**Module 4: Smart Matching & Connections** (3 tables)
- `connections` — Skill exchange requests between users
- `meetings` — Scheduled meetings for skill exchange or collaboration
- `meeting_participants` — Attendance tracking for meetings

**Module 5: Classes & Bookings** (2 tables)
- `class_providers` — Verified instructor profiles
- `classes` — Course offerings with pricing and schedule info
- `bookings` — Class reservations with payment status tracking

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

---

## 🚨 What's New (May 2026)

### 🤖 AI-Powered Daily Summary
- **Feature**: Personalized daily usage digest with AI-generated insights
- **Integration**: Groq API (llama-3.1-8b-instant model)
- **Metrics Tracked**: Posts, comments, hobbies, learning hours, connections, classes taught, meetings, badges earned, likes received, messages sent
- **Location**: Modal popup accessible from dashboard after login
- **Setup**: Configure `GROQ_API_KEY=your_key_here` in `.env`

### 💰 Financial Precision Upgrade
- **Change**: Money fields converted from `FLOAT` to `DECIMAL(10,2)` to prevent floating-point arithmetic errors
- **Affected Fields**: 
  - `bookings.total_amount` (booking totals)
  - `classes.price` (course pricing)
- **Migration**: Execute the provided migration or run SQL manually (see "Upgrading" section)
- **Benefit**: Accurate financial calculations for payments and bookings

### 🌍 Timezone Standardization
- **Change**: PHP application timezone set to UTC
- **Implementation**: Added `boot()` method in `src/Kernel.php`
- **Benefit**: Consistent timestamp handling across PHP application and MySQL database
- **Impact**: All `DateTime` values now stored and compared in UTC

### 📝 Enhanced Error Logging
- **Improvement**: 8+ strategic logging checkpoints added across daily summary service
- **Coverage**: API calls, database queries, business logic transitions
- **Debugging**: Easier troubleshooting of AI summary generation failures
- **Fallback**: Service provides intelligent fallback recommendations if Groq API is unavailable

### ✅ Code Quality Improvements
- **PHPStan Level 5**: Fixed 3 type-safety issues in InstructorController
- **Testing**: 101 PHPUnit tests passing with 182+ assertions
- **Security**: All SQL queries use Doctrine prepared statements (no injection vulnerabilities)

### 📞 Messaging System
- **Fix**: Desktop messaging now includes `sent_at` timestamp on INSERT
- **Impact**: Synchronizes message timestamps between web and desktop clients

---

## 🛠️ Upgrading from Previous Versions

### Database Migration (FLOAT → DECIMAL)
If you previously used `FLOAT` for monetary columns, execute:
```sql
ALTER TABLE bookings MODIFY COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT '0.00';
ALTER TABLE classes MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT '0.00';
```

Or use Doctrine migrations:
```bash
php bin/console doctrine:migrations:migrate
```

### Environment Variables
Ensure your `.env` file contains:
```env
DATABASE_URL="mysql://username:password@localhost:3306/ghrami_db"
APP_ENV=prod
APP_SECRET=your_secret_key
GROQ_API_KEY=your_groq_api_key_here
```

### First-Time Setup
```bash
composer install
npm install && npm run build
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load  # Optional: load demo data
```

---

## 🎬 Promotional Video Script

**Duration:** 60-90 seconds | **Language:** Bilingual (French + English)
**Tone:** Upbeat, inspirational, community-focused

```
[OPENING - 5 seconds]
Upbeat music bed fades in. Ghrami logo appears with purple gradient reveal.
Voiceover (English): "Feeling weighed down? Ghrami is here to help."
Voiceover (French): "Vous vous sentez accablé? Ghrami est là pour aider."

[SCENE 1 - 10 seconds]
Quick montage of user posting hobby photo → notification appears → likes/comments flowing in.
Text overlay: "Share Your Passions"
Show the social feed interface with posts, comments, like animations.

[SCENE 2 - 10 seconds]
User browsing class marketplace → clicking a class → booking confirmation screen.
Instructor profile highlighted with verified badge.
Text overlay: "Learn From Experts"
Close-up of class details: price, schedule, instructor rating.

[SCENE 3 - 12 seconds]
Hobby progress tracker: milestone unlocked animation → achievement badge appears.
Progress chart showing 7-day activity.
Text overlay: "Track Your Growth"
Celebrate with confetti animation on milestone completion.

[SCENE 4 - 10 seconds]
Two users viewing connection suggestion → meeting scheduled → both on call/meeting screen.
Skill exchange context shown in UI.
Text overlay: "Connect & Exchange"
Smooth transition showing friend suggestions based on interests.

[SCENE 5 - 15 seconds]
MAIN FEATURE: Daily Summary modal opens in the app.
AI-powered summary text reveals with typing effect.
Stats cards populate (10+ metrics displayed).
Achievements and personalized recommendations appear.
Text overlay: "Your Daily AI Insight" (animated)
Voiceover: "Get personalized insights every day powered by AI."

[SCENE 6 - 10 seconds]
Montage of badges earned, milestones achieved, community interactions.
Show multiple users connecting and learning together.
Warm, vibrant color grading (purples, teals, gold accents).

[CLOSING - 8 seconds]
All five main features summarized in quick succession with icon animations.
CTA Card: "Join Ghrami Today"
Website URL and QR code displayed.
Final voiceover: "Don't just scroll. Do something meaningful. Join Ghrami."
Tagline on screen: "Connect. Learn. Share."
Social media handles appear at bottom.

[MUSIC & SOUND]
- Use upbeat, modern instrumental music
- Add subtle UI interaction sounds (swooshes, notification pings, success chimes)
- Voiceover should be warm, encouraging, gender-diverse if possible
```

**Platform Recommendations:**
- Create with: Synthesia, Pictory, Descript, or Adobe Express
- Dimensions: 1080x1920 (vertical for social media) or 1920x1080 (horizontal)
- Subtitles: French & English with 0.5s delay
- Post to: LinkedIn, Instagram, TikTok, YouTube Shorts
