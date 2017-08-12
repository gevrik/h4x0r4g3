INSERT INTO `FileCategory` (`id`, `name`, `description`) VALUES
  (1, 'utility', 'small programs that (should) make life easier'),
  (2, 'miner', 'Programs that \"mine\" resources'),
  (3, 'defense', 'Programs that defend systems or other programs'),
  (4, 'equipment', 'Programs that can be used by your avatar'),
  (5, 'forensics', 'Programs used to gather information'),
  (6, 'intrusion', 'Programs that are used to break into systems'),
  (7, 'bypass', 'Programs used to bypass security measures'),
  (8, 'malware', 'Programs that are installed in systems to exploit them'),
  (9, 'tracer', 'Programs used to trace signals to their origin'),
  (10, 'node--upgrade', 'Programs that can be installed in nodes to give them beneficial effects'),
  (11, 'exotic', 'Unknown programs - probably created by some artificial intelligence'),
  (12, 'stealth', 'Programs used to hide your signal and avatar');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES
  (27, 'researcher', 'used in memory nodes to research unknown file-type recipes', 1, 1, 5, 1, 0, 0, 0, 0);

INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES
  (60, 6, 27),
  (61, 8, 27),
  (62, 11, 27);

INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES
  (2, 1),
  (3, 2),
  (5, 3),
  (6, 2),
  (7, 4),
  (8, 4),
  (9, 4),
  (10, 4),
  (11, 5),
  (12, 5),
  (13, 6),
  (14, 6),
  (15, 7),
  (16, 1),
  (17, 8),
  (18, 3),
  (19, 9),
  (20, 12),
  (21, 12),
  (22, 1),
  (23, 1),
  (24, 8),
  (25, 3),
  (26, 11),
  (27, 10);

INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES
  (27, 1),
  (27, 6),
  (27, 8),
  (27, 10);