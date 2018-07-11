INSERT INTO `FileMod` (`name`, `description`) VALUES ('execution-boost', 'This mod can be used to boost the execution time of a file, down to a minimum of 1 second.');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES ('4', '2');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '12', '4'), (NULL, '13', '4');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '42', '4');

INSERT INTO `SystemRole` (`id`, `name`, `description`) VALUES (NULL, 'enemy', 'An enemy of the system, will be attacked by ICE.'), (NULL, 'owner', 'The owner of the system.'), (NULL, 'guest', 'A guest of the system.'), (NULL, 'friend', 'A friend of the system.'), (NULL, 'harvester', 'Is allowed to harvest miners.'), (NULL, 'architect', 'Is allowed to modify the system.');

INSERT INTO `FileType` (`name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES ('padlock', 'Can be used on a codegate to strengthen its security', 1, 1, 1, 1, 0, 0, 0, 1);
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES (45, 3);
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES (45, 1);
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES (45, 3);
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES (45, 5);
INSERT INTO `FileTypeSkill` (`skill_id`, `fileType_id`) VALUES (3, 45);
INSERT INTO `FileTypeSkill` (`skill_id`, `fileType_id`) VALUES (10, 45);

INSERT INTO `FileMod` (`name`, `description`) VALUES ('obfuscation', 'Makes program execution harder to detect');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES (5, 4);
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES (5, 5);
INSERT INTO `FileTypeMod` (`fileType_id`, `fileMod_id`) VALUES (12, 5);
INSERT INTO `FileTypeMod` (`fileType_id`, `fileMod_id`) VALUES (13, 5);
INSERT INTO `FileTypeMod` (`fileType_id`, `fileMod_id`) VALUES (42, 5);
INSERT INTO `FileTypeMod` (`fileType_id`, `fileMod_id`) VALUES (28, 5);

INSERT INTO `Npc` (`name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`, `baseFray`) VALUES ('scanner_program', 'This scanner program has been launched to scan the system architecture', 0, 0, 0, 0, 0, 0, 0, 0, 10, 0, 0, 1, 5, 1, 0, 10);
UPDATE `FileType` t SET t.`executionTime` = 10, t.`fullblock` = 1 WHERE t.`id` = 11;

INSERT INTO `FileMod` (`name`, `description`) VALUES ('cache-memory', 'Allows programs to story information, making them smarter');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES (6, 6);
INSERT INTO `FileTypeMod` (`fileType_id`, `fileMod_id`) VALUES (11, 6);