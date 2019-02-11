

--
-- Database: `librarycard`
--

-- --------------------------------------------------------

--
-- Table structure for table `patrons`
--

CREATE TABLE `patrons` (
  `userName` varchar(128) COLLATE utf8_swedish_ci NOT NULL,
  `passwd` varchar(128) COLLATE utf8_swedish_ci,
  `json` text COLLATE utf8_swedish_ci,
  `activated` tinyint(1) DEFAULT FALSE,
  `activationCode` varchar(512) CHARACTER SET latin1,
  `ppid` varchar(128) CHARACTER SET latin1,
  `barcode` varchar(128) CHARACTER SET latin1,
  `datetime` datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

--
-- Indexes for table `patrons`, table must have a unique key
--
ALTER TABLE `patrons`
  ADD UNIQUE KEY `idxUserName` (`userName`) USING BTREE,
  ADD KEY `idxActivationCode` (`activationCode`),
  ADD KEY `idxPpid` (`ppid`),
  ADD KEY `idxBarcode` (`barcode`);
COMMIT;

