INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'medkit', 'This program can be used out of combat to heal-up your EEG.', '1', '1', '1', '5', '0', '1', '0', '1');

INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES
  (68, 11, 30),
  (69, 7, 30);

INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES
  (30, 1);

INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES
  (30, 1),
  (30, 7),
  (30, 10);

INSERT INTO `FileCategory` (`id`, `name`, `description`) VALUES (NULL, 'spawner', 'Files that are placed in nodes and spawn entities.');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'guard-spawner', 'This file can be executed in a node and will spawn guardian ICE.', '1', '1', '4', '1', '0', '0', '0', '1');

INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '3', '31'), (NULL, '7', '31'), (NULL, '4', '31');

INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('31', '13');

INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('31', '3'), ('31', '7'), ('31', '9');

INSERT INTO `MilkrunAivatar` (`id`, `name`, `description`, `baseEeg`, `baseAttack`, `baseArmor`, `specials`) VALUES (NULL, 'scrounger', 'A very simple Milkrun Aivatar that has limited expansion capabilities.', '20', '1', '0', NULL);