-- Minimal but intentionally inconsistent schema for pipeline tests.

CREATE TABLE `profiles` (
  `id` INT NOT NULL,
  `user_ref` INT NULL,
  `type` VARCHAR(32) NOT NULL,
  `is_active` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `users` (
  `id` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `members` (
  `id` INT NOT NULL,
  `profile_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`)
);

CREATE TABLE `profile_tag` (
  `profile_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`profile_id`, `tag_id`)
);

CREATE TABLE `views` (
  `id` INT NOT NULL,
  `profile_id` INT NOT NULL,
  PRIMARY KEY (`id`)
);
