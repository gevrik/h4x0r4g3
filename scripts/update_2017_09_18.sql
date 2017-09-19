INSERT INTO `GameOption` (`id`, `name`, `description`, `defaultStatus`) VALUES (NULL, 'bgopacity', 'Set the background opacity. Values between 0.1 and 0.9.', '0');
UPDATE `GameOption` SET `defaultValue` = '0.6' WHERE `GameOption`.`id` = 4;

# ------------------------

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'venom', 'Can be used in combat to add a damage-over-time effect to your opponent', '1', '1', '1', '1', '0', '0', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '36'), (NULL, '17', '36');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('36', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('36', '1'), ('36', '4'), ('36', '12');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'antidote', 'Can be used in combat to remove a damage-over-time effect from yourself.', '1', '1', '1', '1', '0', '0', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '3', '37'), (NULL, '19', '37');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('37', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('37', '1'), ('37', '3'), ('37', '14');

UPDATE `Npc` SET `name` = 'murphy_virus' WHERE `Npc`.`id` = 1;
UPDATE `Npc` SET `name` = 'killer_virus' WHERE `Npc`.`id` = 2;
UPDATE `Npc` SET `name` = 'bouncer_ice' WHERE `Npc`.`id` = 3;
UPDATE `Npc` SET `name` = 'worker_program' WHERE `Npc`.`id` = 4;
UPDATE `Npc` SET `name` = 'sentinel_ice' WHERE `Npc`.`id` = 5;
UPDATE `Npc` SET `name` = 'debugger_program' WHERE `Npc`.`id` = 7;
UPDATE `Npc` SET `name` = 'wilderspace_intruder' WHERE `Npc`.`id` = 6;

INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'guardian_ice', 'This guardian ICE will protect the system from intruders.', '5', '0', '0', '0', '5', '0', '5', '5', '0', '2', '1', '0', '2', '0', '1');