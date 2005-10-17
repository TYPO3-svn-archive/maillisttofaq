#
# Table structure for table 'tt_content'
#
CREATE TABLE tt_content (
	tx_maillisttofaq_subjectprefix varchar(30) DEFAULT '' NOT NULL,
	tx_maillisttofaq_selectvalue varchar(60) DEFAULT '' NOT NULL,
	tx_maillisttofaq_listEmail varchar(60) DEFAULT '' NOT NULL,
	tx_maillisttofaq_selectfield tinyint(4) unsigned DEFAULT '0' NOT NULL,
	tx_maillisttofaq_moderators tinyblob NOT NULL,
	tx_maillisttofaq_supervisors tinyblob NOT NULL,
);

#
# Table structure for table 'tx_maillisttofaq_faq'
#
CREATE TABLE tx_maillisttofaq_faq (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,

	subject tinytext NOT NULL,
	question text NOT NULL,
	question_pre text NOT NULL,
	answer text NOT NULL,
	pre text NOT NULL,

	fe_user int(11) DEFAULT '0' NOT NULL,
	last_edited_by int(11) DEFAULT '0' NOT NULL,
	cat int(11) DEFAULT '0' NOT NULL,
	thread int(11) DEFAULT '0' NOT NULL,
	howto tinyint(4) unsigned DEFAULT '0' NOT NULL,
	target_aud tinyint(4) unsigned DEFAULT '0' NOT NULL,
	view_stat int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);



#
# Table structure for table 'tx_maillisttofaq_ml'
#
CREATE TABLE tx_maillisttofaq_ml (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,

	subject tinytext NOT NULL,
	moderated_subject tinytext NOT NULL,
	all_replies int(11) DEFAULT '0' NOT NULL,
	all_latest int(11) DEFAULT '0' NOT NULL,
	all_useruidlist tinyblob NOT NULL,

	sender_email varchar(60) DEFAULT '' NOT NULL,
	sender_name varchar(60) DEFAULT '' NOT NULL,
	fe_user int(11) DEFAULT '0' NOT NULL,
	message_id varchar(100) DEFAULT '' NOT NULL,
	message_id_hash varchar(32) DEFAULT '' NOT NULL,
	parent int(11) DEFAULT '0' NOT NULL,
	reply tinyint(3) DEFAULT '0' NOT NULL,
	references_list tinytext NOT NULL,
	mail_date int(11) DEFAULT '0' NOT NULL,

	raw_mail_uid int(11) DEFAULT '0' NOT NULL,

	moderator_fe_user int(11) DEFAULT '0' NOT NULL,
	moderator_status tinyint(4) DEFAULT '0' NOT NULL,
	moderator_note tinytext NOT NULL,
	ot_flag tinyint(4) unsigned DEFAULT '0' NOT NULL,
	sticky tinyint(4) unsigned DEFAULT '0' NOT NULL,
	all_rating int(11) DEFAULT '0' NOT NULL,
	rating int(11) DEFAULT '0' NOT NULL,
	answer_state tinyint(4) unsigned DEFAULT '0' NOT NULL,
	faq_email_sent tinytext NOT NULL,

	cat int(11) DEFAULT '0' NOT NULL,
	view_stat int(11) DEFAULT '0' NOT NULL,
	content_lgd int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY idhash (message_id_hash),
	KEY parent (pid,reply,hidden,deleted,mail_date)
);

CREATE TABLE tx_maillisttofaq_ml_stick (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	fe_user int(11) DEFAULT '0' NOT NULL,
	ml_uid int(11) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid),
	KEY fe_user (fe_user)
);

CREATE TABLE tx_maillisttofaq_ml_searchstat (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	ses_ref int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	searchlog blob NOT NULL,
	PRIMARY KEY (uid),
	KEY ses_ref (ses_ref)
);

CREATE TABLE tx_maillisttofaq_mlcontent (
	ml_uid int(11) unsigned DEFAULT '0' NOT NULL,
	content text NOT NULL,
	orig_compr_content blob NOT NULL,
	all_content mediumtext NOT NULL,
	FULLTEXT (all_content),
	PRIMARY KEY (ml_uid)
) TYPE=MyISAM;

ALTER TABLE tx_maillisttofaq_mlcontent TYPE=MyISAM

#
# Table structure for table 'tx_maillisttofaq_faqcat'
#
CREATE TABLE tx_maillisttofaq_faqcat (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	pid int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
	title tinytext NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid)
);


CREATE TABLE tx_maillisttofaq_inmail (
	uid int(11) unsigned DEFAULT '0' NOT NULL auto_increment,
	mailcontent mediumblob NOT NULL,
	from_email varchar(80) DEFAULT '' NOT NULL,
	to_email varchar(80) DEFAULT '' NOT NULL,
	reply_to_email varchar(80) DEFAULT '' NOT NULL,
	sender_email varchar(80) DEFAULT '' NOT NULL,
	message_id varchar(80) DEFAULT '' NOT NULL,
	subject tinytext NOT NULL,
	deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid)
);