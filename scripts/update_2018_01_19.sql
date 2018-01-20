INSERT INTO `FileMod` (`id`, `name`, `description`) VALUES (NULL, 'titankiller', 'Can be used on spawners to make the spawned entity more effective in combat against TITAN-based entities.');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES ('3', '4'), ('3', '10');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '31', '3');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '3', '1'), (NULL, '6', '1');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'crowbar', 'used to force open codegates, very noisy', '1', '1', '1', '10', '1', '1', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '41'), (NULL, '4', '41');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('41', '7');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('41', '1'), ('41', '4'), ('41', '9');

INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'story_npc', 'a story npc', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '4', '0', '0');