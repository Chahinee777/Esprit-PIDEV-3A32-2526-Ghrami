-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : lun. 04 mai 2026 à 11:17
-- Version du serveur : 10.4.28-MariaDB
-- Version de PHP : 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `ghrami_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `badges`
--

CREATE TABLE `badges` (
  `badge_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `earned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `badges`
--

INSERT INTO `badges` (`badge_id`, `user_id`, `name`, `description`, `earned_date`) VALUES
(1, 1, 'Pionnier Ghrami', 'Parmi les premiers utilisateurs de la plateforme', '2026-01-26 18:21:03'),
(2, 1, 'Social Actif', 'A créé plus de 10 connexion', '2026-01-26 18:21:03'),
(3, 2, 'Mentor Certifié', 'A aidé 5 personnes à atteindre leurs objectifs', '2026-01-26 18:21:03'),
(4, 3, 'Explorateur du Sahara', 'A partagé des expériences de randonnée dans le désert', '2026-01-26 18:21:03'),
(5, 3, 'Constance 30 Jours', 'A pratiqué un hobby pendant 30 jours consécutifs', '2026-01-26 18:21:03'),
(6, 5, 'Coach Inspiration', 'A inspiré la communauté avec ses conseils sportifs', '2026-01-26 18:21:03'),
(7, 1, 'Pro Footbaleur', 'Milieu de terrain offensif', '2026-02-03 18:14:35'),
(8, 8, '💎 Diamond Member', '1 year anniversary', '2026-02-10 08:04:51'),
(9, 12, '🥇 First Friend', 'Made their first friend on Ghrami', '2026-02-10 08:16:42'),
(10, 8, 'Booster', 'Boost ces amis', '2026-02-14 09:46:00'),
(13, 46, 'el fasa3 el mo2ases', 'yafsa3 barcha wdima re9d', '2026-02-15 13:41:19'),
(14, 51, '🥇 First Friend', 'Made their first friend on Ghrami', '2026-02-16 13:02:56'),
(15, 8, 'First Step 🎬', 'Write your first post', '2026-04-05 19:23:29'),
(16, 8, 'Hobby Enthusiast 🎯', 'Track 5 different hobbies', '2026-04-05 19:23:29'),
(17, 8, 'Connector 🤝', 'Attend your first meeting', '2026-04-05 19:23:29'),
(18, 11, 'First Step 🎬', 'Write your first post', '2026-04-11 22:38:01'),
(19, 11, 'Connector 🤝', 'Attend your first meeting', '2026-04-11 22:38:01');

-- --------------------------------------------------------

--
-- Structure de la table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` bigint(20) NOT NULL,
  `class_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'scheduled',
  `payment_status` varchar(20) DEFAULT 'pending',
  `total_amount` double NOT NULL,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL COMMENT '1–5 star rating left by student after completion',
  `review` text DEFAULT NULL COMMENT 'Written review left by student',
  `watch_progress` int(11) DEFAULT 0 COMMENT 'Last watched position saved in seconds'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `class_id`, `user_id`, `booking_date`, `status`, `payment_status`, `total_amount`, `stripe_session_id`, `rating`, `review`, `watch_progress`) VALUES
(1, 1, 1, '2024-02-01 09:00:00', 'completed', 'paid', 50, NULL, NULL, NULL, 0),
(2, 3, 2, '2024-02-02 13:00:00', 'scheduled', 'paid', 150, NULL, NULL, NULL, 0),
(3, 4, 4, '2024-02-03 08:00:00', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(4, 5, 5, '2024-02-04 10:00:00', 'scheduled', 'paid', 120, NULL, NULL, NULL, 0),
(5, 1, 8, '2026-02-11 19:59:04', 'scheduled', 'pending', 50, NULL, NULL, NULL, 0),
(17, 5, 8, '2026-02-15 12:22:47', 'cancelled', 'pending', 120, NULL, NULL, NULL, 0),
(18, 9, 10, '2026-02-15 12:27:35', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(19, 9, 46, '2026-02-15 13:44:04', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(20, 11, 8, '2026-02-15 13:48:18', 'scheduled', 'pending', 50, NULL, NULL, NULL, 0),
(21, 4, 8, '2026-02-16 11:49:46', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(22, 9, 51, '2026-02-16 13:25:50', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(24, 4, 51, '2026-02-23 11:11:03', 'scheduled', 'pending', 200, NULL, NULL, NULL, 0),
(25, 3, 8, '2026-02-23 19:58:24', 'scheduled', 'pending', 150, NULL, NULL, NULL, 0),
(26, 13, 12, '2026-02-24 07:59:04', 'cancelled', 'paid', 100, NULL, NULL, NULL, 0),
(28, 15, 12, '2026-02-24 15:45:46', 'scheduled', 'paid', 200, NULL, NULL, NULL, 0),
(29, 13, 12, '2026-02-24 17:17:31', 'pending', 'pending', 100, NULL, NULL, NULL, 0),
(30, 16, 12, '2026-02-24 17:20:49', 'cancelled', 'paid', 10, NULL, NULL, NULL, 10548),
(31, 16, 12, '2026-02-24 17:25:21', 'pending', 'pending', 10, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `class_id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` double NOT NULL,
  `duration` int(11) NOT NULL,
  `max_participants` int(11) NOT NULL,
  `video_path` varchar(500) DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL COMMENT 'Absolute path to local thumbnail image'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`class_id`, `provider_id`, `title`, `description`, `category`, `price`, `duration`, `max_participants`, `video_path`, `image_path`) VALUES
(1, 1, 'Beginner Yoga Workshop', 'Introduction to yoga fundamentals', 'fitness', 50, 90, 15, NULL, NULL),
(2, 1, 'Advanced Pilates', 'Intensive pilates training', 'fitness', 75, 60, 10, NULL, NULL),
(3, 2, 'JavaFX Masterclass', 'Build modern desktop applications', 'tech', 150, 180, 20, NULL, NULL),
(4, 2, 'React Native Bootcamp', 'Mobile app development from scratch', 'tech', 200, 240, 25, NULL, NULL),
(5, 3, 'UX Design Fundamentals', 'Learn user experience design', 'design', 120, 120, 15, NULL, NULL),
(9, 4, 'Trading', 'Crypto Currencies', 'finance', 200, 200, 10, NULL, NULL),
(11, 11, 'TLA', 'Matiere esprit', 'education', 50, 100, 30, NULL, NULL),
(13, 4, 'Gaming', 'Fortnite', 'gaming', 100, 10, 10, 'C:\\Users\\MSI\\Videos\\2026-02-23 21-59-28.mkv', NULL),
(15, 4, 'hamza', 'hamza', 'fitness', 200, 10, 10, 'C:\\Users\\MSI\\Desktop\\videoplayback.mp4', NULL),
(16, 4, 'aaaaa', 'aaaaaaaaa', 'aaaaaaa', 10, 10, 10, 'C:\\Users\\MSI\\Desktop\\videoplayback.mp4', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `class_providers`
--

CREATE TABLE `class_providers` (
  `provider_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `expertise` text DEFAULT NULL,
  `rating` double NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `class_providers`
--

