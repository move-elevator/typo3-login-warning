CREATE TABLE tx_typo3loginwarning_iplog
(
	uid        int(11) NOT NULL auto_increment,
	pid        int(11) DEFAULT '0' NOT NULL,

	user_id    int(11) DEFAULT '0' NOT NULL,
	ip_address varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid)
);
