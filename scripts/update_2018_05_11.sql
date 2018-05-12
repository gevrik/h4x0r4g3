INSERT INTO `FileMod` (`name`, `description`) VALUES ('execution-boost', 'This mod can be used to boost the execution time of a file, down to a minimum of 1 second.');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES ('4', '2');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '12', '4'), (NULL, '13', '4');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '42', '4');

INSERT INTO `SystemRole` (`id`, `name`, `description`) VALUES (NULL, 'enemy', 'An enemy of the system, will be attacked by ICE.'), (NULL, 'owner', 'The owner of the system.'), (NULL, 'guest', 'A guest of the system.'), (NULL, 'friend', 'A friend of the system.'), (NULL, 'harvester', 'Is allowed to harvest miners.'), (NULL, 'architect', 'Is allowed to modify the system.');