INSERT INTO `class_providers` (`provider_id`, `user_id`, `company_name`, `expertise`, `rating`, `is_verified`) VALUES
(1, 2, 'Fitness First Tunisia', 'Yoga, Pilates, Nutrition', 4.8, 1),
(2, 3, 'Code Academy TN', 'Web Development, Mobile Apps', 4.9, 1),
(3, 4, 'Creative Studio', 'Graphic Design, UI/UX', 4.7, 1),
(4, 8, 'Esprit', 'Coding', 0, 1),
(5, 15, 'Esprit', 'Un grand Footballeur', 0, 1),
(10, 10, 'Esprit', 'el fas3a', 0, 1),
(11, 46, 'Esprit', 'El fas3a', 0, 1),
(12, 51, 'minds academy', 'tla\numl', 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `comments`
--

CREATE TABLE `comments` (
  `comment_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_url` varchar(500) DEFAULT NULL,
  `mood` varchar(100) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `comments`
--

INSERT INTO `comments` (`comment_id`, `post_id`, `user_id`, `content`, `created_at`, `image_url`, `mood`, `updated_at`) VALUES
(1, 1, 2, 'Beautiful shot! What camera did you use?', '2024-02-01 18:00:00', NULL, NULL, NULL),
(2, 1, 3, 'La Marsa beaches are the best! 😍', '2024-02-01 18:15:00', NULL, NULL, NULL),
(3, 2, 1, 'Keep it up! Consistency is key 💪', '2024-02-02 06:30:00', NULL, NULL, NULL),
(4, 3, 4, 'JavaFX is great! Need any help with UI/UX?', '2024-02-02 14:00:00', NULL, NULL, NULL),
(5, 5, 3, 'Very insightful! Thanks for sharing', '2024-02-03 15:30:00', NULL, NULL, NULL),
(6, 7, 8, 'Grand Equipe', '2026-02-13 15:38:21', NULL, NULL, NULL),
(8, 9, 8, 'Merci OPGG', '2026-02-14 10:21:37', NULL, NULL, NULL),
(22, 23, 11, 'klem ma39oul wmwzoun', '2026-02-15 13:30:07', NULL, NULL, NULL),
(23, 24, 46, 'j\'aime le food bcp', '2026-02-15 15:16:05', NULL, NULL, NULL),
(27, 32, 0, 'bonjour bonjour', '2026-02-28 20:25:06', NULL, NULL, NULL),
(28, 33, 0, 'hi', '2026-02-28 20:30:18', NULL, NULL, NULL),
(30, 49, 8, 'h', '2026-04-05 20:39:09', NULL, NULL, NULL),
(50, 56, 8, 'la poterie est vraiment interessante et artistique', '2026-04-07 09:26:56', NULL, NULL, NULL),
(52, 57, 8, 'la photographie a un goût très artistique', '2026-04-07 09:30:01', NULL, NULL, NULL),
(53, 57, 11, 'Super initiative ! C\'est exactement l\'esprit de Ghrami 🌟', '2026-04-07 09:32:22', NULL, NULL, NULL),
(54, 57, 12, 'magnifique', '2026-04-07 09:35:14', NULL, NULL, NULL),
(55, 58, 12, 'Bravo ! 50h c\'est un grand accomplissement. Je cours aussi à Tunis, \non peut s\'organiser une sortie ensemble !', '2026-04-07 09:36:20', NULL, NULL, NULL),
(56, 65, 11, 'jjj', '2026-04-11 22:46:45', NULL, NULL, NULL),
(57, 66, 11, 'jjjjjjjiiiiii', '2026-04-11 22:46:51', NULL, NULL, NULL),
(58, 66, 11, '@nourhen2004 jjj', '2026-04-11 22:47:28', NULL, NULL, NULL),
(59, 65, 11, 'jjjjj', '2026-04-11 23:23:15', NULL, NULL, NULL),
(60, 66, 11, 'yuuuuuuu', '2026-04-11 23:30:00', '11_1775954121250.png', NULL, '2026-04-12 00:35:21'),
(61, 66, 11, 'yyyy', '2026-04-11 23:30:11', NULL, NULL, NULL),
(62, 68, 8, '', '2026-04-13 20:17:15', '8_1776115035007.png', 'Excité(e) 🤩', NULL),
(63, 68, 8, '@anasBiggie hi', '2026-04-13 20:17:36', NULL, NULL, NULL),
(64, 68, 8, '@anasBiggie hello', '2026-04-13 20:17:53', NULL, NULL, NULL),
(65, 68, 8, 'hello', '2026-04-16 18:49:29', 'https://media1.giphy.com/media/v1.Y2lkPWQwYzA1NDczaWw4c3k2M2h0c21oenYxeTNtem5ncWw0NzRidTBoMnliaDJxa3dhNCZlcD12MV9naWZzX3NlYXJjaCZjdD1n/Xev2JdopBxGj1LuGvt/giphy.gif', NULL, NULL),
(66, 68, 8, 'hi', '2026-04-16 18:49:43', NULL, NULL, NULL),
(67, 68, 8, 'hi', '2026-04-16 18:49:58', 'https://media2.giphy.com/media/v1.Y2lkPWQwYzA1NDczeW00c2U5eTdwdHV0N2xnNG5wajNuc3hjdzFya3JyM2E2bHZiamt1MSZlcD12MV9naWZzX3NlYXJjaCZjdD1n/Zjuq13wY6V0fXw9Q15/giphy-downsized-medium.gif', NULL, NULL),
(68, 68, 8, '', '2026-04-20 17:07:05', 'https://media3.giphy.com/media/v1.Y2lkPWQwYzA1NDczMnRjeHlyYnVoMHV5dzNwYzN1ZXo2dGFncGRmOGhqNnhrYTUxajRoaSZlcD12MV9naWZzX3NlYXJjaCZjdD1n/Zwkxqa2c6C4avWnrq3/giphy.gif', NULL, NULL),
(69, 68, 8, 'h', '2026-04-20 17:07:31', NULL, NULL, NULL),
(70, 69, 8, 'hh', '2026-04-20 17:07:48', NULL, NULL, NULL),
(71, 69, 8, 'hh', '2026-04-20 17:07:52', NULL, NULL, NULL),
(72, 69, 8, 'jj', '2026-04-20 17:07:55', NULL, NULL, NULL),
(73, 69, 8, 'jj', '2026-04-20 17:07:59', NULL, NULL, NULL),
(74, 69, 8, 'jj', '2026-04-20 17:08:06', NULL, NULL, NULL),
(75, 69, 8, '@anasBiggie jj', '2026-04-20 17:20:29', NULL, NULL, NULL),
(76, 69, 8, '@anasBiggie jj', '2026-04-20 17:20:29', NULL, NULL, NULL),
(77, 69, 8, 'bonghhg', '2026-04-20 17:20:45', NULL, NULL, NULL),
(78, 69, 8, '@anasBiggie hh', '2026-04-20 17:20:51', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `connections`
--

CREATE TABLE `connections` (
  `connection_id` varchar(36) NOT NULL,
  `initiator_id` bigint(20) NOT NULL,
  `receiver_id` bigint(20) NOT NULL,
  `connection_type` varchar(50) NOT NULL,
  `receiver_skill` varchar(100) DEFAULT NULL,
  `initiator_skill` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `connections`
--

INSERT INTO `connections` (`connection_id`, `initiator_id`, `receiver_id`, `connection_type`, `receiver_skill`, `initiator_skill`, `status`) VALUES
('056d3c0e-91f3-4fe6-9920-383bc6edfef3', 8, 51, 'activity', 'bbbb', 'aaaa', 'accepted'),
('186e3bea-3590-4a1b-a86e-90780f9d86be', 15, 8, 'skill', 'Baking', 'Art', 'accepted'),
('1f727e36-7008-4b2e-8ac1-25d9c2230327', 57, 8, 'skill', 'fffff', 'hhh', 'accepted'),
('648aab68-94e3-43b0-9311-b823bc2628aa', 8, 10, 'hobby', 'gaming', 'football', 'pending'),
('69ccb289-1dfc-40b7-9066-9d6529bbe35d', 8, 12, 'skill', 'cooking', 'driving', 'accepted'),
('86db099b-6941-46e5-80a3-4878ae40a81f', 46, 11, 'hobby', 'music', 'music', 'accepted'),
('9f017a4a-0cea-44c5-bea2-406068b3c240', 8, 46, 'skill', 'sport', 'cooking', 'pending'),
('d0334e28-0510-11f1-8e75-047c163dbfbf', 1, 2, 'skill', 'Fitness Training', 'Photography', 'accepted'),
('d033a8ca-0510-11f1-8e75-047c163dbfbf', 3, 4, 'activity', 'Design', 'Programming', 'pending'),
('d033a9ee-0510-11f1-8e75-047c163dbfbf', 2, 5, 'skill', 'Marketing', 'Yoga', 'accepted');

-- --------------------------------------------------------

--
-- Structure de la table `digest_logs`
--

CREATE TABLE `digest_logs` (
  `digest_log_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `content` longtext NOT NULL,
  `sent_at` datetime NOT NULL,
  `channel` varchar(20) NOT NULL,
  `opened` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260411130000', '2026-04-11 23:01:51', 19),
('DoctrineMigrations\\Version20260412114000', '2026-04-12 00:18:38', 44),
('DoctrineMigrations\\Version20260415144108', '2026-05-03 16:49:47', 73),
('DoctrineMigrations\\Version20260415152820', '2026-05-03 16:49:47', 37),
('DoctrineMigrations\\Version20260418191354', '2026-04-18 19:14:15', 51),
('DoctrineMigrations\\Version20260418192208', '2026-05-03 16:49:47', 0),
('DoctrineMigrations\\Version20260418212545', '2026-04-18 21:26:32', 109);

-- --------------------------------------------------------

--
-- Structure de la table `friendships`
--

CREATE TABLE `friendships` (
  `friendship_id` bigint(20) NOT NULL,
  `user1_id` bigint(20) NOT NULL,
  `user2_id` bigint(20) NOT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED','BLOCKED') DEFAULT 'PENDING',
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `friendships`
--

INSERT INTO `friendships` (`friendship_id`, `user1_id`, `user2_id`, `status`, `created_date`, `accepted_date`) VALUES
(1, 1, 2, 'ACCEPTED', '2026-01-26 18:21:03', '2026-01-26 18:21:03'),
(2, 1, 3, 'ACCEPTED', '2026-01-26 18:21:03', '2026-01-26 18:21:03'),
(3, 2, 3, 'ACCEPTED', '2026-01-26 18:21:03', '2026-01-26 18:21:03'),
(4, 1, 4, 'PENDING', '2026-01-26 18:21:03', NULL),
(5, 3, 5, 'ACCEPTED', '2026-01-26 18:21:03', '2026-01-26 18:21:03'),
(6, 4, 5, 'PENDING', '2026-01-26 18:21:03', NULL),
(7, 8, 11, 'ACCEPTED', '2026-02-08 09:48:27', '2026-03-01 22:46:37'),
(8, 10, 8, 'ACCEPTED', '2026-02-08 09:50:08', '2026-02-08 09:50:42'),
(9, 12, 8, 'ACCEPTED', '2026-02-10 08:15:17', '2026-02-10 08:15:48'),
(10, 15, 8, 'ACCEPTED', '2026-02-12 12:41:30', '2026-02-12 12:45:15'),
(14, 46, 11, 'ACCEPTED', '2026-02-15 13:29:14', '2026-02-15 13:29:41'),
(16, 51, 8, 'ACCEPTED', '2026-02-16 13:00:59', '2026-02-16 13:01:23'),
(17, 8, 57, 'ACCEPTED', '2026-02-23 15:11:05', '2026-02-23 15:11:34'),
(18, 8, 53, 'PENDING', '2026-02-23 19:50:26', NULL),
(19, 11, 12, 'ACCEPTED', '2026-03-01 22:46:54', '2026-03-01 22:47:23'),
(20, 62, 8, 'ACCEPTED', '2026-04-07 09:38:51', '2026-04-07 09:40:00');

-- --------------------------------------------------------

--
-- Structure de la table `hidden_posts`
--

CREATE TABLE `hidden_posts` (
  `hidden_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `hidden_posts`
--

INSERT INTO `hidden_posts` (`hidden_id`, `user_id`, `post_id`, `created_at`) VALUES
(1, 11, 70, '2026-04-20 19:07:10');

-- --------------------------------------------------------

--
-- Structure de la table `hobbies`
--

CREATE TABLE `hobbies` (
  `hobby_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `hobbies`
--

INSERT INTO `hobbies` (`hobby_id`, `user_id`, `name`, `category`, `description`) VALUES
(1, 1, 'Photography', 'art', 'Landscape and portrait photography'),
(2, 2, 'Yoga', 'fitness', 'Daily yoga practice and meditation'),
(3, 3, 'Programming', 'tech', 'Learning new frameworks and languages'),
(4, 4, 'Digital Art', 'art', 'Creating illustrations and designs'),
(5, 5, 'Content Creation', 'marketing', 'Social media content and copywriting'),
(6, 8, 'Coding', 'Technology', 'Developper applications web'),
(7, 8, 'Football', 'Sports & Fitness', 'Real Madrid'),
(9, 11, 'Football', 'Sports & Fitness', 'Barca'),
(10, 8, 'Baking', 'Cooking', 'Cookies'),
(14, 46, 'Piano', 'Music', 'Piano Tiles'),
(15, 46, 'Guitar', 'Music', 'Shawn Mendes'),
(18, 8, 'violin player', 'Music', 'violoin player pro'),
(19, 8, 'Fashion', 'Arts & Crafts', 'KEJFNIEZBFIrzf'),
(20, 51, 'Football', 'Sports & Fitness', 'mILIEU DEFF'),
(23, 8, 'guitare', 'Music', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `meetings`
--

CREATE TABLE `meetings` (
  `meeting_id` varchar(36) NOT NULL,
  `connection_id` varchar(36) NOT NULL,
  `organizer_id` bigint(20) NOT NULL,
  `meeting_type` varchar(20) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `meetings`
--

INSERT INTO `meetings` (`meeting_id`, `connection_id`, `organizer_id`, `meeting_type`, `location`, `scheduled_at`, `duration`, `status`) VALUES
('006c2d92-2d7a-4112-b60e-cd8a8afff914', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', 'fahs', '2026-02-16 12:12:51', 60, 'cancelled'),
('11e5617e-1192-44f6-ae7d-c7347166286f', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', 'sidi bousaid', '2026-02-12 15:03:58', 60, 'cancelled'),
('1fc0d686-0dc3-4278-b143-83bc396ac910', '86db099b-6941-46e5-80a3-4878ae40a81f', 11, 'physical', 'neb3do aala sidi hsine', '2026-02-21 12:00:00', 60, 'scheduled'),
('2612f31d-590a-4a44-b7dd-3cb020990e2e', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', 'centre ville', '2026-02-14 12:27:32', 60, 'completed'),
('45bc106a-3e41-4def-a09b-06c70ae57694', '056d3c0e-91f3-4fe6-9920-383bc6edfef3', 8, 'physical', 'Montplaisir, خير الدين باشا, معتمدية حي الخضراء, Tunis, 1073, Tunisia', '2026-02-28 12:00:00', 60, 'scheduled'),
('4e14f84e-218e-4602-85e3-c913424c2726', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', 'esprit', '2026-02-16 12:21:24', 60, 'completed'),
('64025203-ccf3-4b48-bf09-aaef8b77a9e5', '69ccb289-1dfc-40b7-9066-9d6529bbe35d', 8, 'physical', 'cité olympique', '2026-02-16 14:22:40', 60, 'completed'),
('8004b327-8425-4e7c-8997-3e62c2f27f9d', '1f727e36-7008-4b2e-8ac1-25d9c2230327', 8, 'physical', 'Tunis, شارع المحطة, Sidi Al Bachir, باب البحر, معتمدية باب بحر, Tunis, 1151, Tunisia', '2026-02-27 12:00:00', 60, 'scheduled'),
('9ace2db6-4bc2-43a4-b22d-7b208ac40bfb', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', '', '2026-02-12 14:56:00', 60, 'completed'),
('c8c1c3e1-3597-45ef-a294-104adf2cb5c5', '186e3bea-3590-4a1b-a86e-90780f9d86be', 8, 'physical', '', '2026-02-12 14:55:39', 60, 'cancelled'),
('d04020cb-0510-11f1-8e75-047c163dbfbf', 'd0334e28-0510-11f1-8e75-047c163dbfbf', 1, 'physical', 'Coffee Shop Tunis', '2024-02-15 13:00:00', 60, 'scheduled'),
('d812fc8e-9a86-4483-9f23-000966664c24', '056d3c0e-91f3-4fe6-9920-383bc6edfef3', 8, 'physical', 'Bouselem', '2026-02-19 12:00:00', 60, 'scheduled'),
('fac0a260-3ead-488c-9f5d-13c820bced43', '69ccb289-1dfc-40b7-9066-9d6529bbe35d', 12, 'physical', 'rades', '2026-02-16 12:12:43', 60, 'cancelled');

-- --------------------------------------------------------

--
-- Structure de la table `meeting_participants`
--

CREATE TABLE `meeting_participants` (
  `participant_id` varchar(36) NOT NULL,
  `meeting_id` varchar(36) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `meeting_participants`
--

INSERT INTO `meeting_participants` (`participant_id`, `meeting_id`, `user_id`, `is_active`) VALUES
('001d40af-3b31-4a14-9401-19d9f678ced9', 'd812fc8e-9a86-4483-9f23-000966664c24', 51, 1),
('026e932a-bb32-4850-ab8b-833fe035c027', '4e14f84e-218e-4602-85e3-c913424c2726', 8, 1),
('15cd9338-16ec-4dde-94f7-7d18e7371941', '11e5617e-1192-44f6-ae7d-c7347166286f', 8, 1),
('1d0c4b4f-9558-453e-9fa0-ed12a363869e', '9ace2db6-4bc2-43a4-b22d-7b208ac40bfb', 8, 1),
('20116c9e-28f0-4067-bd6a-d4461f4ec922', '2612f31d-590a-4a44-b7dd-3cb020990e2e', 8, 1),
('41c9ef8f-63e3-403c-b393-96553efe5cc9', '006c2d92-2d7a-4112-b60e-cd8a8afff914', 15, 1),
('466ca631-f13a-45c6-ac13-bf5fe39a639d', '8004b327-8425-4e7c-8997-3e62c2f27f9d', 8, 1),
('715fe4cf-a23e-4709-8c7a-772e1796db27', '45bc106a-3e41-4def-a09b-06c70ae57694', 8, 1),
('75e1326b-cd1e-4100-b0b4-feb92691a23c', '1fc0d686-0dc3-4278-b143-83bc396ac910', 11, 1),
('77c51604-4f13-4156-8c28-519e86cf5931', '11e5617e-1192-44f6-ae7d-c7347166286f', 15, 1),
('869ee50d-ef7e-48f0-a980-9b9a82ad1a32', 'fac0a260-3ead-488c-9f5d-13c820bced43', 12, 1),
('8b741892-32d9-4e71-ac20-2dcdb5d5a016', 'c8c1c3e1-3597-45ef-a294-104adf2cb5c5', 8, 1),
('945429fe-5039-4a5d-bf70-58fc856f6aad', '64025203-ccf3-4b48-bf09-aaef8b77a9e5', 12, 1),
('977a7735-2a37-483e-9510-532c5d78c630', '006c2d92-2d7a-4112-b60e-cd8a8afff914', 8, 1),
('97bf5b10-1074-4f4c-b35a-e3bef2cbe734', 'fac0a260-3ead-488c-9f5d-13c820bced43', 8, 1),
('a18b1ece-ac51-487d-9fec-073bd1b9f8a1', 'd812fc8e-9a86-4483-9f23-000966664c24', 8, 1),
('d0445ff7-0510-11f1-8e75-047c163dbfbf', 'd04020cb-0510-11f1-8e75-047c163dbfbf', 1, 1),
('d044bc36-0510-11f1-8e75-047c163dbfbf', 'd04020cb-0510-11f1-8e75-047c163dbfbf', 2, 1),
('d0d0fa27-11d4-409e-ad77-ba9787e171f5', 'c8c1c3e1-3597-45ef-a294-104adf2cb5c5', 15, 1),
('d180bd90-601c-49d4-b379-3252cace95dd', '8004b327-8425-4e7c-8997-3e62c2f27f9d', 57, 1),
('d32cebbc-e030-43ef-9937-240292f33643', '2612f31d-590a-4a44-b7dd-3cb020990e2e', 15, 1),
('dc7d3b3d-6532-4f5a-b64d-975b05a4e154', '64025203-ccf3-4b48-bf09-aaef8b77a9e5', 8, 1),
('e0380b81-7ac3-4674-83a6-eea17af0c2d0', '4e14f84e-218e-4602-85e3-c913424c2726', 15, 1),
('e4416a44-0ef7-46a1-b8d4-f64f92825924', '9ace2db6-4bc2-43a4-b22d-7b208ac40bfb', 15, 1),
('f9410b8f-1eb3-4f4a-b9b2-e7db76d59563', '1fc0d686-0dc3-4278-b143-83bc396ac910', 46, 1),
('f94e6a23-6cf5-4587-89ef-40d346ad902e', '45bc106a-3e41-4def-a09b-06c70ae57694', 51, 1);

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `message_id` bigint(20) NOT NULL,
  `sender_id` bigint(20) NOT NULL,
  `receiver_id` bigint(20) NOT NULL,
  `content` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `content`, `sent_at`, `is_read`) VALUES
(1, 8, 51, 'salut chahine', '2026-02-21 15:01:26', 1),
(2, 51, 8, 'ahla bik', '2026-02-21 15:09:55', 1),
(3, 8, 51, 'coucou les babies', '2026-02-23 11:08:49', 1),
(4, 51, 8, 'ahla bkhouya', '2026-02-23 11:09:35', 1),
(5, 8, 51, 'hello', '2026-02-23 11:54:34', 1),
(6, 51, 8, 'hey', '2026-02-23 11:55:10', 0),
(7, 8, 51, 'hey', '2026-02-23 11:55:27', 0),
(8, 8, 12, 'hello', '2026-02-23 13:21:34', 1),
(9, 8, 12, 'cc', '2026-02-23 14:48:37', 1);

-- --------------------------------------------------------

--
-- Structure de la table `milestones`
--

CREATE TABLE `milestones` (
  `milestone_id` bigint(20) NOT NULL,
  `hobby_id` bigint(20) NOT NULL,
  `title` varchar(100) NOT NULL,
  `target_date` date DEFAULT NULL,
  `is_achieved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `milestones`
--

INSERT INTO `milestones` (`milestone_id`, `hobby_id`, `title`, `target_date`, `is_achieved`) VALUES
(1, 1, 'First exhibition', '2024-06-01', 0),
(2, 2, 'Become certified instructor', '2024-08-01', 0),
(3, 3, 'Contribute to open source', '2024-05-01', 1),
(4, 4, 'Launch design agency', '2024-12-01', 0),
(5, 5, 'Reach 10K followers', '2024-07-01', 0),
(6, 9, 'Sa7e7t maa ljuve', '2026-02-26', 0),
(7, 6, 'Engeneer', '2026-02-10', 0),
(8, 10, 'Chef De Cuisine', '2026-02-28', 0),
(9, 10, 'Milk and cookies', '2026-02-08', 0),
(11, 14, 'Presenter mes skill infront ma famille', '2026-02-28', 0),
(12, 14, 'Test', '2026-02-14', 0),
(13, 18, 'proooooo', '2026-03-14', 0),
(14, 20, 'Test', '2026-02-28', 1),
(15, 20, 'gggg', '2026-02-11', 0);

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `type` varchar(50) NOT NULL,
  `content` varchar(500) NOT NULL,
  `related_user_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `content`, `related_user_id`, `created_at`, `is_read`) VALUES
(1, 51, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-21 15:01:26', 1),
(2, 8, 'MESSAGE', 'Chahine Aouled Amor vous a envoyé un message 💬', 51, '2026-02-21 15:09:55', 1),
(3, 51, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-23 11:08:49', 0),
(4, 8, 'MESSAGE', 'Chahine Aouled Amor vous a envoyé un message 💬', 51, '2026-02-23 11:09:35', 0),
(5, 51, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-23 11:54:34', 0),
(6, 8, 'MESSAGE', 'Chahine Aouled Amor vous a envoyé un message 💬', 51, '2026-02-23 11:55:10', 0),
(7, 51, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-23 11:55:27', 0),
(8, 12, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-23 13:21:34', 0),
(9, 12, 'MESSAGE', 'ANAS BIGGIE vous a envoyé un message 💬', 8, '2026-02-23 14:48:37', 0),
(10, 57, 'FRIEND_REQUEST', 'ANAS BIGGIE vous a envoyé une demande d\'amitié', 8, '2026-02-23 16:11:07', 1),
(11, 8, 'FRIEND_ACCEPTED', 'Chahine Aouled Amor a accepté votre demande d\'amitié ✅', 57, '2026-02-23 16:11:36', 0),
(12, 53, 'FRIEND_REQUEST', 'ANAS BIGGIE vous a envoyé une demande d\'amitié', 8, '2026-02-23 20:50:29', 0),
(13, 8, 'FRIEND_ACCEPTED', 'Nourhen Dheker a accepté votre demande d\'amitié ✅', 11, '2026-03-01 23:46:38', 0),
(14, 12, 'FRIEND_REQUEST', 'Nourhen Dheker vous a envoyé une demande d\'amitié', 11, '2026-03-01 23:46:55', 1),
(15, 11, 'FRIEND_ACCEPTED', 'Hamza Mnajja a accepté votre demande d\'amitié ✅', 12, '2026-03-01 23:47:25', 0),
(16, 8, 'BADGE_EARNED', '🏆 You earned the \"First Step 🎬\" badge!', NULL, '2026-04-05 19:23:29', 0),
(17, 8, 'BADGE_EARNED', '🏆 You earned the \"Hobby Enthusiast 🎯\" badge!', NULL, '2026-04-05 19:23:29', 0),
(18, 8, 'BADGE_EARNED', '🏆 You earned the \"Connector 🤝\" badge!', NULL, '2026-04-05 19:23:29', 0),
(19, 8, 'POST_LIKE', 'Someone liked your post.', 11, '2026-04-07 09:31:50', 0),
(20, 8, 'COMMENT', 'Someone commented on your post.', 11, '2026-04-07 09:32:22', 0),
(21, 8, 'POST_LIKE', 'Someone liked your post.', 12, '2026-04-07 09:34:57', 0),
(22, 8, 'COMMENT', 'Someone commented on your post.', 12, '2026-04-07 09:35:14', 0),
(23, 8, 'POST_LIKE', 'Someone liked your post.', 12, '2026-04-07 09:35:29', 0),
(24, 8, 'COMMENT', 'Someone commented on your post.', 12, '2026-04-07 09:36:20', 0),
(25, 8, 'FRIEND_REQUEST', 'You received a new friend request.', 62, '2026-04-07 09:38:51', 1),
(26, 62, 'FRIEND_ACCEPTED', 'Your friend request was accepted.', 8, '2026-04-07 09:40:00', 0),
(27, 11, 'BADGE_EARNED', '🏆 You earned the \"First Step 🎬\" badge!', NULL, '2026-04-11 22:38:01', 0),
(28, 11, 'BADGE_EARNED', '🏆 You earned the \"Connector 🤝\" badge!', NULL, '2026-04-11 22:38:01', 0);

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

CREATE TABLE `posts` (
  `post_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `content` text NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `spotify_track_id` varchar(500) DEFAULT NULL,
  `spotify_song_title` varchar(500) DEFAULT NULL,
  `spotify_artist` varchar(500) DEFAULT NULL,
  `spotify_album_image` varchar(1000) DEFAULT NULL,
  `spotify_track_url` varchar(1000) DEFAULT NULL,
  `hidden_until` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `mood` varchar(100) DEFAULT NULL,
  `hobby_tag` varchar(100) DEFAULT NULL,
  `visibility` varchar(20) NOT NULL DEFAULT 'public',
  `updated_at` datetime DEFAULT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `content`, `image_url`, `created_at`, `spotify_track_id`, `spotify_song_title`, `spotify_artist`, `spotify_album_image`, `spotify_track_url`, `hidden_until`, `location`, `mood`, `hobby_tag`, `visibility`, `updated_at`, `is_hidden`, `is_pinned`) VALUES
(1, 1, 'Just captured an amazing sunset at La Marsa! 🌅 #Photography', NULL, '2024-02-01 17:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(2, 2, 'Morning yoga session complete! Starting the day with positive energy 🧘‍♀️', NULL, '2024-02-02 06:15:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(3, 3, 'Working on a new JavaFX project. Love the framework! 💻', NULL, '2024-02-02 13:20:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(4, 4, 'New design project completed! Check out my portfolio 🎨', NULL, '2024-02-03 10:45:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(5, 5, 'Marketing tip of the day: Know your audience! 📊', NULL, '2024-02-03 15:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(7, 10, 'Hala Madrid..Y nada mas', '10_1771000602829_8ece0.jpg', '2026-02-13 15:36:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(9, 8, 'je suis heureux car je utilise Ghrami', '8_1771066256693_487744895_9596645127062608_1218059749256759297_n.jpg', '2026-02-14 09:50:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(23, 46, 'ahsen haja heya lfas3a', NULL, '2026-02-15 13:28:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(24, 11, 'astro burger for the win', '11_1771165890134_unnamed.jpg', '2026-02-15 13:31:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(30, 8, 'je suis anas et je suis content', '8_1771880160139_Origin.jpg', '2026-02-23 19:56:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(31, 0, 'bonjour', NULL, '2026-02-28 20:23:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(32, 0, 'bonjour J\'espère que vous avez passé une journée super ! Qu\'est-ce qui vous fait sourire aujourd\'hui ?', NULL, '2026-02-28 20:24:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(33, 0, 'hi', NULL, '2026-02-28 20:27:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(34, 0, 'comment vous pouvez vous démarquer sur les réseaux sociaux en partageant vos passions, vos expériences et vos connaissances de manière authentique et engageante !', NULL, '2026-02-28 20:28:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(35, 0, 'hi Comment allez-vous aujourd\'hui ?', '0_ai_1772314127528.png', '2026-02-28 20:28:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(36, 0, 'comment ca', NULL, '2026-02-28 20:30:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(37, 0, 'hhh J\'ai eu la meilleure journée depuis des mois !', '0_ai_1772314240978.png', '2026-02-28 20:30:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(38, 8, 'bonjour', NULL, '2026-03-01 21:49:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(39, 8, 'bonjour', NULL, '2026-03-01 22:01:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(40, 8, 'hi', NULL, '2026-03-01 22:01:33', NULL, NULL, NULL, NULL, NULL, '2026-03-03 03:14:25', NULL, NULL, NULL, 'public', NULL, 0, 0),
(41, 8, 'bonjour', NULL, '2026-03-01 22:15:58', NULL, NULL, NULL, NULL, NULL, '2026-03-03 03:12:05', NULL, NULL, NULL, 'public', NULL, 0, 0),
(42, 8, 'fff', NULL, '2026-03-01 23:15:17', NULL, NULL, NULL, NULL, NULL, '2026-03-03 03:11:59', NULL, NULL, NULL, 'public', NULL, 0, 0),
(43, 8, 'Voiture de sport élégante et puissante.', '8_1772417598371_Capture d’écran 2026-02-23 à 10.46.04.png', '2026-03-02 01:13:27', NULL, NULL, NULL, NULL, NULL, '2026-03-03 03:13:37', NULL, NULL, NULL, 'public', NULL, 0, 0),
(44, 8, 'Voiture de sport élégante ! #voituresportive #design #bleu', '8_1772417683319_Capture d’écran 2026-02-23 à 10.46.04.png', '2026-03-02 01:14:51', NULL, NULL, NULL, NULL, NULL, '2026-03-03 03:15:09', NULL, NULL, NULL, 'public', NULL, 0, 0),
(45, 8, 'La Porsche Taycan 2024 est une voiture électrique de luxe.', '8_1772418293696_Capture d’écran 2026-02-23 à 10.58.18.png', '2026-03-02 01:25:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(46, 8, 'bonjour', NULL, '2026-03-02 01:26:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(47, 8, 'bonjour', NULL, '2026-03-02 11:20:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(48, 8, 'salut', NULL, '2026-04-05 19:23:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(49, 8, 'bmw', '8_1775420706153.png', '2026-04-05 19:25:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(50, 8, 'h', NULL, '2026-04-05 20:38:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(51, 8, 'heelo', NULL, '2026-04-05 21:55:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(53, 8, 'ggg', NULL, '2026-04-06 23:51:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(56, 8, 'Je cherche quelqu\'un pour m\'apprendre le montage vidéo sur Premiere Pro.  En échange je peux vous apprendre la poterie ou la cuisine tunisienne !', NULL, '2026-04-07 09:20:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(57, 8, 'Cours gratuit ce samedi : initiation à la photographie de rue à Tunis.  Places limitées, commentez pour réserver votre place 📷', '8_1775557334197.jpg', '2026-04-07 09:22:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(58, 8, 'Bonne nouvelle — j\'ai atteint mon milestone de 50 heures de pratique en ukulélé !  Ghrami m\'a vraiment aidé à rester motivé et à suivre mes progrès 🎵', NULL, '2026-04-07 09:23:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(59, 8, 'jjjj', NULL, '2026-04-11 22:02:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(60, 8, 'hii', NULL, '2026-04-11 22:27:35', NULL, NULL, NULL, NULL, NULL, NULL, 'Kébili', 'Stressé(e) 😓', 'Voyage', 'friends', NULL, 0, 0),
(61, 8, 'bonjour', NULL, '2026-04-11 22:30:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'private', NULL, 0, 0),
(62, 11, 'hii ..', NULL, '2026-04-11 22:38:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', '2026-04-11 23:38:20', 0, 0),
(63, 11, 'coucou', NULL, '2026-04-11 22:39:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'private', NULL, 0, 0),
(64, 11, 'hello', NULL, '2026-04-11 22:41:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'friends', NULL, 0, 0),
(65, 11, 'hii', NULL, '2026-04-11 22:41:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'private', NULL, 0, 0),
(66, 11, 'hhhyy777', NULL, '2026-04-11 22:45:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', '2026-04-11 23:45:24', 0, 0),
(67, 8, 'bonjour', NULL, '2026-04-12 11:39:32', NULL, NULL, NULL, NULL, NULL, NULL, 'Gafsa', 'Fatigué(e) 😴', 'Randonnée', 'friends', NULL, 1, 0),
(68, 8, 'sahreya lila chkoun yji', NULL, '2026-04-13 09:04:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Curieux(se) 🧐', NULL, 'public', NULL, 0, 0),
(69, 8, 'bonojurrr', NULL, '2026-04-20 17:07:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0),
(70, 8, 'baby', NULL, '2026-04-20 18:06:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'public', NULL, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `post_hidden_by_user`
--

CREATE TABLE `post_hidden_by_user` (
  `user_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `hidden_until` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `post_hidden_by_user`
--

INSERT INTO `post_hidden_by_user` (`user_id`, `post_id`, `hidden_until`, `created_at`) VALUES
(8, 42, '2026-03-03 13:19:54', '2026-03-02 12:19:54'),
(8, 43, '2026-03-03 03:21:33', '2026-03-02 02:21:33'),
(8, 44, '2026-03-03 03:21:25', '2026-03-02 02:21:25'),
(8, 45, '2026-03-03 03:26:08', '2026-03-02 02:26:08'),
(8, 46, '2026-03-03 03:26:53', '2026-03-02 02:26:53'),
(8, 47, '2026-03-03 13:20:08', '2026-03-02 12:20:08'),
(12, 44, '2026-03-03 03:35:15', '2026-03-02 02:35:15'),
(12, 45, '2026-03-03 03:25:50', '2026-03-02 02:25:50');

-- --------------------------------------------------------

--
-- Structure de la table `post_hides`
--

CREATE TABLE `post_hides` (
  `user_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `hidden_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `post_hides`
--

INSERT INTO `post_hides` (`user_id`, `post_id`, `hidden_at`) VALUES
(8, 30, '2026-03-02 00:15:31'),
(8, 38, '2026-03-01 23:15:45'),
(8, 39, '2026-03-01 23:15:39'),
(8, 40, '2026-03-01 23:03:58'),
(8, 41, '2026-03-01 23:16:01'),
(8, 42, '2026-03-02 01:07:26'),
(12, 40, '2026-03-01 23:47:45'),
(12, 41, '2026-03-01 23:47:38'),
(12, 42, '2026-03-02 00:24:25');

-- --------------------------------------------------------

--
-- Structure de la table `post_likes`
--

CREATE TABLE `post_likes` (
  `user_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `post_likes`
--

INSERT INTO `post_likes` (`user_id`, `post_id`, `created_at`) VALUES
(8, 7, '2026-02-22 12:39:51'),
(8, 9, '2026-02-23 11:56:18'),
(8, 30, '2026-02-23 20:56:17'),
(8, 50, '2026-04-05 20:40:14'),
(8, 57, '2026-04-07 09:30:31'),
(11, 57, '2026-04-07 09:31:50'),
(12, 30, '2026-02-23 20:56:46'),
(12, 57, '2026-04-07 09:34:57'),
(12, 58, '2026-04-07 09:35:29'),
(51, 9, '2026-02-23 12:00:27');

-- --------------------------------------------------------

--
-- Structure de la table `progress`
--

CREATE TABLE `progress` (
  `progress_id` bigint(20) NOT NULL,
  `hobby_id` bigint(20) NOT NULL,
  `hours_spent` double DEFAULT 0,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `progress`
--

INSERT INTO `progress` (`progress_id`, `hobby_id`, `hours_spent`, `notes`) VALUES
(1, 1, 150.5, 'Completed advanced lighting course'),
(2, 2, 200, 'Achieved intermediate level certification'),
(3, 3, 180, 'Built 5 complete projects'),
(4, 4, 120.5, 'Mastered Adobe Creative Suite'),
(5, 5, 95, 'Grew followers by 500%'),
(6, 6, 35, 'Lyoum t3alemt devops'),
(7, 7, 0, 'Started tracking'),
(9, 9, 2, 't3alemt el dribble'),
(10, 10, 1, 'Chocolate Ships'),
(12, 14, 2, '15 fev : first steps'),
(13, 15, 2, ''),
(15, 18, 6, 'bfbjr'),
(16, 19, 0, 'Started tracking'),
(17, 20, 2, 'LES BASICS DU FOOT'),
(20, 23, 0, 'Started tracking');

-- --------------------------------------------------------

--
-- Structure de la table `shared_songs`
--

CREATE TABLE `shared_songs` (
  `shared_song_id` bigint(20) NOT NULL,
  `post_id` bigint(20) NOT NULL,
  `spotify_track_id` varchar(500) DEFAULT NULL,
  `spotify_song_title` varchar(500) DEFAULT NULL,
  `spotify_artist` varchar(500) DEFAULT NULL,
  `shared_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `spotify_user_tokens`
--

CREATE TABLE `spotify_user_tokens` (
  `user_id` bigint(20) NOT NULL,
  `access_token` varchar(500) NOT NULL,
  `refresh_token` varchar(500) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stories`
--

CREATE TABLE `stories` (
  `story_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 24 hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `stories`
--

INSERT INTO `stories` (`story_id`, `user_id`, `caption`, `image_url`, `created_at`, `expires_at`) VALUES
(6, 8, NULL, '8_story_1772405521278.png', '2026-03-01 21:52:01', '2026-03-02 21:52:01'),
(7, 12, NULL, '12_story_1772417962086.png', '2026-03-02 01:19:22', '2026-03-03 01:19:22'),
(8, 8, '', '/uploads/stories/8_Capture-d-ecran-2026-04-06-a-11-45-25_69dbd9b586d5f3.70496717.png', '2026-04-12 16:43:17', '2026-04-13 16:43:17'),
(9, 8, 'hybdrid', '', '2026-04-12 16:43:39', '2026-04-13 16:43:39');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `google_id` varchar(50) DEFAULT NULL,
  `auth_provider` varchar(20) DEFAULT 'local',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `is_two_factor_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled_at` datetime DEFAULT NULL,
  `two_factor_backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`two_factor_backup_codes`)),
  `digest_opted_in` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`user_id`, `username`, `full_name`, `email`, `password`, `profile_picture`, `bio`, `location`, `is_online`, `created_at`, `last_login`, `google_id`, `auth_provider`, `is_banned`, `is_two_factor_enabled`, `two_factor_secret`, `two_factor_enabled_at`, `two_factor_backup_codes`, `digest_opted_in`) VALUES
(0, 'chahine', 'Chahine Admin', 'chahine@ghrami.tn', '$2a$12$8DyT3LJQewW0m6EU94dEKeQYdZAafj2rboJtmAtRFpBFWrIC2u6K2', '', 'Administrateur système', 'Tunis', 1, '2026-01-26 17:32:35', '2026-04-13 09:08:52', NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(1, 'amine_ben_ali', 'Amine Ben Ali', 'amine@ghrami.tn', 'password123', NULL, 'Passionné par le développement personnel et la lecture', 'Tunis, Tunisie', 1, '2026-01-26 18:21:03', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(2, 'salma_trabelsi', 'Salma Trabelsi', 'salma@ghrami.tn', 'password123', NULL, 'Entrepreneur social et mentor pour les jeunes', 'Sfax, Tunisie', 0, '2026-01-26 18:21:03', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(3, 'youssef_chaouch', 'Youssef Chaouch', 'youssef@ghrami.tn', 'password123', NULL, 'Amateur de randonnée et photographie', 'Sousse, Tunisie', 1, '2026-01-26 18:21:03', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(4, 'lina_gharbi', 'Lina Gharbi', 'lina@ghrami.tn', 'password123', NULL, 'Artiste peintre et passionnée de calligraphie arabe', 'La Marsa, Tunisie', 0, '2026-01-26 18:21:03', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(5, 'mehdi_jebali', 'Mehdi Jebali', 'mehdi@ghrami.tn', 'password123', NULL, 'Coach sportif et nutritionniste', 'Bizerte, Tunisie', 1, '2026-01-26 18:21:03', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(8, 'anasBiggie', 'ANAS BIGGIE', 'anas@ghrami.tn', '$2a$12$ThDEhWRKuC4sxga6jPzJX.YQPBULUymrkMBvyiKMMK.WAsccpIZR2', '8_1770192277278.png', 'Waa', 'hay zouhour', 1, '2026-01-26 18:06:50', '2026-05-03 16:34:11', NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(10, 'roua', 'roue hammemi', 'roua@ghrami.tn', '$2a$12$ry5aPTSulQU5.TOuP7n5kOjD/VoFcAfJj2m82rUHA9SBv9NSnLa.a', '', 'aaaaa', 'sidi hsine', 0, '2026-01-28 17:18:14', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(11, 'nourhen2004', 'Nourhen Dheker', 'nourhen@ghrami.tn', '$2a$12$09T6NioWegcjGIHv5gdemOQ1ixQQi11sXjwk4DjuoCu51JPg4K3zW', '11_1770039241022.png', 'violonist', 'tunis', 1, '2026-02-02 12:32:06', '2026-04-20 18:02:32', NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(12, 'hamza_laz3er', 'Hamza Mnajja', 'hamza@ghrami.tn', '$2a$12$7CM4rE4eWmEG0OcJ6CJZoOjLpILNeaAseKkmiLqz2.OXBfy2PhwoO', '12_1770714864510.png', 'Nheb el mekla', 'Yssminet', 1, '2026-02-10 08:12:55', '2026-04-07 09:34:51', NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(14, 'astroNourhen', 'Nourhen Dhaker', 'nourhendhaker25@gmail.com', '$2a$12$7mU2tZhIEpNoWusPWg1sIOhrv/NYRSA8SJ16.4m/LyH2umuLDsDyW', '', 'Astro Burger', 'Ariana', 0, '2026-02-11 18:19:31', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(15, 'aymen_bavari', 'Aymen Le fils de Aziza', 'aymen.benaziza@icloud.com', '$2a$12$eAGbUjEQiycAxh0dhbuL2ubibczH9bnbMj/ZDJvkfyxqCMgSy9sRS', '15_1770903664598.jpeg', 'Nheb el denya wel mdina', 'Mdina Aarbi', 0, '2026-02-12 12:38:38', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(45, 'anasEsprit', 'Med Khelifi', 'dgxbigi@gmail.com', '$2a$12$/1xwRjyatb/thADazUxqNOBrBZKFjfHa86H7l563GYiTCrLUentIu', '', 'naturelle', 'Hay zouhour', 0, '2026-02-15 12:44:17', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(46, 'ptit_fille', 'roue hamemi', 'roue.hamemi@esprit.tn', '$2a$12$JES25U3iho1dhBEiHaGTt.Lz6SVRMYpu3UgIHC8mqr2HYGQ15rwry', '', 'Jaime le fas3a et le 9oumen ma5er', 'Sidi hsine', 1, '2026-02-15 13:18:34', '2026-04-05 19:35:33', '100812944781398690886', 'google', 0, 0, NULL, NULL, NULL, 1),
(51, 'chahine_aouledamor', 'Chahine Aouled Amor', 'chahineaouledamor721@gmail.com', '$2a$12$yUWDER9cKsWonsXA9lSpYOR3kT1X0RKlnSwrW3Ho86mg3n6bEyNHC', '51_1771250440706.jpg', 'jaime le football', 'Ben Arous', 0, '2026-02-16 12:58:19', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(53, 'nour_medini', 'Nour Medini', 'medini.nour@esprit.tn', '$2a$12$ict7JRoDgt7f2rnpgavVL.ggr8/.95OlNj3P1SSfSXziJIz1c6rFK', '', '', 'Tunis', 0, '2026-02-17 09:40:44', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(56, 'aaaaaaa', 'aaaaaaaaaaa', 'chahi@gmai.co', '$2a$12$YIvkp1N.1GeEde6pC5dhtuEPTMQH1PtSdbC.20CosX9K0wsOb5kGW', '', '', 'aaaaaaaa', 0, '2026-02-21 13:24:53', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(57, 'acgamer35ca', 'Chahine Aouled Amor', 'acgamer35ca@gmail.com', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIZPaUWzdch1i-ece0sNQyxVSoyT0WlkBnP-ba_b69meJGd4G8=s96-c', NULL, NULL, 0, '2026-02-21 13:33:03', NULL, '100949373375872281937', 'google', 0, 0, NULL, NULL, NULL, 1),
(61, 'roue', 'roue hamemi', 'roue12@ghrami.tn', 'ghrami123', NULL, NULL, 'manouba', 0, '2026-03-01 21:56:16', NULL, NULL, 'local', 0, 0, NULL, NULL, NULL, 1),
(62, 'rouehamemi7', 'Roue Hamemi', 'rouehamemi7@gmail.com', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJXjemsjI83Ke2hgaUCXXm-pNK5PGV97C1df9CQIfiWl-WmvLj8=s96-c', NULL, '', 1, '2026-04-07 09:38:02', '2026-04-07 09:38:02', '102978057669830673040', 'google', 0, 0, NULL, NULL, NULL, 1);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_earned_date` (`earned_date`),
  ADD KEY `idx_badges_user_id` (`user_id`);

--
-- Index pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `idx_class_id` (`class_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_price` (`price`);

--
-- Index pour la table `class_providers`
--
ALTER TABLE `class_providers`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_verified` (`is_verified`),
  ADD KEY `idx_rating` (`rating`);

--
-- Index pour la table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `idx_post_id` (`post_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `connections`
--
ALTER TABLE `connections`
  ADD PRIMARY KEY (`connection_id`),
  ADD KEY `idx_initiator_id` (`initiator_id`),
  ADD KEY `idx_receiver_id` (`receiver_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `digest_logs`
--
ALTER TABLE `digest_logs`
  ADD PRIMARY KEY (`digest_log_id`),
  ADD KEY `IDX_4405F0BDA76ED395` (`user_id`),
  ADD KEY `IDX_4405F0BD2A9A7F63` (`sent_at`);

--
-- Index pour la table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Index pour la table `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`friendship_id`),
  ADD UNIQUE KEY `unique_friendship` (`user1_id`,`user2_id`),
  ADD KEY `idx_user1` (`user1_id`),
  ADD KEY `idx_user2` (`user2_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_date` (`created_date`),
  ADD KEY `idx_friendships_user1_user2` (`user1_id`,`user2_id`),
  ADD KEY `idx_friendships_status` (`status`);

--
-- Index pour la table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD PRIMARY KEY (`hidden_id`),
  ADD UNIQUE KEY `UNIQ_HIDDEN_POSTS_USER_POST` (`user_id`,`post_id`),
  ADD KEY `IDX_HIDDEN_POSTS_USER` (`user_id`),
  ADD KEY `IDX_HIDDEN_POSTS_POST` (`post_id`);

--
-- Index pour la table `hobbies`
--
ALTER TABLE `hobbies`
  ADD PRIMARY KEY (`hobby_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`);

--
-- Index pour la table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`meeting_id`),
  ADD KEY `idx_connection_id` (`connection_id`),
  ADD KEY `idx_organizer_id` (`organizer_id`),
  ADD KEY `idx_scheduled_at` (`scheduled_at`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD KEY `idx_meeting_id` (`meeting_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Index pour la table `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`milestone_id`),
  ADD KEY `idx_hobby_id` (`hobby_id`),
  ADD KEY `idx_is_achieved` (`is_achieved`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `notifications_ibfk_2` (`related_user_id`);

--
-- Index pour la table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_spotify_track_id` (`spotify_track_id`),
  ADD KEY `idx_posts_hidden_until` (`hidden_until`);

--
-- Index pour la table `post_hidden_by_user`
--
ALTER TABLE `post_hidden_by_user`
  ADD PRIMARY KEY (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `idx_post_hidden_by_user_hidden_until` (`hidden_until`);

--
-- Index pour la table `post_hides`
--
ALTER TABLE `post_hides`
  ADD PRIMARY KEY (`user_id`,`post_id`);

--
-- Index pour la table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`user_id`,`post_id`),
  ADD KEY `post_likes_ibfk_2` (`post_id`);

--
-- Index pour la table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD KEY `idx_hobby_id` (`hobby_id`);

--
-- Index pour la table `shared_songs`
--
ALTER TABLE `shared_songs`
  ADD PRIMARY KEY (`shared_song_id`),
  ADD UNIQUE KEY `unique_post_song` (`post_id`,`spotify_track_id`),
  ADD KEY `idx_shared_songs_post` (`post_id`),
  ADD KEY `idx_shared_songs_track` (`spotify_track_id`);

--
-- Index pour la table `spotify_user_tokens`
--
ALTER TABLE `spotify_user_tokens`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_spotify_tokens_user` (`user_id`);

--
-- Index pour la table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`story_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_online` (`is_online`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `badges`
--
ALTER TABLE `badges`
  MODIFY `badge_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `class_providers`
--
ALTER TABLE `class_providers`
  MODIFY `provider_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT pour la table `digest_logs`
--
ALTER TABLE `digest_logs`
  MODIFY `digest_log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `friendship_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  MODIFY `hidden_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `hobbies`
--
ALTER TABLE `hobbies`
  MODIFY `hobby_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `milestones`
--
ALTER TABLE `milestones`
  MODIFY `milestone_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT pour la table `progress`
--
ALTER TABLE `progress`
  MODIFY `progress_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `shared_songs`
--
ALTER TABLE `shared_songs`
  MODIFY `shared_song_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `stories`
--
ALTER TABLE `stories`
  MODIFY `story_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `badges`
--
ALTER TABLE `badges`
  ADD CONSTRAINT `badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `class_providers` (`provider_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `class_providers`
--
ALTER TABLE `class_providers`
  ADD CONSTRAINT `class_providers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `connections`
--
ALTER TABLE `connections`
  ADD CONSTRAINT `connections_ibfk_1` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `connections_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `friendships_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friendships_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD CONSTRAINT `FK_HIDDEN_POSTS_POST` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_HIDDEN_POSTS_USER` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `hobbies`
--
ALTER TABLE `hobbies`
  ADD CONSTRAINT `hobbies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `meetings`
--
ALTER TABLE `meetings`
  ADD CONSTRAINT `meetings_ibfk_1` FOREIGN KEY (`connection_id`) REFERENCES `connections` (`connection_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meetings_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  ADD CONSTRAINT `meeting_participants_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`meeting_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `milestones`
--
ALTER TABLE `milestones`
  ADD CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`hobby_id`) REFERENCES `hobbies` (`hobby_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `post_hidden_by_user`
--
ALTER TABLE `post_hidden_by_user`
  ADD CONSTRAINT `post_hidden_by_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_hidden_by_user_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`hobby_id`) REFERENCES `hobbies` (`hobby_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `shared_songs`
--
ALTER TABLE `shared_songs`
  ADD CONSTRAINT `shared_songs_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `spotify_user_tokens`
--
ALTER TABLE `spotify_user_tokens`
  ADD CONSTRAINT `spotify_user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
