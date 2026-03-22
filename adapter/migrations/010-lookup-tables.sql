-- Lookup tables for PM tool configuration (people shortcuts, labels, schedule shortcuts)
CREATE TABLE `pm_lookups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL COMMENT 'Category: people, labels, schedule',
    `shortcut` VARCHAR(20) NOT NULL COMMENT 'Short code: PS, TT, RESIT',
    `value` VARCHAR(200) NOT NULL COMMENT 'Full value: Petr Simonides, this_week, ŘEŠIT',
    `description` VARCHAR(500) NULL COMMENT 'When/how to use this option',
    `sort_order` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_category_shortcut` (`category`, `shortcut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: People (initials → full name)
INSERT INTO `pm_lookups` (`category`, `shortcut`, `value`, `description`, `sort_order`) VALUES
    ('people', 'AJ', 'Aleš Janoušek', NULL, 1),
    ('people', 'JC', 'Jan Chmelíček', NULL, 2),
    ('people', 'KM', 'Karel Marek', NULL, 3),
    ('people', 'PS', 'Petr Simonides', NULL, 4),
    ('people', 'SP', 'Silvie Peková', NULL, 5),
    ('people', 'UI', 'Umělý Inteligent', 'AI service account', 6),
    ('people', 'ET', 'El Tester', 'Testovací účet', 7);

-- Seed: Labels (shortcut → PM label name + description)
INSERT INTO `pm_lookups` (`category`, `shortcut`, `value`, `description`, `sort_order`) VALUES
    ('labels', 'NOVY', 'NOVÝ', 'Nově založený úkol, zatím nezpracovaný', 1),
    ('labels', 'RESIT', 'ŘEŠIT', 'Úkol k aktivnímu řešení', 2),
    ('labels', 'NERESIT', 'NEŘEŠIT', 'Odložený úkol, neřešit teď', 3),
    ('labels', 'PREPLANOVAT', 'PŘEPLÁNOVAT', 'Přesunout na jiný termín', 4),
    ('labels', 'ZKONTROLOVAT', 'ZKONTROLOVAT', 'Čeká na kontrolu/review', 5),
    ('labels', 'KONZULTOVAT', 'KONZULTOVAT', 'Vyžaduje konzultaci s někým', 6),
    ('labels', 'SCHUZKA', 'SCHŮZKA', 'Schůzka — vyžaduje termín a účastníky', 7),
    ('labels', 'UZAVREN', 'UZAVŘEN', 'Dokončený/uzavřený úkol', 8),
    ('labels', 'DUPLICITNI', 'DUPLICITNÍ', 'Duplicitní úkol — řeší se jinde', 9),
    ('labels', 'STORNOVAN', 'STORNOVÁN', 'Zrušený úkol', 10);

-- Seed: Schedule shortcuts
INSERT INTO `pm_lookups` (`category`, `shortcut`, `value`, `description`, `sort_order`) VALUES
    ('schedule', 'TT', 'this_week', 'Tento týden (pondělí–pátek)', 1),
    ('schedule', 'PT', 'next_week', 'Příští týden', 2),
    ('schedule', 'PPT', 'after_next_week', 'Přespříští týden', 3),
    ('schedule', 'DNES', 'today', 'Dnes', 4),
    ('schedule', 'ZITRA', 'tomorrow', 'Zítra', 5),
    ('schedule', 'POZITRI', 'day_after_tomorrow', 'Pozítří', 6);
