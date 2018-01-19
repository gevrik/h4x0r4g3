INSERT INTO `FileMod` (`id`, `name`, `description`) VALUES (NULL, 'titankiller', 'Can be used on spawners to make the spawned entity more effective in combat against TITAN-based entities.');
INSERT INTO `filemod_filepart` (`filemod_id`, `filepart_id`) VALUES ('3', '4'), ('3', '10');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '31', '3');
INSERT INTO `FileTypeMod` (`id`, `fileType_id`, `fileMod_id`) VALUES (NULL, '3', '1'), (NULL, '6', '1');