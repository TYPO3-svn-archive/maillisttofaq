<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

t3lib_extMgm::allowTableOnStandardPages("tx_maillisttofaq_faq");
t3lib_extMgm::addToInsertRecords("tx_maillisttofaq_faq");

$TCA["tx_maillisttofaq_faq"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faq",		
		"label" => "subject",	
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"default_sortby" => "ORDER BY crdate DESC",	
		"delete" => "deleted",	
		"enablecolumns" => Array (		
			"disabled" => "hidden",
		),
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."icon_tx_maillisttofaq_faq.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "hidden, subject, question, answer, fe_user, cat, thread",
	)
);


t3lib_extMgm::allowTableOnStandardPages("tx_maillisttofaq_ml");
t3lib_extMgm::addToInsertRecords("tx_maillisttofaq_ml");

$TCA["tx_maillisttofaq_ml"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_ml",		
		"label" => "subject",	
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"default_sortby" => "ORDER BY crdate DESC",	
		"delete" => "deleted",	
		"enablecolumns" => Array (		
			"disabled" => "hidden",
		),
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."icon_tx_maillisttofaq_ml.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "hidden, subject, content, sender_email, sender_name, fe_user, message_id, parent",
	)
);

$TCA["tx_maillisttofaq_faqcat"] = Array (
	"ctrl" => Array (
		"title" => "LLL:EXT:maillisttofaq/locallang_db.php:tx_maillisttofaq_faqcat",		
		"label" => "title",	
		"tstamp" => "tstamp",
		"crdate" => "crdate",
		"cruser_id" => "cruser_id",
		"default_sortby" => "ORDER BY title",	
		"delete" => "deleted",	
		"enablecolumns" => Array (		
			"disabled" => "hidden",
		),
		"dynamicConfigFile" => t3lib_extMgm::extPath($_EXTKEY)."tca.php",
		"iconfile" => t3lib_extMgm::extRelPath($_EXTKEY)."icon_tx_maillisttofaq_faqcat.gif",
	),
	"feInterface" => Array (
		"fe_admin_fieldList" => "hidden, title",
	)
);



$tempColumns = Array (
	"tx_maillisttofaq_subjectprefix" => Array (		
		"exclude" => 1,
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_subjectprefix",
		"config" => Array (
			"type" => "input",	
			"size" => "30",	
		)
	),
	"tx_maillisttofaq_selectfield" => Array (		
		"exclude" => 1,
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectfield",
		"config" => Array (
			"type" => "select",
			"items" => array(
				array("LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectfield.I.0",0),
				array("LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectfield.I.1",1),
				array("LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectfield.I.2",2),
				array("LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectfield.I.3",3),
			)
		)
	),
	"tx_maillisttofaq_selectvalue" => Array (		
		"exclude" => 1,
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_selectvalue",
		"config" => Array (
			"type" => "input",	
			"size" => "30",	
		)
	),
	"tx_maillisttofaq_listEmail" => Array (		
		"exclude" => 1,
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_listEmail",
		"config" => Array (
			"type" => "input",	
			"size" => "30",	
		)
	),
	"tx_maillisttofaq_moderators" => Array (
		"exclude" => 1,        
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_moderators",
		"config" => Array (
			"type" => "group",    
			"internal_type" => "db",    
			"allowed" => "fe_users",    
			"size" => 10,    
			"minitems" => 0,
			"maxitems" => 50
		)
	),
	"tx_maillisttofaq_supervisors" => Array (
		"exclude" => 1,        
		"label" => "LLL:EXT:maillisttofaq/locallang_db.php:tt_content.tx_maillisttofaq_supervisors",
		"config" => Array (
			"type" => "group",    
			"internal_type" => "db",    
			"allowed" => "fe_users",    
			"size" => 3,    
			"minitems" => 0,
			"maxitems" => 10
		)
	),
);


t3lib_div::loadTCA("tt_content");
t3lib_extMgm::addTCAcolumns("tt_content",$tempColumns,1);
$TCA["tt_content"]["types"]["list"]["subtypes_excludelist"][$_EXTKEY."_pi1"]="layout,select_key,recursive";
$TCA["tt_content"]["types"]["list"]["subtypes_addlist"][$_EXTKEY."_pi1"]="tx_maillisttofaq_selectfield;;;;1-1-1, tx_maillisttofaq_selectvalue, tx_maillisttofaq_listEmail, tx_maillisttofaq_subjectprefix, tx_maillisttofaq_supervisors, tx_maillisttofaq_moderators";

t3lib_extMgm::addStaticFile($_EXTKEY,"pi1/static/","Mailing List Archive");
t3lib_extMgm::addPlugin(Array("LLL:EXT:maillisttofaq/locallang_db.php:tt_content.list_type", $_EXTKEY."_pi1"),"list_type");

?>