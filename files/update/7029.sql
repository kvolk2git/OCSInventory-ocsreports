-- Create save_query table
CREATE TABLE IF NOT EXISTS `cve_search_history` (
    `ID` INTEGER NOT NULL AUTO_INCREMENT,
    `FLAG_DATE` DATETIME NOT NULL,
    `CVE_NB` int(11) default 0,
    `TYPE` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;