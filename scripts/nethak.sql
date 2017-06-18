-- phpMyAdmin SQL Dump
-- version 4.2.12deb2+deb8u2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 18, 2017 at 09:12 PM
-- Server version: 10.0.30-MariaDB-0+deb8u2
-- PHP Version: 5.6.30-0+deb8u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `nethak`
--

-- --------------------------------------------------------

--
-- Table structure for table `Connection`
--

CREATE TABLE IF NOT EXISTS `Connection` (
`id` int(11) NOT NULL,
  `sourceNode_id` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT '0',
  `level` int(11) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `targetNode_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctrine_migration_versions`
--

CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `File`
--

CREATE TABLE IF NOT EXISTS `File` (
`id` int(11) NOT NULL,
  `coder_id` int(11) DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `system_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `size` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `maxIntegrity` int(11) NOT NULL,
  `integrity` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `executable` int(11) DEFAULT '0',
  `running` int(11) DEFAULT '0',
  `mailMessage_id` int(11) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT '1',
  `slots` int(11) DEFAULT '0',
  `fileType_id` int(11) DEFAULT NULL,
  `node_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FilePart`
--

CREATE TABLE IF NOT EXISTS `FilePart` (
`id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `type` int(11) NOT NULL,
  `level` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `FilePart`
--

INSERT INTO `FilePart` (`id`, `name`, `description`, `type`, `level`) VALUES
(1, 'controller', 'a controller', 1, 1),
(2, 'frontend code', 'frontend code', 1, 1),
(3, 'whitehat code', 'whitehat code', 1, 1),
(4, 'blackhat code', 'blackhat code', 1, 1),
(5, 'crypto code', 'crypto code', 1, 1),
(6, 'database code', 'database code', 1, 1),
(7, 'electronics code', 'electronics code', 1, 1),
(8, 'forensics code', 'forensics code', 1, 1),
(9, 'network code', 'network code', 1, 1),
(10, 'reverse engineering code', 'reverse engineering code', 1, 1),
(11, 'social engineering code', 'social engineering code', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `FilePartInstance`
--

CREATE TABLE IF NOT EXISTS `FilePartInstance` (
`id` int(11) NOT NULL,
  `coder_id` int(11) DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL,
  `filePart_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `FilePartSkill`
--

CREATE TABLE IF NOT EXISTS `FilePartSkill` (
`id` int(11) NOT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `filePart_id` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `FilePartSkill`
--

INSERT INTO `FilePartSkill` (`id`, `skill_id`, `filePart_id`) VALUES
(1, 1, 1),
(2, 13, 2),
(3, 3, 3),
(4, 2, 4),
(5, 10, 5),
(6, 6, 6),
(7, 7, 7),
(8, 8, 8),
(9, 4, 9),
(10, 9, 11),
(11, 11, 10);

-- --------------------------------------------------------

--
-- Table structure for table `FileType`
--

CREATE TABLE IF NOT EXISTS `FileType` (
`id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `codable` int(11) DEFAULT '0',
  `executable` int(11) DEFAULT '0',
  `size` int(11) DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `FileType`
--

INSERT INTO `FileType` (`id`, `name`, `description`, `codable`, `executable`, `size`) VALUES
(1, 'directory', 'a directory', 0, 0, 0),
(2, 'chatclient', 'a chatclient', 1, 1, 1),
(3, 'dataminer', 'a dataminer', 1, 1, 1),
(4, 'text', 'a text file', 0, 0, 1),
(5, 'icmp-blocker', 'an icmp-blocker', 1, 1, 1),
(6, 'coinminer', 'a coinminer', 1, 1, 1),
(7, 'codeblade', 'a codeblade used for close-cyber-combat', 1, 1, 1),
(8, 'codeblaster', 'a codeblade used for ranged-cyber-combat', 1, 1, 1),
(9, 'codearmor', 'a piece of cyber-armor', 1, 1, 1),
(10, 'codeshield', 'a cyber-shield', 1, 1, 1),
(11, 'sysmapper', 'tries to map the current system', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `FileTypeSkill`
--

CREATE TABLE IF NOT EXISTS `FileTypeSkill` (
`id` int(11) NOT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `fileType_id` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `FileTypeSkill`
--

INSERT INTO `FileTypeSkill` (`id`, `skill_id`, `fileType_id`) VALUES
(1, 4, 3),
(2, 4, 2),
(3, 8, 3),
(4, 4, 5),
(5, 3, 5),
(6, 10, 6),
(7, 4, 6),
(8, 17, 7),
(9, 14, 7),
(10, 18, 8),
(11, 15, 8),
(12, 12, 9),
(13, 13, 9),
(14, 19, 10),
(15, 16, 10),
(16, 4, 11),
(17, 8, 11);

-- --------------------------------------------------------

--
-- Table structure for table `filetype_filepart`
--

CREATE TABLE IF NOT EXISTS `filetype_filepart` (
  `filetype_id` int(11) NOT NULL,
  `filepart_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `filetype_filepart`
--

INSERT INTO `filetype_filepart` (`filetype_id`, `filepart_id`) VALUES
(3, 1),
(3, 6),
(3, 8),
(5, 1),
(5, 3),
(5, 9),
(6, 1),
(6, 5),
(6, 9);

-- --------------------------------------------------------

--
-- Table structure for table `filetype_optionalfilepart`
--

CREATE TABLE IF NOT EXISTS `filetype_optionalfilepart` (
  `filetype_id` int(11) NOT NULL,
  `filepart_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `KnownNode`
--

CREATE TABLE IF NOT EXISTS `KnownNode` (
`id` int(11) NOT NULL,
  `node_id` int(11) DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MailMessage`
--

CREATE TABLE IF NOT EXISTS `MailMessage` (
`id` int(11) NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8_unicode_ci NOT NULL,
  `sentDateTime` datetime DEFAULT NULL,
  `readDateTime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Manpage`
--

CREATE TABLE IF NOT EXISTS `Manpage` (
`id` int(11) NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8_unicode_ci NOT NULL,
  `createdDateTime` datetime DEFAULT NULL,
  `updatedDateTime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Node`
--

CREATE TABLE IF NOT EXISTS `Node` (
`id` int(11) NOT NULL,
  `system_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` int(11) DEFAULT '0',
  `level` int(11) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `description` longtext COLLATE utf8_unicode_ci
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `Node`
--

INSERT INTO `Node` (`id`, `system_id`, `name`, `type`, `level`, `created`, `description`) VALUES
(1, 2, 'cpu', 6, 1, '2017-06-01 00:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Notification`
--

CREATE TABLE IF NOT EXISTS `Notification` (
`id` int(11) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `sentDateTime` datetime NOT NULL,
  `readDateTime` datetime DEFAULT NULL,
  `severity` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Profile`
--

CREATE TABLE IF NOT EXISTS `Profile` (
`id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `credits` int(11) NOT NULL,
  `snippets` int(11) NOT NULL,
  `currentNode_id` int(11) DEFAULT NULL,
  `skillPoints` int(11) DEFAULT '20',
  `homeNode_id` int(11) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `Profile`
--

INSERT INTO `Profile` (`id`, `user_id`, `credits`, `snippets`, `currentNode_id`, `skillPoints`, `homeNode_id`) VALUES
(3, 1, 999999, 999999, 1, 20, 1);

-- --------------------------------------------------------

--
-- Table structure for table `Role`
--

CREATE TABLE IF NOT EXISTS `Role` (
`id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `roleId` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `Role`
--

INSERT INTO `Role` (`id`, `parent_id`, `roleId`) VALUES
(1, NULL, 'guest'),
(2, NULL, 'user'),
(3, 2, 'premiumuser'),
(4, 3, 'moderator'),
(5, 4, 'admin'),
(6, 5, 'superadmin');

-- --------------------------------------------------------

--
-- Table structure for table `Skill`
--

CREATE TABLE IF NOT EXISTS `Skill` (
`id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci,
  `advanced` int(11) DEFAULT '0',
  `added` datetime NOT NULL,
  `level` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `Skill`
--

INSERT INTO `Skill` (`id`, `name`, `description`, `advanced`, `added`, `level`) VALUES
(1, 'coding', 'general coding skills', 0, '2017-06-01 00:00:00', 30),
(2, 'blackhat', 'how well the person knows malicious coding skills', 0, '2017-06-01 00:00:00', 10),
(3, 'whitehat', 'how well the person knows defensive coding skills', 0, '2017-06-01 00:00:00', 10),
(4, 'networking', 'how well the person knows networking subjects', 0, '2017-06-01 00:00:00', 0),
(5, 'computing', 'general computing skills', 0, '2017-06-01 00:00:00', 30),
(6, 'database', 'how well the person knows database coding and architecture', 0, '2017-06-01 00:00:00', 0),
(7, 'electronics', 'how well the person knows electronic hardware and its coding', 0, '2017-06-01 00:00:00', 0),
(8, 'forensics', 'how well the person knows how to gather information and data', 0, '2017-06-01 00:00:00', 0),
(9, 'social engineering', 'how well the person is in social engineering and relevant coding', 0, '2017-06-01 00:00:00', 0),
(10, 'cryptography', 'how well the person knows cryptography and its applications', 0, '2017-06-01 00:00:00', 0),
(11, 'reverse engineering', 'how well the person knows reverse engineering of files and systems', 0, '2017-06-01 00:00:00', 0),
(12, 'advanced networking', 'how well the person knows advanced networking coding and architecture', 1, '2017-06-01 00:00:00', 0),
(13, 'advanced coding', 'advanced coding techniques skill', 1, '2017-06-01 00:00:00', 0),
(14, 'blades', 'how well the person can use codeblades in cyberspace', 0, '2017-06-01 00:00:00', 0),
(15, 'blasters', 'how well the person can use codeblasters in cyberspace', 0, '2017-06-01 00:00:00', 0),
(16, 'shields', 'how well the person can use codeshields in cyberspace', 0, '2017-06-01 00:00:00', 0),
(17, 'bladecoding', 'how well the person can code blades', 0, '2017-06-01 00:00:00', 0),
(18, 'blastercoding', 'how well the person can code blasters', 0, '2017-06-01 00:00:00', 0),
(19, 'shieldcoding', 'how well the person can code shields', 0, '2017-06-01 00:00:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `SkillRating`
--

CREATE TABLE IF NOT EXISTS `SkillRating` (
`id` int(11) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `SkillRating`
--

INSERT INTO `SkillRating` (`id`, `profile_id`, `skill_id`, `rating`) VALUES
(1, 3, 1, 30),
(2, 3, 2, 10),
(3, 3, 3, 10),
(4, 3, 4, 0),
(5, 3, 5, 30),
(6, 3, 6, 0),
(7, 3, 7, 0),
(8, 3, 8, 0),
(9, 3, 9, 0),
(10, 3, 10, 0),
(11, 3, 11, 0),
(12, 3, 12, 0),
(13, 3, 13, 0),
(14, 3, 14, 0),
(15, 3, 15, 0),
(16, 3, 16, 0),
(17, 3, 17, 0),
(18, 3, 18, 0),
(19, 3, 19, 0);

-- --------------------------------------------------------

--
-- Table structure for table `System`
--

CREATE TABLE IF NOT EXISTS `System` (
`id` int(11) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `addy` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `System`
--

INSERT INTO `System` (`id`, `profile_id`, `name`, `addy`) VALUES
(2, 1, 'wintermute', '354e:2abe:c051:cc09:c181:e6e2:b90d:da26');

-- --------------------------------------------------------

--
-- Table structure for table `User`
--

CREATE TABLE IF NOT EXISTS `User` (
`id` int(11) NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `displayName` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(128) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `User`
--

INSERT INTO `User` (`id`, `username`, `email`, `displayName`, `password`) VALUES
(1, 'administrator', 'gevrik@totalmadownage.com', 'administrator', '$2y$10$awTb.igZSbFZcP2335J1ROgrmKDHBlCfrknidKskGREbiwZfJ7KSe');

-- --------------------------------------------------------

--
-- Table structure for table `user_role_linker`
--

CREATE TABLE IF NOT EXISTS `user_role_linker` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `user_role_linker`
--

INSERT INTO `user_role_linker` (`user_id`, `role_id`) VALUES
(1, 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Connection`
--
ALTER TABLE `Connection`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_66AA70B6C67C642E` (`sourceNode_id`), ADD KEY `IDX_66AA70B67989A678` (`targetNode_id`);

--
-- Indexes for table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
 ADD PRIMARY KEY (`version`);

--
-- Indexes for table `File`
--
ALTER TABLE `File`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_2CAD992EDC398579` (`coder_id`), ADD KEY `IDX_2CAD992ECCFA12B8` (`profile_id`), ADD KEY `IDX_2CAD992ED0952FA5` (`system_id`), ADD KEY `IDX_2CAD992E2961AF87` (`mailMessage_id`), ADD KEY `IDX_2CAD992E4BD57433` (`fileType_id`), ADD KEY `IDX_2CAD992E460D9FD7` (`node_id`);

--
-- Indexes for table `FilePart`
--
ALTER TABLE `FilePart`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `FilePartInstance`
--
ALTER TABLE `FilePartInstance`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_3C5A29E7C27AB34C` (`filePart_id`), ADD KEY `IDX_3C5A29E7DC398579` (`coder_id`), ADD KEY `IDX_3C5A29E7CCFA12B8` (`profile_id`);

--
-- Indexes for table `FilePartSkill`
--
ALTER TABLE `FilePartSkill`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_166EC64EC27AB34C` (`filePart_id`), ADD KEY `IDX_166EC64E5585C142` (`skill_id`);

--
-- Indexes for table `FileType`
--
ALTER TABLE `FileType`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `FileTypeSkill`
--
ALTER TABLE `FileTypeSkill`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_BBC97DCB4BD57433` (`fileType_id`), ADD KEY `IDX_BBC97DCB5585C142` (`skill_id`);

--
-- Indexes for table `filetype_filepart`
--
ALTER TABLE `filetype_filepart`
 ADD PRIMARY KEY (`filetype_id`,`filepart_id`), ADD KEY `IDX_1F1D197A84684DAF` (`filetype_id`), ADD KEY `IDX_1F1D197ADC78AD0` (`filepart_id`);

--
-- Indexes for table `filetype_optionalfilepart`
--
ALTER TABLE `filetype_optionalfilepart`
 ADD PRIMARY KEY (`filetype_id`,`filepart_id`), ADD KEY `IDX_AFC6E36084684DAF` (`filetype_id`), ADD KEY `IDX_AFC6E360DC78AD0` (`filepart_id`);

--
-- Indexes for table `KnownNode`
--
ALTER TABLE `KnownNode`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_367D6BC3460D9FD7` (`node_id`), ADD KEY `IDX_367D6BC3CCFA12B8` (`profile_id`);

--
-- Indexes for table `MailMessage`
--
ALTER TABLE `MailMessage`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_DB96DEDCF675F31B` (`author_id`), ADD KEY `IDX_DB96DEDCE92F8F78` (`recipient_id`), ADD KEY `IDX_DB96DEDC727ACA70` (`parent_id`);

--
-- Indexes for table `Manpage`
--
ALTER TABLE `Manpage`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_6854B728F675F31B` (`author_id`), ADD KEY `IDX_6854B728727ACA70` (`parent_id`);

--
-- Indexes for table `Node`
--
ALTER TABLE `Node`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_254D477BD0952FA5` (`system_id`);

--
-- Indexes for table `Notification`
--
ALTER TABLE `Notification`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_A765AD32CCFA12B8` (`profile_id`);

--
-- Indexes for table `Profile`
--
ALTER TABLE `Profile`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `UNIQ_4EEA9393A76ED395` (`user_id`), ADD KEY `IDX_4EEA9393774A9BA6` (`currentNode_id`), ADD KEY `IDX_4EEA939380872875` (`homeNode_id`);

--
-- Indexes for table `Role`
--
ALTER TABLE `Role`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `UNIQ_F75B2554B8C2FD88` (`roleId`), ADD KEY `IDX_F75B2554727ACA70` (`parent_id`);

--
-- Indexes for table `Skill`
--
ALTER TABLE `Skill`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `SkillRating`
--
ALTER TABLE `SkillRating`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_F6A9D363CCFA12B8` (`profile_id`), ADD KEY `IDX_F6A9D3635585C142` (`skill_id`);

--
-- Indexes for table `System`
--
ALTER TABLE `System`
 ADD PRIMARY KEY (`id`), ADD KEY `IDX_CEE114BDCCFA12B8` (`profile_id`);

--
-- Indexes for table `User`
--
ALTER TABLE `User`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `UNIQ_2DA17977E7927C74` (`email`), ADD UNIQUE KEY `UNIQ_2DA17977F85E0677` (`username`);

--
-- Indexes for table `user_role_linker`
--
ALTER TABLE `user_role_linker`
 ADD PRIMARY KEY (`user_id`,`role_id`), ADD KEY `IDX_61117899A76ED395` (`user_id`), ADD KEY `IDX_61117899D60322AC` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Connection`
--
ALTER TABLE `Connection`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `File`
--
ALTER TABLE `File`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `FilePart`
--
ALTER TABLE `FilePart`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `FilePartInstance`
--
ALTER TABLE `FilePartInstance`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `FilePartSkill`
--
ALTER TABLE `FilePartSkill`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `FileType`
--
ALTER TABLE `FileType`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `FileTypeSkill`
--
ALTER TABLE `FileTypeSkill`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=18;
--
-- AUTO_INCREMENT for table `KnownNode`
--
ALTER TABLE `KnownNode`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `MailMessage`
--
ALTER TABLE `MailMessage`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `Manpage`
--
ALTER TABLE `Manpage`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `Node`
--
ALTER TABLE `Node`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `Notification`
--
ALTER TABLE `Notification`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `Profile`
--
ALTER TABLE `Profile`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `Role`
--
ALTER TABLE `Role`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `Skill`
--
ALTER TABLE `Skill`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=20;
--
-- AUTO_INCREMENT for table `SkillRating`
--
ALTER TABLE `SkillRating`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=20;
--
-- AUTO_INCREMENT for table `System`
--
ALTER TABLE `System`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `User`
--
ALTER TABLE `User`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `Connection`
--
ALTER TABLE `Connection`
ADD CONSTRAINT `FK_66AA70B67989A678` FOREIGN KEY (`targetNode_id`) REFERENCES `Node` (`id`),
ADD CONSTRAINT `FK_66AA70B6C67C642E` FOREIGN KEY (`sourceNode_id`) REFERENCES `Node` (`id`);

--
-- Constraints for table `File`
--
ALTER TABLE `File`
ADD CONSTRAINT `FK_2CAD992E2961AF87` FOREIGN KEY (`mailMessage_id`) REFERENCES `MailMessage` (`id`),
ADD CONSTRAINT `FK_2CAD992E460D9FD7` FOREIGN KEY (`node_id`) REFERENCES `Node` (`id`),
ADD CONSTRAINT `FK_2CAD992E4BD57433` FOREIGN KEY (`fileType_id`) REFERENCES `FileType` (`id`),
ADD CONSTRAINT `FK_2CAD992ECCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`),
ADD CONSTRAINT `FK_2CAD992ED0952FA5` FOREIGN KEY (`system_id`) REFERENCES `System` (`id`),
ADD CONSTRAINT `FK_2CAD992EDC398579` FOREIGN KEY (`coder_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `FilePartInstance`
--
ALTER TABLE `FilePartInstance`
ADD CONSTRAINT `FK_3C5A29E7C27AB34C` FOREIGN KEY (`filePart_id`) REFERENCES `FilePart` (`id`),
ADD CONSTRAINT `FK_3C5A29E7CCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`),
ADD CONSTRAINT `FK_3C5A29E7DC398579` FOREIGN KEY (`coder_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `FilePartSkill`
--
ALTER TABLE `FilePartSkill`
ADD CONSTRAINT `FK_166EC64E5585C142` FOREIGN KEY (`skill_id`) REFERENCES `Skill` (`id`),
ADD CONSTRAINT `FK_166EC64EC27AB34C` FOREIGN KEY (`filePart_id`) REFERENCES `FilePart` (`id`);

--
-- Constraints for table `FileTypeSkill`
--
ALTER TABLE `FileTypeSkill`
ADD CONSTRAINT `FK_BBC97DCB4BD57433` FOREIGN KEY (`fileType_id`) REFERENCES `FileType` (`id`),
ADD CONSTRAINT `FK_BBC97DCB5585C142` FOREIGN KEY (`skill_id`) REFERENCES `Skill` (`id`);

--
-- Constraints for table `filetype_filepart`
--
ALTER TABLE `filetype_filepart`
ADD CONSTRAINT `FK_1F1D197A84684DAF` FOREIGN KEY (`filetype_id`) REFERENCES `FileType` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `FK_1F1D197ADC78AD0` FOREIGN KEY (`filepart_id`) REFERENCES `FilePart` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `filetype_optionalfilepart`
--
ALTER TABLE `filetype_optionalfilepart`
ADD CONSTRAINT `FK_AFC6E36084684DAF` FOREIGN KEY (`filetype_id`) REFERENCES `FileType` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `FK_AFC6E360DC78AD0` FOREIGN KEY (`filepart_id`) REFERENCES `FilePart` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `KnownNode`
--
ALTER TABLE `KnownNode`
ADD CONSTRAINT `FK_367D6BC3460D9FD7` FOREIGN KEY (`node_id`) REFERENCES `Node` (`id`),
ADD CONSTRAINT `FK_367D6BC3CCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `MailMessage`
--
ALTER TABLE `MailMessage`
ADD CONSTRAINT `FK_DB96DEDC727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `MailMessage` (`id`),
ADD CONSTRAINT `FK_DB96DEDCE92F8F78` FOREIGN KEY (`recipient_id`) REFERENCES `Profile` (`id`),
ADD CONSTRAINT `FK_DB96DEDCF675F31B` FOREIGN KEY (`author_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `Manpage`
--
ALTER TABLE `Manpage`
ADD CONSTRAINT `FK_6854B728727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `Manpage` (`id`),
ADD CONSTRAINT `FK_6854B728F675F31B` FOREIGN KEY (`author_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `Node`
--
ALTER TABLE `Node`
ADD CONSTRAINT `FK_254D477BD0952FA5` FOREIGN KEY (`system_id`) REFERENCES `System` (`id`);

--
-- Constraints for table `Notification`
--
ALTER TABLE `Notification`
ADD CONSTRAINT `FK_A765AD32CCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `Profile`
--
ALTER TABLE `Profile`
ADD CONSTRAINT `FK_4EEA9393774A9BA6` FOREIGN KEY (`currentNode_id`) REFERENCES `Node` (`id`),
ADD CONSTRAINT `FK_4EEA939380872875` FOREIGN KEY (`homeNode_id`) REFERENCES `Node` (`id`),
ADD CONSTRAINT `FK_4EEA9393A76ED395` FOREIGN KEY (`user_id`) REFERENCES `User` (`id`);

--
-- Constraints for table `Role`
--
ALTER TABLE `Role`
ADD CONSTRAINT `FK_F75B2554727ACA70` FOREIGN KEY (`parent_id`) REFERENCES `Role` (`id`);

--
-- Constraints for table `SkillRating`
--
ALTER TABLE `SkillRating`
ADD CONSTRAINT `FK_F6A9D3635585C142` FOREIGN KEY (`skill_id`) REFERENCES `Skill` (`id`),
ADD CONSTRAINT `FK_F6A9D363CCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `System`
--
ALTER TABLE `System`
ADD CONSTRAINT `FK_CEE114BDCCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `Profile` (`id`);

--
-- Constraints for table `user_role_linker`
--
ALTER TABLE `user_role_linker`
ADD CONSTRAINT `FK_61117899A76ED395` FOREIGN KEY (`user_id`) REFERENCES `User` (`id`),
ADD CONSTRAINT `FK_61117899D60322AC` FOREIGN KEY (`role_id`) REFERENCES `Role` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
