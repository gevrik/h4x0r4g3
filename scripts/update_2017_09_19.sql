INSERT INTO `Effect` (`id`, `name`, `description`) VALUES (NULL, 'stunned', 'Stunned entities are not able to do anything during their combat action');
INSERT INTO `Effect` (`id`, `name`, `description`) VALUES (NULL, 'damage-over-time', 'Damages the entity every time they start their combat action');

UPDATE `Effect` SET `expireTimer` = '2', `dimishTimer` = '4', `diminishValue` = '50', `immuneTimer` = '8' WHERE `Effect`.`id` = 1;
UPDATE `Effect` SET `expireTimer` = '8', `dimishTimer` = '16', `diminishValue` = '50', `immuneTimer` = '32' WHERE `Effect`.`id` = 2;

UPDATE `Effect` SET `defaultRating` = '1' WHERE `Effect`.`id` = 2;

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'puncher', 'Can be used in combat to deal additional damage', '1', '1', '1', '1', '0', '0', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '38');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('38', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('38', '1'), ('38', '4');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'stimulant', 'Can be used in combat to heal yourself for a small amount', '1', '1', '1', '1', '0', '0', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '3', '39');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('39', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('39', '1'), ('39', '3');

