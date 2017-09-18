INSERT INTO `FileCategory` (`id`, `name`, `description`) VALUES (NULL, 'combat', 'Programs that are used during combat');
INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'kicker', 'Can be used during combat to try to stun your opponent', '1', '1', '1', '1', '0', '0', '0', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '4', '33'), (NULL, '17', '33');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('33', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('33', '1'), ('33', '9'), ('33', '12');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'breakout', 'Can be used in combat to remove negative effects', '1', '1', '1', '1', '0', '0', '0', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '11', '34'), (NULL, '19', '34');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('34', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('34', '1'), ('34', '10'), ('34', '14');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'smokescreen', 'Can be used in combat to disengage from combat and re-enter stealth', '1', '1', '1', '1', '0', '0', '0', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '10', '35'), (NULL, '18', '35');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('35', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('35', '1'), ('35', '5'), ('35', '13');