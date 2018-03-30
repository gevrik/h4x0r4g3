INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'spider-spawner', 'This program can be installed in a node and will spawn Spider ICE that protect secured connections from unauthorized access.', '1', '1', '3', '1', '0', '0', '1', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '41'), (NULL, '4', '41'), (NULL, '8', '41');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('41', '13');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('41', '1'), ('41', '3'), ('41', '8'), ('41', '9');

INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'spider_ice', 'Spider ICE will protect the connections in this node from unauthorized access.', '2', '0', '0', '0', '0', '0', '0', '50', '50', '1', '0', '0', '2', '1', '0');

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'lockpick', 'This program can be used to bypass codegates.', '1', '1', '1', '10', '1', '1', '0', '0');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '2', '42'), (NULL, '4', '42');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('42', '7');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('42', '1'), ('42', '4'), ('42', '9');

INSERT INTO `MorphCategory` (`id`, `name`, `description`) VALUES (NULL, 'synthetic', 'The morphs are completely artificial/robotic.');
INSERT INTO `MorphCategory` (`id`, `name`, `description`) VALUES (NULL, 'biomorph', 'Biomorphs are fully biological sleeves (usually equipped with implants), birthed naturally or in an exowomb.');
INSERT INTO `MorphCategory` (`id`, `name`, `description`) VALUES (NULL, 'pod', 'Pods (from \"pod people\") are vat-grown, biological bodies with extremely undeveloped brains that are augmented with an implanted computer and cybernetic systems.');

INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'case', 'Cases are extremely cheap, mass-produced robotic shells intended to provide an affordable remorphing option.', '1');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'synth', 'Synths are antropomorphic robotic shells (androids and gynoids). Typically used for menial labor jobs.', '1');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'arachnoid', 'Arachnoid robotic shells are 1-meter in length, segmented into two parts, with a smaller head like a spider or termite.', '1');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'flat', 'Flats are baseline unmodified humans, born with all of the natural defects, hereditary diseases and other genetic mutations that evolution so lovingly supplies', '2');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'splicer', 'Splicers are genefixed humans with an optimized genome.', '2');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'exalt', 'Exalt morphs are genetically enhanced humans, designed to emphasize specific traits.', '2');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'pleasure', 'Pleasure pods are exactly what they seem - faux humans designed purely for intimate entertainment purposes.', '3'), (NULL, 'worker', 'Part exalt human, part machine, these basic pods are virtually indistinguishable from humans.', '3');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'novacrab', 'Novacrabs are a pod design bioengineered from coconut crab and spider crab stock and grown to a human size.', '3');

INSERT INTO `NodeType` (`id`, `name`, `shortName`, `description`, `cost`) VALUES (NULL, 'egocasting', 'ego', 'This node allows to transfer your ego into a morph', '1000');
