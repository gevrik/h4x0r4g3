INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'netwatch_investigator', 'An investigator entity that has been dispatched by the NetWatch faction to neutralize a cyber-criminal.', '50', '0', '0', '0', '30', '30', '30', '50', '50', '0', '0', '3', '3', '0', '1'), (NULL, 'netwatch_agent', 'An agent entity that has been dispatched by the NetWatch faction to neutralize a cyber-criminal.', '100', '0', '0', '0', '50', '50', '50', '75', '75', '0', '0', '3', '3', '0', '1');

UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 2;
UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 3;
UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 4;
UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 5;
UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 6;
UPDATE `Npc` SET `level` = '0' WHERE `Npc`.`id` = 7;

UPDATE `Npc` SET `aggressive` = '1' WHERE `Npc`.`id` = 3;
UPDATE `Npc` SET `roaming` = '1' WHERE `Npc`.`id` = 3;