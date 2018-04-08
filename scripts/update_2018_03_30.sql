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

INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'leadership', 'The ability to lead others - important for parties and groups', '0', '2018-03-30 00:00:00', '0');

INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'beam-weapons', 'Knowledge of beam-weapons', '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'blade-weapons', 'Knowledge of blade-weapons.', '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'climbing', 'Knowledge of climbing.', '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'control', NULL, '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'deception', NULL, '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'demolitions', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'disguise', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'flight', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'fray', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'free-fall', NULL, '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'free-running', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'gunnery', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'hardware', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'impersonation', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'infiltration', NULL, '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'infosec', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'interfacing', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'intimidation', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'investigation', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'kinesics', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'kinetic-weapons', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'medicine', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'navigation', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'palming', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'perception', NULL, '0', '2018-03-31 00:00:00', '0');
INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES (NULL, 'persuasion', NULL, '0', '2018-03-31 00:00:00', '0'), (NULL, 'pilot', NULL, '0', '2018-03-31 00:00:00', '0');

INSERT INTO `Npc` (`id`, `name`, `description`, `baseEeg`, `baseSnippets`, `baseCredits`, `level`, `baseBlade`, `baseBlaster`, `baseShield`, `baseDetection`, `baseStealth`, `baseSlots`, `aggressive`, `roaming`, `type`, `stealthing`, `social`) VALUES (NULL, 'story_npc', 'a story npc', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '4', '0', '0');

# RUN PHP PUBLIC/INDEX.PHP RESET-SKILLS AFTER THIS

UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 1; UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 2; UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 3; UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 4; UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 5; UPDATE `Faction` SET `openRecruitment` = '1' WHERE `Faction`.`id` = 6;

UPDATE System SET integrity = 100 WHERE id > 0;

INSERT INTO `GroupRole` (`id`, `name`, `description`) VALUES (NULL, 'combat officer', 'This role allows combat-related actions, like claiming enemy systems.');

INSERT INTO `GameOption` (`name`, `description`, `defaultStatus`, `defaultValue`) VALUES ('newbiechat', 'show newbie chat', 1, '');
INSERT INTO `GameOption` (`name`, `description`, `defaultStatus`, `defaultValue`) VALUES ('tradechat', 'show trade chat', 1, 'NULL');
INSERT INTO `GameOption` (`name`, `description`, `defaultStatus`, `defaultValue`) VALUES ('systemchat', 'show system chat', 1, 'NULL');
INSERT INTO `GameOption` (`name`, `description`, `defaultStatus`, `defaultValue`) VALUES ('globalchat', 'show global chat', 1, 'NULL');

UPDATE `FileType` SET `executionTime` = '120' WHERE `FileType`.`id` = 13;
UPDATE `FileType` SET `executionTime` = '60' WHERE `FileType`.`id` = 12;
UPDATE `FileType` SET `executionTime` = '10' WHERE `FileType`.`id` = 28;
UPDATE `FileType` SET `executionTime` = '90' WHERE `FileType`.`id` = 42;

INSERT INTO `FileType` (`name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES ('omen', 'Can be used in monitoring nodes to intercept hacking attempts', 1, 1, 2, 1, 0, 0, 0, 1);
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '3', '43'), (NULL, '4', '43'), (NULL, '8', '43');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('43', '10');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('43', '1'), ('43', '3'), ('43', '8'), ('43', '9');

INSERT INTO `MissionArchetype` (`name`, `description`, `subtype`) VALUES ('clean-system', 'Your Johnson needs you to clear a system from virii.', 1);

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`, `executionTime`, `fullblock`, `blocking`, `stealthing`, `needRecipe`) VALUES (NULL, 'logic-bomb', 'Can be used to damage all enemy entities in a node.', '1', '1', '1', '1', '0', '0', '0', '1');
INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES (NULL, '13', '44'), (NULL, '15', '44');
INSERT INTO `filetype_filecategory` (`filetype_id`, `filecategory_id`) VALUES ('44', '14');
INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES ('44', '1'), ('44', '13');

INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'aquanaut', 'Aquanauts are environmentally adapted for underwater activities.', '2'), (NULL, 'ariel', 'The Ariel morph is designed for people who wish to survive by hunting.', '2');
INSERT INTO `Morph` (`id`, `name`, `description`, `morphCategory_id`) VALUES (NULL, 'ayah', 'The ayah pod morph is designed to fulfill nurse and caretaker functions.', '3'), (NULL, 'basic-pod', 'Basic pods are essentially lower-cost pod versions of a splicer morph.', '3');
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('biocore', 'The biocore is a synthmorph with a biological brain.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('blackbird', 'When they''re seen at all, these morphs resemble a matte gray neo-corvid with many odd, shard angles.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('bouncer', 'Bouncers are humans genetically adapted for zero-g and microgravity environments.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('brute', 'This morph is specifically designed to be large, strong and phyiscally intimidating.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('cetus', 'The cetus was designed for deep sea activity and is capable of operating under extreme pressure and cold.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('chickcharnie', 'The Fortean version of the chickcharnie is best described as a humanoid owl.', 3);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('cloud-skate', 'The cloud skate is another biomorph that pushes the edge of what is possible.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('cloud-skimmer', 'This streamlined synthmorph is designed for exploring the atmosphere of gas giants.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('courier', 'This synthmorph was specifically designed to fly between the many moons and habitats in the Saturnian system.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('crasher', 'An enhanced version of ruster morphs, crashers are rugged and durable designs capable of weathing a range of harsh environments.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('critter', 'Critters are pods either genetically blended from multiple animal species or simply biosculpted to appear as such.', 3);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('daitya', 'This huge, vaguely anthropomorphic synthmorph is designed for large construction projects and similar heavy industrial uses.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('digger', 'Diggers are worker pods customized for archeological work.', 3);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('dragonfly', 'The dragonfly robotic morph takes the shape of a meter-long flexible shell with multiple wings and manipulator arms.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('dvergr', 'Dvergr are biomorphs designed for comfortable operation in high-gravity environments.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('faust', 'Developed in secret by a small group of unaffiliated async genehackers, this morph is not a publicly known model.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('fenrir', 'The fenrir is one of the most imposing combat morphs ever developed.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('fighting-kite', 'Combat version of the kite morph, more durable and better armorerd.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('flexbot', 'Desgined for multipurpose functions, flexbots can transform their shells to suit a range of situations and tasks.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('flying-squid', 'This morph''s streamlined form resembles a stylized squid which can move swiftly in both water and air.', 3);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('fury', 'Furies are combat morphs. These transgenic human upgrades feature genetics tailored for endurance, strength and reflexes.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('gargoyle', 'Gargoyles are an anthroform synthetic morph designed as a mobile sensor unit.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('ghost', 'Ghosts are partially designed for combat applications, but their primary focus is stealth and infiltration.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('grey', 'Originally created as part of a fad based on 20th-century images of aliens, the grey morph soon became popular with eccentric scientists and engineers.', 2);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('griefer', 'Based on the case synthmorph design, griefers are used by vandals, terrorists, and dedicated trolls to harass enemies and antagonize the masses.', 1);
INSERT INTO `Morph` (`name`, `description`, `morphCategory_id`) VALUES ('guard', 'This morph''s enhanced senses allow it to more easily detect any threats to the person the user is guarding.', 1);