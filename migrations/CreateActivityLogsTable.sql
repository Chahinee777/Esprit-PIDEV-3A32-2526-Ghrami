-- Migration: Add activity_logs table for AI-powered analytics
-- Created: 2026-04-14

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'hobby_logged, post_created, login, friendship_accepted, meeting_scheduled, etc',
  `description` VARCHAR(500) COMMENT 'Human-readable description of the event',
  `metadata` JSON COMMENT 'Flexible data structure for event-specific information',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_user_date (user_id, created_at),
  INDEX idx_event_type (event_type),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example queries to test:
-- SELECT COUNT(*) FROM activity_logs;
-- SELECT event_type, COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY event_type;
-- SELECT user_id, COUNT(*) as activities FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_id ORDER BY activities DESC LIMIT 10;
