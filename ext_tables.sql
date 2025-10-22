CREATE TABLE tx_typo3loginwarning_iplog
(
	uid             int(11) NOT NULL auto_increment,
    tstamp          int(11) DEFAULT '0' NOT NULL,

	identifier_hash varchar(64) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid),
	UNIQUE KEY identifier_hash (identifier_hash)
);
