UPDATE `nethak`.`FileType` SET `executionTime` = '1', `blocking` = '0' WHERE `FileType`.`id` = 14;
UPDATE `nethak`.`FileType` SET `executionTime` = '30' WHERE `FileType`.`id` = 13;

INSERT INTO `nethak`.`FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'passkey', 'This passkey allows access to the node that it has been created in', '0', '1', '0', '10', '1', '1', '0', '0');

UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 1;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 2;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 3;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 4;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 5;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 6;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 7;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 8;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 9;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 10;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 12;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 13;
UPDATE `nethak`.`FileCategory` SET `researchable` = '1' WHERE `FileCategory`.`id` = 14;

INSERT INTO `nethak`.`FileCategory` (`id`, `name`, `description`, `researchable`) VALUES (NULL, 'passkey', 'Passkeys grant access to nodes', '0');
INSERT INTO `nethak`.`FileCategory` (`id`, `name`, `description`, `researchable`) VALUES (NULL, 'text', 'Text files can hold textual information', '0');
INSERT INTO `nethak`.`filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('4', '16'), ('40', '15');