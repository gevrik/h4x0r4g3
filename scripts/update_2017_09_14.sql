INSERT INTO `MissionArchetype` (`id`, `name`, `description`) VALUES (NULL, 'steal-file', 'Your Johnson needs you to steal a file from the target system.'), (NULL, 'upload-file', 'Your Johnson needs you to upload a file to the target system.'), (NULL, 'plant-backdoor', 'Your Johnson needs you to plant a backdoor in the target system.'), (NULL, 'delete-file', 'Your Johnson needs you to delete a file in the target system.');

UPDATE `MissionArchetype` SET `subtype` = '2' WHERE `MissionArchetype`.`id` = 1;
UPDATE `MissionArchetype` SET `subtype` = '2' WHERE `MissionArchetype`.`id` = 2;
UPDATE `MissionArchetype` SET `subtype` = '2' WHERE `MissionArchetype`.`id` = 3;
UPDATE `MissionArchetype` SET `subtype` = '2' WHERE `MissionArchetype`.`id` = 4;

UPDATE `Geocoord` SET `zone`="global" WHERE `zone` IS NULL;

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'proxifier', 'This program can be used to lower your security rating.', '1', '1', '2', '10', '0', '1', '0', '1');

INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES
  (73, 2, 32),
  (74, 4, 32);

INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES
  (32, 1);

INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('32', '4'), ('32', '9'), ('32', '1');