<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

$TCA["tx_maillisttofaq_faq"] = Array (
	"ctrl" => $TCA["tx_maillisttofaq_faq"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,subject,question,answer,fe_user,cat,thread"
	),
	"feInterface" => $TCA["tx_maillisttofaq_faq"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"subject" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.subject",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "required",
			)
		),
		"question" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.question",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "5",
			)
		),
		"answer" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.answer",		
			"config" => Array (
				"type" => "text",
				"cols" => "30",	
				"rows" => "5",
			)
		),
		"fe_user" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.fe_user",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"cat" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.cat",		
			"config" => Array (
				"type" => "select",	
				"items" => Array (
					Array("",0),
				),
				"foreign_table" => "tx_maillisttofaq_faqcat",	
				"foreign_table_where" => "AND tx_maillisttofaq_faqcat.pid=###STORAGE_PID### ORDER BY tx_maillisttofaq_faqcat.title",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"thread" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq.thread",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_maillisttofaq_ml",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, subject, question, answer, fe_user, cat, thread")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);



$TCA["tx_maillisttofaq_ml"] = Array (
	"ctrl" => $TCA["tx_maillisttofaq_ml"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,subject,content,sender_email,sender_name,fe_user,message_id,parent"
	),
	"feInterface" => $TCA["tx_maillisttofaq_ml"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"subject" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.subject",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "required",
			)
		),
		"sender_email" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.sender_email",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"sender_name" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.sender_name",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"fe_user" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.fe_user",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "fe_users",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
		"message_id" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.message_id",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",
			)
		),
		"parent" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml.parent",		
			"config" => Array (
				"type" => "group",	
				"internal_type" => "db",	
				"allowed" => "tx_maillisttofaq_ml",	
				"size" => 1,	
				"minitems" => 0,
				"maxitems" => 1,
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, subject, sender_email, sender_name, fe_user, message_id, parent")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);



$TCA["tx_maillisttofaq_faqcat"] = Array (
	"ctrl" => $TCA["tx_maillisttofaq_faqcat"]["ctrl"],
	"interface" => Array (
		"showRecordFieldList" => "hidden,title"
	),
	"feInterface" => $TCA["tx_maillisttofaq_faqcat"]["feInterface"],
	"columns" => Array (
		"hidden" => Array (		
			"exclude" => 1,	
			"label" => "LLL:EXT:lang/locallang_general.php:LGL.hidden",
			"config" => Array (
				"type" => "check",
				"default" => "0"
			)
		),
		"title" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faqcat.title",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"eval" => "required",
			)
		),
	),
	"types" => Array (
		"0" => Array("showitem" => "hidden;;1;;1-1-1, title;;;;2-2-2")
	),
	"palettes" => Array (
		"1" => Array("showitem" => "")
	)
);
?>