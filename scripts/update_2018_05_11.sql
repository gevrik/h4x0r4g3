INSERT INTO `FileMod` (`name`, `description`) VALUES ('execution-boost', 'This mod can be used to boost the execution time of a file, down to a minimum of 1 second.');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES ('4', '2');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '12', '4'), (NULL, '13', '4');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '42', '4');