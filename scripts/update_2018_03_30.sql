INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'spider-spawner', 'This program can be installed in a node and will spawn Spider ICE that protect secured connections from unauthorized access.', '1', '1', '3', '1', '0', '0', '1', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '41'), (NULL, '4', '41'), (NULL, '8', '41');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('41', '13');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('41', '1'), ('41', '3'), ('41', '8'), ('41', '9');

INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'spider_ice', 'Spider ICE will protect the connections in this node from unauthorized access.', '2', '0', '0', '0', '0', '0', '0', '50', '50', '1', '0', '0', '2', '1', '0');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'lockpick', 'This program can be used to bypass codegates.', '1', '1', '1', '10', '1', '1', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '42'), (NULL, '4', '42');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('42', '7');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('42', '1'), ('42', '4'), ('42', '9');