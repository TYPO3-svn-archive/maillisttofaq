<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2002-2004 Kasper Skårhøj (kasper@typo3.com)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'Mailing list/FAQ listing' for the 'maillisttofaq' extension.
 *
 * @author	Kasper Skårhøj <kasper@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *  109: class tx_maillisttofaq_pi1 extends tslib_pibase
 *  157:     function main($content,$conf)
 *  204:     function listView($content,$conf)
 *  390:     function listFAQ_HOWTO()
 *  486:     function listArchive()
 *  615:     function managerStatus()
 *
 *              SECTION: Functions generating HTML output
 *  857:     function listThreads($res)
 *  879:     function pi_list_header()
 *  912:     function pi_list_row($c)
 *  997:     function pi_list_searchBox()
 * 1019:     function categoryBox()
 * 1084:     function singleView($content,$conf)
 * 1388:     function moderatorFields($content,$cRow,$child=0)
 * 1494:     function postForm()
 * 1533:     function replyForm($replyUid,$ccEmails=array())
 * 1603:     function printSingleFaqItems($record,$single=0)
 * 1634:     function renderFAQForm($faqC,$faqUid)
 *
 *              SECTION: Functions assisting the major display functions (above)
 * 1730:     function moderatorList()
 * 1770:     function canModifyThread($threadModUser)
 * 1784:     function getFaqItemCount($rootUid)
 * 1800:     function processContent($str)
 * 1821:     function searchWordReplaceArray()
 * 1843:     function setStar($rating)
 * 1856:     function pi_list_modeSelector($items=array())
 * 1879:     function pi_list_browseresults($showResultCount=1,$tableParams="")
 * 1935:     function expThreadsCheck()
 * 1951:     function getFieldContent($fN)
 * 1993:     function removeSubjectPrefix($str)
 * 2006:     function getFieldHeader($fN)
 * 2021:     function getFieldHeader_sortLink($fN,$label='')
 *
 *              SECTION: Data processing functions
 * 2060:     function processingOfInData($pid,$rootUid=0)
 * 2269:     function sendReplyMail($replyArray,$altEmail='')
 * 2324:     function updateThread($itemUid)
 * 2407:     function updateViewStat($type,$uid,$currentCount)
 * 2428:     function getSticking()
 * 2442:     function manageSticking()
 * 2463:     function getFAQCategories()
 * 2485:     function getOnlineUsers()
 * 2514:     function getRootMessage($parent_uid,$maxLevels=50)
 * 2542:     function getChildren($parent_uid,&$result,$fields='uid',$level=1,$enFields=1,$otCheck=0)
 * 2568:     function getContentForMLitem($uid)
 * 2583:     function getUserNameLink($fe_users_uid,$showUserUidPid=0,$prefix='')
 *
 *              SECTION: MAIL transfer functions:
 * 2621:     function transferMailsFromInBox($number=10)
 * 2656:     function storeMailInMLtable($row)
 * 2765:     function collectOrphans()
 * 2805:     function getPlainTextContentOut($cArr)
 * 2854:     function htmlToPlain($in)
 * 2871:     function searchForParent($items)
 *
 *              SECTION: MAIL FEED functions:
 * 2914:     function readMails($mconf)
 * 2973:     function feedMails($mconf)
 *
 *              SECTION: Experimental / Development functions
 * 3031:     function makeFeUserStat($fe_user_uid)
 *
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_t3lib.'class.t3lib_readmail.php');





/**
 * Plugin class for the mailing list archive
 *
 */
class tx_maillisttofaq_pi1 extends tslib_pibase {

		// Values set by the listView function, from tt_content record:
	var $selectField='sender_email';	// The field from the 'inmail' table to select on. This field must be among the ones listed in $this->selectFields
	var $selectValue='';				// The value that the $this->selectField should match in order to be included in this archive. EG: typo3-owner@netfielders.de
	var $subjectPrefix='';				// The subject prefix string, used to explode the subject line when creating reply subjects. Eg: '[Typo3]'
	var $listEmail='';					// List email (where messages are sent to by the reply feature)
	var $thisPID=0;						// The PID where messages/faq items are stored.

		// used to divide messages for display - trying to remove the original message automatically. Can be extended with other divider strings.
	var $messageDividers = array(
		'-----Original Message-----'
	);

		// Internal, fixed:
	var $prefixId = 'tx_maillisttofaq_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_maillisttofaq_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'maillisttofaq';	// The extension key.
	var $selectFields=array(
		0 => 'sender_email',
		1 => 'reply_to_email',
		2 => 'to_email',
		3 => 'from_email'
	);

		// Internal, variable:
	var $cache_fe_user_names=array();		// Storing username ready for output on pages.
	var $categories=array();				// Storing selected categories from storage folder
	var $onlineUsers=array();				// Storing [uid]/[rendered username] for online users.
	var $mailParser;						// Used for the mail parser object instance.
	var $mList=array();						// List of managers
	var $sList=array();						// List of supervisors
	var $stickingElements=array();			// Be loaded with the references to sticking elements for the current fe_user user.
	var $clip =array();						// Move-thread clipboard
	var $isManager=0;						// set true if the fe_user is manager.
	var $insertRecord=0;					// set true if the singleview is triggered by "insert records"

		// Development:
#	var $LLtestPrefix='##';		// This prefix will be put before all getLL labels - thus makes it easy to find which labels ARE translated and which are not.
#	var $LLtestPrefixAlt='¤¤';

	/**
	 * Main function, distributing the display load.
	 *
	 * @param	string		$content: Blank value. Not used
	 * @param	array		$conf: TypoScript config for this plugin
	 * @return	string		HTML content from this plugin, wrapped in <div>-section with class-attribute.
	 */
	function main($content,$conf)	{
			// If no static template is included, show this error message:
		if (!$conf["_static_included"])	{
			return $this->pi_wrapInBaseClass('
			<div style="border: 1px solid black; background-color: red; padding: 5px 5px 5px 5px;">
				<p style="color:white;"><strong>Mailing List Archive Plugin is not available for use</strong></p>
				<p style="color:white;">Before you can use the archive ask your administrator to add the Mailing List Archive static template:</p>
				<img src="'.t3lib_extMgm::siteRelPath($this->extKey).'pi1/template.gif" width="450" height="152" border="0" alt="">
				<p style="color:white;">More information about configuration of this plugin can be found at <a href="http://typo3.org/doc.0.html?&tx_extrepmgm_pi1[extUid]=302&tx_extrepmgm_pi1[tocEl]=1907&cHash=370851991f">http://typo3.org/, Extension Documentation section</a></p>
			</div>
			');
		}

		$GLOBALS['TSFE']->pEncAllowedParamNames[$this->prefixId.'[showUid]']=1;
		$GLOBALS['TSFE']->pEncAllowedParamNames[$this->prefixId.'[mode]']=1;

		switch((string)$conf['CMD'])	{
			case 'singleView':
				list($t) = explode(':',$this->cObj->currentRecord);
				$this->internal['currentTable']=$t;
				$this->internal['currentRow']=$this->cObj->data;

				$this->insertRecord=1;

				return $this->pi_wrapInBaseClass($this->singleView($content,$conf));
			break;
			default:
				if ($this->piVars['readMails'])		{	// If the 'readMails' piVar is set, then it is a request for delivery of inmail rows (pulling rows from this table to another webserver)
					$this->feedMails($conf['readmail.']);
					exit;
				} else {	// Else, proceed as usual with listing the archive.
					if (strstr($this->cObj->currentRecord,'tt_content'))	{
						$conf['pidList'] = $this->cObj->data['pages'];	// Only ONE page
					}
					return $this->pi_wrapInBaseClass($this->listView($content,$conf));
				}
			break;
		}
	}

	/**
	 * Creates the listing of archive/faq/howto
	 *
	 * @param	string		Empty content string
	 * @param	array		TypoScript options for plugin passed to this function
	 * @return	string		HTML-content
	 */
	function listView($content,$conf)	{
		$this->conf=$conf;		// Setting the TypoScript passed to this function in $this->conf
		$this->pi_setPiVarDefaults();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
#		$this->pi_alwaysPrev=-1;
		$this->pi_loadLL();		// Loading the LOCAL_LANG values
		$lConf = $this->conf['listView.'];	// Local settings for the listView function

			// Preparing listing of the archive.
			// First get manager/supervisor list, then online users, faq categories etc.
		$modList = $this->moderatorList();	// Line is placed here because it fills in the ->mList array with the moderators of the list.
		$this->getOnlineUsers();	//
		$this->cache_fe_user_names=array();
		$this->getFAQCategories();	// Getting FAQ categories

			// Set isManager flag.
		$this->isManager=isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]);

		if (is_array($this->conf['messageDividers.']))	{
			foreach($this->conf['messageDividers.'] as $divStr)	{
				if (is_string($divStr))	$this->messageDividers[]=quotemeta(trim($divStr));
			}
		}
#debug($this->messageDividers);

			// Getting values from the fields of the tt_content record.
		if (strstr($this->cObj->currentRecord,'tt_content'))	{
			$this->selectField=isset($this->selectFields[$this->cObj->data['tx_maillisttofaq_selectfield']]) ? $this->selectFields[$this->cObj->data['tx_maillisttofaq_selectfield']] : $this->selectFields[0];
			$this->selectValue=trim($this->cObj->data['tx_maillisttofaq_selectvalue']);
			$this->subjectPrefix=trim($this->cObj->data['tx_maillisttofaq_subjectprefix']);
			$this->listEmail=trim($this->cObj->data['tx_maillisttofaq_listEmail']);
		}

			// Set stat for search words.
		if (!$this->isManager)	{	// Managers are not registered.
			$sWstat = $GLOBALS['TSFE']->fe_user->getKey('ses','tx_maillisttofaq_sword');
			if (is_array($sWstat) || (trim($this->piVars['sword']) && isset($_POST['tx_maillisttofaq_pi1']['sword'])))	{
				$sWstat[]=array(
					'sword' => substr($this->piVars['sword'],0,40),
					'search_submit' => isset($_POST['tx_maillisttofaq_pi1']['sword']),
					'time' => time(),
					'showUid' => $this->piVars['showUid'],
					'mode' =>  $this->piVars['mode']
				);
				$GLOBALS['TSFE']->fe_user->setKey('ses','tx_maillisttofaq_sword',$sWstat);

					// Storing the content of sWstat:
				$ses_ref = t3lib_div::md5int($GLOBALS['TSFE']->fe_user->id);
				$query = 'SELECT uid FROM tx_maillisttofaq_ml_searchstat WHERE ses_ref='.$ses_ref;
				$res = mysql(TYPO3_db,$query);
				if ($row = mysql_fetch_assoc($res))	{
					$query = 'UPDATE tx_maillisttofaq_ml_searchstat SET searchlog="'.addslashes(serialize($sWstat)).'" WHERE uid='.intval($row['uid']);
				} else {
					$query = 'INSERT INTO tx_maillisttofaq_ml_searchstat (searchlog, ses_ref, crdate) VALUES ("'.addslashes(serialize($sWstat)).'", '.$ses_ref.', '.time().')';
				}
				$res = mysql(TYPO3_db,$query);
			}
		}



			// If a single element should be displayed (and NOT faq/howto listing...)
		if ($this->piVars['showUid'] && ($this->piVars['mode']!=4 && $this->piVars['mode']!=5))	{
			$this->internal['currentTable'] = 'tx_maillisttofaq_ml';
			$this->internal['currentRow'] = $this->pi_getRecord('tx_maillisttofaq_ml',$this->piVars['showUid']);

			if (!$this->isManager)	{	// Managers are not registered.
				$this->updateViewStat('',$this->internal['currentRow']['uid'],$this->internal['currentRow']['view_stat']);
			}

			$content = $this->singleView($content,$conf);
			return $content;
		} else {
				// Set pid for listing
			$this->thisPID = intval($this->pi_getPidList($this->conf['pidList'],0));

				// Reading mails from external source? This will *pull* messages from another webserver which has a mail server that pipes the mailing list messages into the database.
				// If we're lucky we'll get to read mails from external source
			$didImport=0;
			if ($conf['readmail'])	{
				if ($conf['readmail.']['url'])	{
					$rm_probability = t3lib_div::intInRange($conf['readmail.']['probability'],0,100);
					if ($rm_probability && (rand()%100) < $rm_probability) {
						$this->readMails($conf['readmail.']);
						$didImport=1;
					} else $GLOBALS['TT']->setTSlogMessage('READMAIL: Bypassed - probability of '.$rm_probability.'% did not trigger it.');
				} else $GLOBALS['TT']->setTSlogMessage('READMAIL ERROR: TypoScript "url" property is empty! Must be set.',3);
			}

				// Transfer 50 mails from 'inbox' table when in list view:
			if ($this->selectValue)	{
				$prob = t3lib_div::intInRange($lConf['transfer_probability'],1,100);
				if (($prob && (rand()%100) < $prob)  ||  $didImport) {
					$this->transferMailsFromInBox(t3lib_div::intInRange($lConf['transfer_amount'],1,200));
				}
			}

				// Mode menu:
			$items=array(
				'1'=> $this->pi_getLL('list_mode_1','List'),
#				'2'=> $this->pi_getLL('list_mode_2','Expanded threads'),
				'3'=> $this->pi_getLL('list_mode_3','My threads'),
				'7'=> $this->pi_getLL('list_mode_7','My replies'),
				'8'=> $this->pi_getLL('list_mode_8','My selected'),
				'9'=> $this->pi_getLL('list_mode_9','Unanswered'),
				'4'=> $this->pi_getLL('list_mode_4','FAQs'),
				'5'=> $this->pi_getLL('list_mode_5','HOWTOs'),
				'6'=> $this->pi_getLL('list_mode_6','Thr. managed by me'),
				'10'=> $this->pi_getLL('list_mode_10','Mgr. stat'),
			);

				// If no login user, the 'My threads' + others are not visible.
			if (!$GLOBALS['TSFE']->loginUser)	{
				unset($items['3']);
				unset($items['7']);
				unset($items['8']);
				unset($items['6']);
				unset($items['10']);
			} else {
#$this->makeFeUserStat($GLOBALS['TSFE']->fe_user->user['uid']);
				if (!$this->isManager)	{	// Manager
					unset($items['6']);
					unset($items['10']);
				}
			}


				// Initializing the query parameters:
			if (!isset($this->piVars['pointer']))	$this->piVars['pointer']=0;
			if (!isset($this->piVars['mode']))	$this->piVars['mode']=1;
			list($this->internal['orderBy'],$this->internal['descFlag']) = explode(':',$this->piVars['sort']);
			$this->internal['results_at_a_time']=t3lib_div::intInRange($lConf['results_at_a_time'],0,1000,20);		// Number of results to show in a listing.
			$this->internal['maxPages']=t3lib_div::intInRange($lConf['maxPages'],0,1000,10);		// The maximum number of 'pages' in the browse-box: 'Page 1', 'Page 2', etc.

				// Removing defaults:
			if (!$this->piVars['pointer'])		$this->piVars['pointer']='';		//... this should work, shouldn't it??
			if (!strcmp($this->piVars['sort'],$this->conf['_DEFAULT_PI_VARS.']['sort']))	$this->piVars['sort']='';


				// Put the whole list together:
			$fullTable='';	// Clear var;

				// Adds the mode selector.
			$fullTable.=$this->pi_list_modeSelector($items);

			if ($this->isManager && $this->piVars['mode']==10)	{		// This is the manager view.
				$fullTable.=$this->managerStatus();

					// Managers:
				$fullTable.=$modList;
			} else {	// otherwise, show lists.
				if ($this->piVars['mode']!=4 && $this->piVars['mode']!=5)	{
					$fullTable.=$this->expThreadsCheck();
				}

					// QUERY / RENDERS THE LIST:
					// FAQ / HOWTO:
				if (($this->piVars['mode']==4 || $this->piVars['mode']==5))	{	// FAQ
					$fullTable.=$this->listFAQ_HOWTO();
				} else {	// NORMAL listing of the _ml table.
					$fullTable.=$this->listArchive();
				}

				$fullTable.='<br/>';

					// Online users
				if (count($this->onlineUsers))	{
					$fullTable.='<p><strong>'.$this->pi_getLL('users_online','Users online').':</strong> '.implode(', ',$this->onlineUsers).'</p>';
				}

					// Managers:
				$fullTable.=$modList;

				$fullTable.= !$this->conf['listView.']['catSelTop'] ? $this->categoryBox() : '';
			}

				// Returns the content from the plugin.
			return $fullTable;
		}
	}

	/**
	 * This will render the FAQ/HOWTO sections
	 *
	 * @return	string		HTML content
	 */
	function listFAQ_HOWTO()	{
		if ($this->piVars['showUid'])	{	// If a single element should be displayed
			$therow = $this->pi_getRecord('tx_maillisttofaq_faq',$this->piVars['showUid']);
			if (is_array($therow))	{

				if (!$this->isManager)	{	// Managers are not registered.
					$this->updateViewStat('faq',$therow['uid'],$therow['view_stat']);
				}

				if ($this->piVars['editFaqUid'])	{
					$fullTable.=$this->renderFAQForm($therow,$therow['uid']);
				} else {
						// PRocessing in-data, possibly reloading root record.
					$status=$this->processingOfInData($therow['pid']);
					if ($status)	{
						if ($status!=-1)	{
							$fullTable.= '<p style="color:red; font-weight: bold;">'.$status.'</p>';
						} else {
							$fullTable.= '<p>'.$this->pi_getLL('main_saved','').'</p>';
							$therow = $this->pi_getRecord('tx_maillisttofaq_faq',$this->piVars['showUid']);
						}
					}

					$fullTable.= '<div'.$this->pi_classParam('singleView').'>
						<p'.$this->pi_classParam('back').'>'.$this->pi_list_linkSingle($this->pi_getLL('main_back',''),0).'</p>
						'.$this->printSingleFaqItems($therow,1).'</div>';
				}
			}
		} elseif ($this->piVars['editFaqUid']=='NEW') {
			$fullTable.=$this->renderFAQForm(array('howto'=>intval($this->piVars['mode']==5)),'NEW');
		} else {
				// SAVING new...
			$status=$this->processingOfInData($this->thisPID);
			if ($status)	{
				if ($status!=-1)	{
					$fullTable.= '<p style="color:red; font-weight: bold;">'.$status.'</p>';
				} else {
					$fullTable.= '<p>'.$this->pi_getLL('listarchiv_saved','').'</p>';
				}
			}

				// LISTING faq/howto
			$this->internal['searchFieldList']='subject,question,question_pre,answer,pre';
			$this->internal['orderByList']='subject,crdate,cat,view_stat';

				// If the current value for 'orderBy' is not found in the orderBy list, then set it to the default value, which is order by 'crdate' for FAQ/HOWTO (and DESC, which will be inherited from the TypoScript defaults)
			if (!t3lib_div::inList($this->internal['orderByList'],$this->internal['orderBy']))	{
				$this->internal['orderBy'] = 'crdate';
			}


			$addWhere = $this->piVars['mode']==4 ? ' AND howto=0' :  ' AND howto=1';
			if (is_array($this->piVars['DATA']['selcat']))	{
				$setCatInt=t3lib_div::intExplode(',',implode(',',$this->piVars['DATA']['selcat']));
				$addWhere.=' AND cat IN ('.implode(',',$setCatInt).')';
			}

				// Get number of records:
			$query = $this->pi_list_query('tx_maillisttofaq_faq',1,$addWhere);
#debug($query);
			$res = mysql(TYPO3_db,$query);
			if (mysql_error())	debug(array(mysql_error(),$query));
			list($this->internal['res_count']) = mysql_fetch_row($res);

				// Make listing query, pass query to MySQL:
			$query = $this->pi_list_query('tx_maillisttofaq_faq',0,$addWhere);
			$res = mysql(TYPO3_db,$query);
			if (mysql_error())	debug(array(mysql_error(),$query));
			$this->internal['currentTable'] = 'tx_maillisttofaq_faq';

				// Adds the search box:
			$fullTable.=$this->pi_list_searchBox();

				// Adds category box (possibly)
			$fullTable.= $this->conf['listView.']['catSelTop'] ? $this->categoryBox() : '';

				// Adds the result browser:
			$fullTable.=$this->pi_list_browseresults();

				// Adds the whole list table
			$fullTable.=$this->pi_list_makelist($res);

				// MAKE NEW
			if ($GLOBALS['TSFE']->loginUser)	{
				$fullTable.='<p>'.$this->pi_linkTP_keepPIvars($this->pi_getLL('makenewfaq','Make a new FAQ or HOWTO item.'),array('editFaqUid'=>'NEW')).'</p>';
			}
		}

		return $fullTable;
	}

	/**
	 * This will render the non-FAQ/HOWTO sections (That is the list archive in all modes)
	 *
	 * @return	string		HTML content
	 */
	function listArchive()	{
		$this->internal['searchFieldList']='';
		$this->internal['orderByList']='mail_date,subject,moderated_subject,sender_name,all_replies,all_latest,view_stat,moderator_status';

			// List of fields to select:
		$this->pi_listFields='subject,mail_date,subject,moderated_subject,sender_name,sender_email,all_replies,all_latest,uid,reply,all_rating,moderator_status,moderator_fe_user,cat,all_useruidlist,sticky,answer_state,view_stat,content_lgd';

		if ($GLOBALS['TSFE']->loginUser)	{
			$this->manageSticking();
			$this->getSticking();
#debug($this->stickingElements);
		}

			// ORDER BY...
		$orderBy=' ORDER BY tx_maillisttofaq_ml.sticky DESC';
		if (t3lib_div::inList($this->internal['orderByList'],$this->internal['orderBy']))	{
			$orderBy.= ',tx_maillisttofaq_ml.'.$this->internal['orderBy'].($this->internal['descFlag']?' DESC':'').chr(10);
		}

			// Select all which are either root-items OR root-less replies (which should be 'fixed')
		$addWhere=' AND reply<=0 ';
		$addWhere.=' AND ot_flag=0 ';	// By simply removing this check OT threads can be displayed.
		if (is_array($this->piVars['DATA']['selcat']))	{
			$setCatInt=t3lib_div::intExplode(',',implode(',',$this->piVars['DATA']['selcat']));
			$addWhere.=' AND cat IN ('.implode(',',$setCatInt).')';
		}
		if ($GLOBALS['TSFE']->loginUser)	{
			if ($this->piVars['mode']==3)	{	// My threads
				$addWhere.=' AND fe_user='.intval($GLOBALS['TSFE']->fe_user->user['uid']);
			}
			if ($this->piVars['mode']==7)	{	// My replys
				$addWhere.=' AND all_useruidlist LIKE "%,'.intval($GLOBALS['TSFE']->fe_user->user['uid']).',%"';
			}
			if ($this->piVars['mode']==8)	{	// My replys
				$uidList = implode(',',array_keys($this->stickingElements));
				$addWhere.=' AND uid IN ('.($uidList?$uidList:'0').')';
			}
			if ($this->piVars['mode']==6 && isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{	// Managed...
				$addWhere.=' AND moderator_fe_user='.intval($GLOBALS['TSFE']->fe_user->user['uid']);
			}
		}
		if ($this->piVars['mode']==9)	{	// Unanswered
			$addWhere.=' AND answer_state=1';
			if (intval($this->conf['listView.']['expireDaysForUnanswered']))	{	// select only unanswered mails from the last xx days
				$addWhere.=' AND mail_date>'.(time()-intval($this->conf['listView.']['expireDaysForUnanswered'])*24*3600);
			}
		}

		$GLOBALS['TT']->push('Queries');

		$preQ='';
		if ($this->piVars['sword'])	{
			$pidList = $this->thisPID;
			$preQ = 'FROM tx_maillisttofaq_ml,tx_maillisttofaq_mlcontent'.chr(10).
				' WHERE tx_maillisttofaq_ml.uid=tx_maillisttofaq_mlcontent.ml_uid '.chr(10).
				' AND tx_maillisttofaq_ml.pid IN ('.$pidList.')'.chr(10).
				$this->cObj->enableFields('tx_maillisttofaq_ml').chr(10);	// This adds WHERE-clauses that ensures deleted, hidden, starttime/endtime/access records are NOT selected, if they should not! Almost ALWAYS add this to your queries!

			#$addWhere.=' '.$this->cObj->searchWhere($this->piVars['sword'],'all_content','tx_maillisttofaq_mlcontent');
			$addWhere.=' AND MATCH(all_content) AGAINST ("'.addslashes($this->piVars['sword']).'")';
		}

		if ($this->piVars['answered_only'])	{
			$addWhere.=' AND (answer_state=2 OR all_rating>0)';
		}
#debug(array('tx_maillisttofaq_ml',1,$addWhere,'','',$orderBy,$preQ));
			// Get number of records:
		$query = $this->pi_list_query('tx_maillisttofaq_ml',1,$addWhere,'','',$orderBy,$preQ);
#debug($query,2);
		$res = mysql(TYPO3_db,$query);
		if (mysql_error())	debug(array(mysql_error(),$query));
		list($this->internal['res_count']) = mysql_fetch_row($res);

			// Make listing query, pass query to MySQL:
		$query = $this->pi_list_query('tx_maillisttofaq_ml',0,$addWhere,'','',$orderBy,$preQ);
#debug($query);
		$res = mysql(TYPO3_db,$query);
		if (mysql_error())	debug(array(mysql_error(),$query));
		$this->internal['currentTable'] = 'tx_maillisttofaq_ml';

		$GLOBALS['TT']->pull();

			// Adds the search box:
		$fullTable.=$this->pi_list_searchBox();

			// Adds category box (possibly)
		$fullTable.= $this->conf['listView.']['catSelTop'] ? $this->categoryBox() : '';

			// Post form:
		if ($GLOBALS['TSFE']->loginUser && $this->listEmail && t3lib_div::validEmail($this->listEmail))	{
			if ($this->piVars['reply']=='NEW')	{
				$fullTable.=$this->postForm();
			} else {
				if ($this->piVars['DATA']['_send_reply'])	{
					$status=$this->processingOfInData($this->thisPID);
					if ($status!=-1)	{
						$fullTable.= '<p style="color:red; font-weight: bold;">'.$status.'</p>';
					}
				}
				$rplLabel = $this->pi_getLL('pilistsear_post','');
				$fullTable.='<p'.$this->pi_classParam('postMsg').'>'.$this->pi_linkTP_keepPIvars(
	//					'<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/reply_small.gif" width="20" height="16" border="0" alt="'.$rplLabel.'" title="'.$rplLabel.'" align="absmiddle"> '.
					$rplLabel,array('reply'=>'NEW')).'</p>';
			}
		}

			// Adds the result browser:
		$fullTable.=$this->pi_list_browseresults();

		$GLOBALS['TT']->push('List rendering');

			// RENDERING THE LISTS:
		if ($this->piVars['expThr'])	{
			$fullTable.=$this->listThreads($res);
		} else {
			$fullTable.=$this->pi_list_makelist($res);
		}
		$GLOBALS['TT']->pull();

			// Adds the result browser:
		$fullTable.=$this->pi_list_browseresults();

		return $fullTable;
	}

	/**
	 * Will display the status of management of the threads and more.
	 *
	 * @return	void
	 */
	function managerStatus()	{
		$thisMidnight = mktime(0,0,0);		// Returns seconds to this midnight date.
		$weekday = (date('w')-1+7)%7;		// Weekday, monday being 0 (zero), sunday being 6
		$thisWeekStart = $thisMidnight-$weekday*24*3600;
		$weeks = 10;

			// ************************
			// Collecting data from the database:
			// ************************
		$dat=array();
		for($a=$weeks-1;$a>=0;$a--)	{
			$dat[$a]=array();
			$dat[$a]['start']=	$thisWeekStart-$a*7*24*3600;
			$dat[$a]['end']=	$dat[$a]['start']+7*24*3600-1;
			$dat[$a]['hr_range'] = date('d-m-Y H:i:s',$dat[$a]['start']).' - '.date('d-m-Y H:i:s',$dat[$a]['end']).' / week: '.date('W',$dat[$a]['start']);

				// Selecting ALL:
			$query = 'SELECT count(*),moderator_fe_user FROM tx_maillisttofaq_ml WHERE mail_date>='.intval($dat[$a]['start']).' AND mail_date<'.intval($dat[$a]['end']).
						' AND pid IN ('.$this->thisPID.')'.
						' AND reply<=0'.
						' AND ot_flag=0'.
						$this->cObj->enableFields('tx_maillisttofaq_ml').
						' %s'.
						' GROUP BY moderator_fe_user';

			$res = mysql(TYPO3_db,sprintf($query,''));
			while($row=mysql_fetch_assoc($res))	{
				$dat[$a]['total'][$row['moderator_fe_user']]=$row;
			}
			$res = mysql(TYPO3_db,sprintf($query,' AND moderator_status < 0'));
			while($row=mysql_fetch_assoc($res))	{
				$dat[$a]['notfinished'][$row['moderator_fe_user']]=$row;
			}
		}


			// ************************
			// Making the stat table:
			// ************************

		$trows=array();
			// Header
		$tcells=array();
		$tcells[]='<td>Username:</td>';
		for($a=$weeks-1;$a>=0;$a--)	{
			$tcells[]='<td><span title="'.htmlspecialchars($dat[$a]['hr_range']).'">Week '.date('W',$dat[$a]['start']).'</span></td>';
		}
		$trows[]='<tr>'.implode('',$tcells).'</tr>';

		$totals=array();
		$localMList = $this->mList;
		$localMList[0] = 'UNMANAGED';
		foreach($localMList as $k => $mgrLink)	{
			$tcells=array();
			$tcells[]='<td>'.$mgrLink.'</td>';
			for($a=$weeks-1;$a>=0;$a--)	{
				$content = intval($dat[$a]['total'][$k]['count(*)']);
				$totals[$a]['total']+=$content;
				$totals[$a]['notfinished']+=intval($dat[$a]['notfinished'][$k]['count(*)']);
				$tcells[]='<td'.(!$content?' style="background-color:red;"':(intval($dat[$a]['notfinished'][$k]['count(*)'])?' style="background-color:#ff9900;"':'')).'>'.
					($content?$content.($k&&intval($dat[$a]['notfinished'][$k]['count(*)'])?'/'.intval($dat[$a]['notfinished'][$k]['count(*)']):''):'-').
					'</td>';
			}
			$trows[]='<tr>'.implode('',$tcells).'</tr>';
		}

		$tcells=array();
		$tcells[]='<td><strong>All threads:</strong></td>';
		for($a=$weeks-1;$a>=0;$a--)	{
			$content = intval($totals[$a]['total']);
			$tcells[]='<td'.(!$content?' style="background-color:red;"':(intval($totals[$a]['notfinished'])?' style="background-color:#ff9900;"':'')).'>'.
				($content?$content.(intval($dat[$a]['notfinished'][$k]['count(*)'])?'/'.intval($totals[$a]['notfinished']):''):'-').
				'</td>';
		}
		$trows[]='<tr>'.implode('',$tcells).'</tr>';

		$out.='
		<h3>Manager statistics over the last 10 weeks.</h3>
		<p>Red cells represent weeks where the manager did not manage any threads. The orange cells represents weeks where managed threads are not yet finished (the second number after the slash is the number of non-finished threads).</p>
		<table border="1">'.implode('',$trows).'</table>';



		// ******************************************************************
		// Threads with FAQ -request emails sent, but not yet produced.
		// ******************************************************************

		$query = 'SELECT tx_maillisttofaq_ml.faq_email_sent,tx_maillisttofaq_ml.uid,tx_maillisttofaq_ml.subject,tx_maillisttofaq_faq.thread FROM tx_maillisttofaq_ml LEFT JOIN tx_maillisttofaq_faq
			ON tx_maillisttofaq_faq.thread=tx_maillisttofaq_ml.uid
			WHERE tx_maillisttofaq_ml.faq_email_sent!="" '.
			' AND tx_maillisttofaq_ml.reply<=0'.
			' AND tx_maillisttofaq_ml.ot_flag=0'.
			' AND tx_maillisttofaq_ml.mail_date>'.(time()-31*24*3600).
			$this->cObj->enableFields('tx_maillisttofaq_ml');

		$res = mysql(TYPO3_db,$query);
		$dat=array();
		while($row=mysql_fetch_assoc($res))	{
			$inf = explode(' ',$row['faq_email_sent'],2);
			if ($row['thread'])	{
				$dat[strtolower($inf[0])]['made']++;
			} else {
				$dat[strtolower($inf[0])]['threads'][$row['uid']]=$row;
			}
		}

		$trows=array();
		foreach($dat as $k => $v)	{
			$notMade = array();
			$cc=0;
			if (is_array($v['threads']))	{
				foreach($v['threads'] as $v2)	{
					$notMade[]=$this->pi_linkTP_keepPIvars(htmlspecialchars($v2['subject']),array('showUid'=>$v2['uid']));
				}
				$cc=count($v['threads']);
			}
			$trows[]='<tr>
				<td>'.htmlspecialchars($k).'</td>
				<td nowrap>Made: <strong>'.intval($v['made']).'</strong><br/>Not made: <strong>'.$cc.'</strong></td>
				<td>'.implode('<hr/>',$notMade).'</td>
			</tr>';
		}
		$out.='
		<h3>List members having been requested to make FAQ from mail the last 31 days.</h3>
		<p>This list will show all list member emails who have been asked to create an FAQ item from the answer they got on the list. You can see which of the members who have not done that... yet.
		</p>
		<table border="1">'.implode('',$trows).'</table>';



		// ******************************************************************
		// Search word statistics:
		// ******************************************************************

		$query = 'SELECT * FROM tx_maillisttofaq_ml_searchstat ORDER BY uid DESC LIMIT 100';
		$res = mysql(TYPO3_db,$query);
		$searchWords=array();
		$tRows=array();
		while($row = mysql_fetch_assoc($res))	{
			$dat=unserialize($row['searchlog']);
			$logLines=array();
			$timeStart=$dat[0]['time'];
			if (is_array($dat))	{
				foreach($dat as $item)	{
					if ($item['search_submit'])	{
						$kw=split("[ ,]",strtolower(trim($item['sword'])));

						while(list(,$val)=each($kw))	{
							$val=trim($val);
							if (strlen($val)>=2)	{
								$searchWords[$val]++;
							}
						}
					}

					$logText='';
					$logText.=str_pad($item['time']-$timeStart, 4, '_', STR_PAD_LEFT).' ';
					if ($item['search_submit'])	{
						$logText.='SEARCH: "'.$item['sword'].'"';
					} elseif ($item['showUid'])	{
						$subject = $GLOBALS['TSFE']->sys_page->getRawRecord('tx_maillisttofaq_ml', $item['showUid'], 'subject');
						$url = $this->pi_linkTP_keepPIvars_url(array('showUid'=>$item['showUid']),0,1);
						$logText.='---> Clicked: <a href="'.$url.'" target="_NEW_WINDOW">#'.$item['showUid'].': "'.htmlspecialchars(t3lib_div::fixed_lgd($subject['subject'],40)).'"</a>';
					} else $logText='';

					if ($logText)	$logLines[]=$logText;
				}
			}
			if (count($logLines))	{
				$tRows[]='<tr>
					<td valign="top">'.date('d-m-Y H:i',$row['crdate']).'</td>
					<td nowrap>'.implode('<br/>',$logLines).'</td>
				</tr>';
			}
		}

		$out.='
		<h3>Search patterns of users:</h3>
		<p>...</p>
		<table border="1">'.implode('',$tRows).'</table>';


		$tRows=array();
		arsort($searchWords);
		foreach($searchWords as $k => $v)	{
			if ($v>1)	{
				$tRows[]='<tr>
					<td>'.htmlspecialchars($k).'</td>
					<td>'.htmlspecialchars($v).'</td>
				</tr>';
			}
		}
		$out.='
		<h3>Popular search words:</h3>
		<p>...</p>
		<table border="1">'.implode('',$tRows).'</table>';



		if (count($this->mList))	{
			$query='SELECT email FROM fe_users WHERE uid IN ('.implode(',',array_keys($this->mList)).')';
			$res = mysql(TYPO3_db,$query);
			$list=array();
			while($email=mysql_fetch_assoc($res))	{
				$list[]=$email['email'];
			}
			$out.='
			<h3>Email list of managers and supervisors</h3>
			<p>'.implode(', ',$list).'</p>';
		}

		return $out.'<br/><br/>';
	}
















	/***********************************************
	 *
	 * Functions generating HTML output
	 *
	 ***********************************************/


	/**
	 * Returns the expanded-threads view, which is the alternative listing form compared to regular list of only thread starters.
	 *
	 * @param	pointer		MySQL result pointer
	 * @return	string		HTML content for the expanded thread listing.
	 */
	function listThreads($res)	{
		$rows=array();
		while($rootRow=mysql_fetch_assoc($res))	{
			$rows[]='<p'.$this->pi_classParam($rootRow['reply']==0?'root':'lost').'>'.
					$this->pi_list_linkSingle(htmlspecialchars($this->removeSubjectPrefix($rootRow['subject'])),$rootRow['uid'],0).
					'</p>';

			$child=array();
			$children = $this->getChildren($rootRow['uid'],$child,'uid,subject,rating',1,1,1);
			reset($child);
			while(list(,$childRow)=each($child))	{
				$rows[]='<p'.$this->pi_classParam('child').'>'.str_pad('',$childRow['_LEVEL']*4*6,'&nbsp;').htmlspecialchars($this->removeSubjectPrefix($childRow['subject'])).' '.$this->setStar($childRow['rating']).'</p>';
			}
		}
		return '<div'.$this->pi_classParam('threads').'>'.implode(chr(10),$rows).'</div>';
	}

	/**
	 * Display a header row for FAQ or ARCHIVE listing.
	 *
	 * @return	string		HTML
	 */
	function pi_list_header()	{
		if ($this->internal['currentTable']=='tx_maillisttofaq_faq')	{
			return '<tr'.$this->pi_classParam('listrow-header').'>
					<td><p>'.$this->getFieldHeader_sortLink('crdate',$this->pi_getLL('listarchiv_date','')).'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('subject',$this->pi_getLL('listarchiv_subject','')).'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('cat',$this->pi_getLL('listarchiv_category','')).'</p></td>
					<td><p>'.$this->pi_getLL('listarchiv_createdBy','').'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('view_stat').'</p></td>
				</tr>';
		} else {
			return '<tr'.$this->pi_classParam('listrow-header').'>
					'.($GLOBALS['TSFE']->loginUser?'<td>&nbsp;</td>':'').'
					<td><p>'.$this->getFieldHeader_sortLink('mail_date').'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('subject').'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('sender_name').'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('all_replies').'</p></td>
					<td><p>'.$this->getFieldHeader_sortLink('all_latest').'</p></td>
					<td><p>'.$this->pi_getLL('listarchiv_cat','').'</p></td>
					'.($this->isManager?'<td><p><span title="'.$this->pi_getLL('mod_status','Managed status').'">'.
						($this->piVars['mode']==6 ? $this->getFieldHeader_sortLink('moderator_status','Md:') : 'Md:').
						'</span></p></td>':'').'
					<td><p><span title="'.htmlspecialchars($this->pi_getLL('flag_items')).'">'.$this->pi_getLL('pilistrow_flags').'</span></p></td>
					<td><p>'.$this->getFieldHeader_sortLink('view_stat').'</p></td>
				</tr>';
		}
	}

	/**
	 * Display a single row in the normal list view of either FAQ or ARCHIVE items
	 *
	 * @param	integer		Count (row-number on this page)
	 * @return	string		HTML content, table row.
	 */
	function pi_list_row($c)	{
			// Get 'editPanel' if available.
		$editPanel = $this->pi_getEditPanel();
		if ($editPanel)	$editPanel='<td>'.$editPanel.'</td>';

			// FAQ item? THen process that.
		if ($this->internal['currentTable']=='tx_maillisttofaq_faq')	{
			$showUserUidPid=intval($this->conf['tx_newloginbox_pi3-showUidPid']);
			return '<tr'.($c%2 ? $this->pi_classParam('listrow-odd') : '').'>
					<td nowrap="nowrap"><p>'.date('d-m-Y',$this->internal['currentRow']['crdate']).'</p></td>
					<td><p>'.$this->pi_list_linkSingle(htmlspecialchars($this->internal['currentRow']['subject']),$this->internal['currentRow']['uid'],0).'</p></td>
					<td><p>'.($this->internal['currentRow']['cat']==-1?'<em>'.$this->pi_getLL('pilistrow_unmoderatedItems','').'</em>':$this->categories[$this->internal['currentRow']['cat']]['title']).'</p></td>
					<td><p>'.$this->getUserNameLink($this->internal['currentRow']['fe_user'],$showUserUidPid).'</p></td>
					<td valign="top" align="center"><p>'.($this->internal['currentRow']['view_stat']?$this->internal['currentRow']['view_stat']:'-').'</p></td>
					'.$editPanel.'
				</tr>';
		} else {
			// ARCHIVE items:
				// Moderation:
			$M='&nbsp;';
			if ($this->isManager)	{
				$M='<img src="clear.gif" width="18" height="1" align="absmiddle" />';
				if ($this->internal['currentRow']['moderator_status']!=0)	{
					$username=strip_tags($this->getUserNameLink($this->internal['currentRow']['moderator_fe_user']));
					if ($this->internal['currentRow']['moderator_status']<=-1)	{
						$M='<img src="t3lib/gfx/icon_ok_dim.gif" width="18" height="16" border="0" alt="" title="'.sprintf($this->pi_getLL('pilistrow_thereAreNewUnmanaged',''),abs($this->internal['currentRow']['moderator_status']),htmlspecialchars($username)).'" align="absmiddle" />';
					} else {
						$M='<img src="t3lib/gfx/icon_ok'.($GLOBALS['TSFE']->loginUser && $this->internal['currentRow']['moderator_fe_user']==$GLOBALS['TSFE']->fe_user->user['uid']?'_brown':'').'.gif" width="18" height="16" border="0" alt="'.$this->pi_getLL('pilistsear_managedBy','').' '.htmlspecialchars($username).'" align="absmiddle" />';
					}
				}
					// Edit link:
				if ($GLOBALS['TSFE']->loginUser && ($this->internal['currentRow']['moderator_fe_user']==$GLOBALS['TSFE']->fe_user->user['uid'] || isset($this->sList[$GLOBALS['TSFE']->fe_user->user['uid']])))	{
					$M.=$this->pi_linkTP_keepPIvars('<img src="t3lib/gfx/edit2.gif" width="11" height="12" border="0" alt="Go - manage..." align="absmiddle" />',array('showUid'=>$this->internal['currentRow']['uid'],'cmd'=>'mod'));
				}
			}

				// FAQ items
			$faqItems=$this->getFaqItemCount($this->internal['currentRow']['uid']);
			$F=!$faqItems?'':'<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/faq.gif" width="26" height="11" hspace=1 border="0" alt="" title="'.$faqItems.'" align="absmiddle" />';
			$F.=$this->setStar($this->internal['currentRow']['all_rating']);
			$F.=$this->internal['currentRow']['answer_state']==1 ? '<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/noanswer.gif" width="11" hspace=1 height="11" border="0" alt="" title="'.htmlspecialchars($this->pi_getLL('pilistrow_noanswer')).'" align="absmiddle" />':'';
			$F.=$this->internal['currentRow']['answer_state']==2 ? '<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/yesanswer.gif" width="11" hspace=1 height="11" border="0" alt="" title="'.htmlspecialchars($this->pi_getLL('pilistrow_yesanswer')).'" align="absmiddle" />':'';
			if ($this->conf['listView.']['lgdIndicator'] && $this->internal['currentRow']['content_lgd'])	{
				$t = $this->pi_getLL('pilistrow_approxMsgLines').': '.intval($this->internal['currentRow']['content_lgd'] / 60);
				if ($F)	$F.='<br/>';
				if ($this->internal['currentRow']['content_lgd'] < $this->conf['listView.']['lgdIndicator.']['limit_1'])	{
					$F.='<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/lgdbar_1.gif" width="27" height="4" border="0" vspace="2" alt="" title="'.$t.'" />';
				} elseif ($this->internal['currentRow']['content_lgd'] < $this->conf['listView.']['lgdIndicator.']['limit_2'])	{
					$F.='<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/lgdbar_2.gif" width="27" height="4" border="0" vspace="2" alt="" title="'.$t.'" />';
				}elseif ($this->internal['currentRow']['content_lgd'] < $this->conf['listView.']['lgdIndicator.']['limit_3'])	{
					$F.='<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/lgdbar_3.gif" width="27" height="4" border="0" vspace="2" alt="" title="'.$t.'" />';
				} else {
					$F.='<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/lgdbar_4.gif" width="27" height="4" border="0" vspace="2" alt="" title="'.$t.'" />';
				}
			}
			if (!$F)	$F='&nbsp;';

				// Check / Un-check
			$checkUncheck = '<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/'.($this->stickingElements[$this->internal['currentRow']['uid']]?'':'un').'checked.gif" width="13" height="13" border="0" alt="">';
			$checkUncheck = $this->pi_linkTP_keepPIvars($checkUncheck,array('DATA'=>array('stick'=>$this->internal['currentRow']['uid'].':'.($this->stickingElements[$this->internal['currentRow']['uid']]?0:1))));

# BASICALLY this was just to update all threads in a way... Definitely a dev.thing.
#$this->updateThread($this->internal['currentRow']['uid']);
			return '<tr'.($this->internal['currentRow']['sticky'] ? $this->pi_classParam('listrow-sticky') : ($c%2 ? $this->pi_classParam('listrow-odd') : '')).'>
					'.($GLOBALS['TSFE']->loginUser?'<td>'.$checkUncheck.'</td>':'').'
					<td valign="top" nowrap="nowrap"><p>'.$this->getFieldContent('mail_date').'</p></td>
					<td valign="top"><p>'.trim($this->getFieldContent('subject')).'</p></td>
					<td valign="top"><p>'.$this->getFieldContent('sender_name').'</p></td>
					<td valign="top" align="center"><p>'.$this->getFieldContent('all_replies').'</p></td>
					<td valign="top" nowrap><p>'.$this->getFieldContent('all_latest').'</p></td>
					<td valign="top" align="center" nowrap><p>'.htmlspecialchars(t3lib_div::fixed_lgd($this->categories[intval($this->internal['currentRow']['cat'])]['title'],15)).'</p></td>
					'.($this->isManager?'<td valign="top" nowrap>'.$M.'</td>' : '').'
					<td valign="top" nowrap>'.$F.'</td>
					<td valign="top" align="center"><p>'.($this->internal['currentRow']['view_stat']?$this->internal['currentRow']['view_stat']:'-').'</p></td>
					'.$editPanel.'
				</tr>';
		}
	}

	/**
	 * Returns a Search box for the listing (for both FAQ or ARCHIVE listing)
	 * Extends the original function in pibase class.
	 *
	 * @return	string		HTML-string with a search box for the archive, both mailing list and FAQ
	 */
	function pi_list_searchBox()	{
			// Search box design:
		$sTables = '<div'.$this->pi_classParam('searchbox').'><table>
			<form action="'.t3lib_div::getIndpEnv('REQUEST_URI').'" method="post">
			<tr>
				<td><input type="text" name="'.$this->prefixId.'[sword]" value="'.htmlspecialchars($this->piVars['sword']).'"'.$this->pi_classParam('searchbox-sword').'></td>
				<td><input type="submit" name="_submit_search" value="'.htmlspecialchars($this->pi_getLL('pi_list_searchBox_search','Search')).'"'.$this->pi_classParam('searchbox-button').'>
					<input type="hidden" name="'.$this->prefixId.'[pointer]" value="0">
					</td>
				<td><input type="hidden" name="'.$this->prefixId.'[answered_only]" value="0">
					<input type="checkbox" name="'.$this->prefixId.'[answered_only]" value="1"'.($this->piVars['answered_only']?' checked="checked"':'').'>'.htmlspecialchars($this->pi_getLL('pi_list_searchBox_search_answered','Only answered or rated posts')).'
					</td>
			</tr></form>
			</table></div>';
		return $sTables;
	}

	/**
	 * Returns a select category box
	 *
	 * @return	string		HTML string with category selector box.
	 */
	function categoryBox()	{
		// Making category selector:
		$selOptions=is_array($this->piVars['DATA']['selcat'])?$this->piVars['DATA']['selcat']:array();
		$opt=array();
		$opt[]='<option value="0"></option>';
		reset($this->categories);

		$GLOBALS['TT']->push('CatBoxNumbers');

			// Get counts into data array
		$countDat=array();
		if (($this->piVars['mode']==4 || $this->piVars['mode']==5))	{	// FAQ
			$query = 'SELECT cat,count(*) FROM tx_maillisttofaq_faq WHERE pid IN ('.$this->thisPID.')
					 AND howto='.($this->piVars['mode']==4?0:1).'
					 '.$this->cObj->enableFields('tx_maillisttofaq_faq').
					 ' GROUP BY cat';
		} else {	// NORMAL listing of the _ml table.
			$query = 'SELECT cat,count(*) FROM tx_maillisttofaq_ml WHERE pid IN ('.$this->thisPID.')
					 AND reply<=0
					 AND ot_flag=0
					 '.$this->cObj->enableFields('tx_maillisttofaq_ml').
					 ' GROUP BY cat';
		}
		$res = mysql(TYPO3_db,$query);
		while($cRow=mysql_fetch_assoc($res))	{
			$countDat[intval($cRow['cat'])]=$cRow['count(*)'];
		}

		while(list($catuid,$catrec)=each($this->categories))	{
			$cc = intval($countDat[$catuid]);
			if ($cc)	{
				$cLabel = ' [ '.$cc.' ]';
				$opt[]='<option value="'.$catuid.'"'.(in_array($catuid,$selOptions)?' SELECTED':'').'>'.htmlspecialchars($catrec['title']).$cLabel.'</option>';
			}
		}
		$GLOBALS['TT']->pull();

		$catSelSize = t3lib_div::intInRange($this->conf['listView.']['catSelSize'],1,10);
		if ($catSelSize>1)	{
			$sP = ' size="'.$catSelSize.'" multiple="multiple"';
		} else {
			$sP = '';
		}

			// Search box design:
		$sTables = '<div'.$this->pi_classParam('catbox').'>'.
			(!$this->conf['listView.']['catSelNoHeader'] ? '<h3>'.htmlspecialchars($this->pi_getLL('Categories','Categories')).':</h3>' : '').
		'<table>
		<form action="'.t3lib_div::getIndpEnv('REQUEST_URI').'" method="post">
		<tr>
			<td><select name="'.$this->prefixId.'[DATA][selcat][]"'.$sP.'>'.implode('',$opt).'</select></td>
			<td valign="top"><input type="submit" value="'.htmlspecialchars($this->pi_getLL('pi_list_searchBox_selcat','Show categories')).'"'.$this->pi_classParam('searchbox-button').'></td>
		</tr></form>
		</table></div>';
		return $sTables;
	}

	/**
	 * Display single thread.
	 * Called both when a thread is displayed from the listing and when a single-item is displayed by the insert-records feature.
	 *
	 * @param	string		Formalized content variable - not just, just pass blank value or whatever.
	 * @param	array		TypoScript array for the WHOLE plugin!
	 * @return	string		HTML output with the listing of the thread
	 */
	function singleView($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

			// Look up root-message / thread starter and use that UID
		$rootUid = intval($this->getRootMessage($this->internal['currentRow']['uid']));
		$showUserUidPid=intval($this->conf['tx_newloginbox_pi3-showUidPid']);

		$content='';
		if ($rootUid)	{
				// If moderation is enabled, load clipboard:
			if ($this->piVars['cmd']=='mod')	{
				$this->clip = $GLOBALS["TSFE"]->fe_user->getKey("ses","tx_maillisttofaq_clip");
			}

				// Get root item record:
			$rootItem = $this->pi_getRecord('tx_maillisttofaq_ml',intval($rootUid));
			$this->thisPID=trim($rootItem['pid']);		// Set the thisPID value to the pid of the root message.

				// Processing in-data, possibly reloading root record.
			$status=$this->processingOfInData($rootItem['pid'],$rootUid);
			if ($status)	{
				$rootItem = $this->pi_getRecord('tx_maillisttofaq_ml',intval($rootUid));
				if ($status!=-1)	{
					$content.= '<p style="color:red; font-weight: bold;">'.$status.'</p>';
				}
			}
				// Possibly go back to listing mode
			if (isset($this->piVars['DATA']['_moderate_return'])) {
#debug("BLBALBAL");
				header('Location: '.t3lib_div::locationHeaderUrl($this->pi_linkTP_keepPIvars_url(array('showUid'=>''))));
			}

				// If the OT flag is set for a root item then the thread is NOT displayed at all!
			if (!$rootItem['ot_flag'])	{
					// Getting root-record content:
				$rootItem['content'] = $this->getContentForMLitem($rootItem['uid']);

					// Header:
				$headerCode = '<h3'.$this->pi_classParam('subject').'>'.htmlspecialchars($this->removeSubjectPrefix($rootItem['moderated_subject']?$rootItem['moderated_subject']:$rootItem['subject'])).'</h3>';
				$content.= $headerCode;

					// Init faq vars:
				$FAQcontent=array();
				$FAQcontrib=array();
				$faqC=array();
				$faqC['subject']=$rootItem['subject'];
				$faqC['question'] = trim($rootItem['content']);

					// MENU: IF login user is present, show menu for options:
				$cmdLinks='';
				$modOK=0;
				if (!$this->insertRecord)	{
					if ($GLOBALS['TSFE']->loginUser)	{
						$cmdLinks='';
						$cmdLinks.=$this->pi_linkTP_keepPIvars($this->pi_getLL('pilistsear_makeAFaqOr',''),array('cmd'=>'faq'));
						if ($this->canModifyThread($this->internal['currentRow']['moderator_fe_user']))	{
							$cmdLinks.=' '.$this->pi_linkTP_keepPIvars($this->pi_getLL('pilistsear_manage',''),array('cmd'=>'mod','full'=>0));
							$cmdLinks.=' '.$this->pi_linkTP_keepPIvars($this->pi_getLL('pilistsear_manageFullContent',''),array('cmd'=>'mod','full'=>1));
							$modOK=1;
						}
						$cmdLinks='<p'.$this->pi_classParam('cmd').'>'.$cmdLinks.'</p>';
						$content.=$cmdLinks;
					}

					if ($this->piVars['cmd']=='mod')	{
						if (!$modOK)	{
							$this->piVars['cmd']='';
						} else {
							$this->internal['currentRow']['moderator_fe_user']=intval($GLOBALS['TSFE']->fe_user->user['uid']);
							$query ='UPDATE tx_maillisttofaq_ml SET moderator_fe_user='.$this->internal['currentRow']['moderator_fe_user'].
										', moderator_status='.intval($this->internal['currentRow']['moderator_status']?$this->internal['currentRow']['moderator_status']:-1).
										' WHERE uid='.intval($this->internal['currentRow']['uid']);
	#debug($query);
							$res=mysql(TYPO3_db,$query);
							echo mysql_error();
						}
					}
				}

					// Getting children:
				$result=array();
				$children = $this->getChildren($rootUid,$result,'uid,mail_date,sender_name,sender_email,fe_user,subject,ot_flag,moderator_note,moderator_fe_user,rating',1,1,
//								$this->piVars['cmd']=='mod'?0:1);
								0);
				$tempROW=$this->internal['currentRow'];

					// Thread menu:
				$prevID=0;
				$threadMenu='';
				$ccEmailArr=array();
				reset($result);
				while(list($kk,$childRow)=each($result))	{
					$threadMenu.='<p'.$this->pi_classParam('child').'>'.str_pad('',$childRow['_LEVEL']*4*6,'&nbsp;').'<a href="#childUid'.$childRow['uid'].'">'.htmlspecialchars($this->removeSubjectPrefix($childRow['subject'])).'</a> - <em>'.($childRow['sender_name']?$childRow['sender_name']:$childRow['sender_email']).'</em>'.$this->setStar($childRow['rating']).'</p>';
					if (isset($result[$prevID]))	$result[$prevID]['next']=$childRow['uid'];
					$prevID=$childRow['uid'];
					if (trim($childRow['sender_email']))	$ccEmailArr[trim($childRow['sender_email'])]=trim($childRow['sender_email']);
				}
				$ccEmailFlag = ($this->conf['listView.']['daysBeforeDirectNotificationsWhenReply'] && $rootItem['mail_date']+(24*3600*$this->conf['listView.']['daysBeforeDirectNotificationsWhenReply'])<time());

				if ($threadMenu)	{
					$content.='<div'.$this->pi_classParam('thrMenu').'><p'.$this->pi_classParam('threadLinks').'>'.$this->pi_getLL('pilistsear_threadAnswers','').'</p>'.$threadMenu.'</div>';
				}

					// Print main message
				$content.='<p'.$this->pi_classParam('author').'><strong>'.$this->getFieldContent('mail_date').'</strong>&nbsp; '.$this->pi_getLL('pilistsear_by','').' &nbsp;<strong>'.
					$this->getFieldContent('sender_name').', &nbsp; '.
					$this->getFieldContent('sender_email').' &nbsp; '.
					($rootItem['fe_user'] ? ' &nbsp; '.$this->getUserNameLink($rootItem['fe_user'],$showUserUidPid,'<img src="t3lib/gfx/i/'.(isset($this->mList[$rootItem['fe_user']])?'user2':'fe_users').'.gif" width="18" height="16" border="0" align="absmiddle">'): '').
					'</strong></p>';

				$msgContent = $rootItem['content'];
				if ($this->piVars['cmd']=='mod')	{
					if (!$this->piVars['full'])	{
						list($msgContent) = split(implode('|',$this->messageDividers),$msgContent,2);
					}
					$content.=$this->moderatorFields(trim($msgContent),$rootItem);
				} elseif ($this->piVars['cmd']=='faq') {
					$FAQcontrib[$rootItem['sender_email']]=($rootItem['sender_name']?$rootItem['sender_name']:$rootItem['sender_email']);
				} else {
					if (trim($rootItem['moderator_note']))	{
						$content.='<p><strong>'.$this->pi_getLL('pilistsear_moderatorNote','').' ('.$this->mList[$rootItem['moderator_fe_user']].'):</strong> <em>'.nl2br($this->processContent(htmlspecialchars(trim($rootItem['moderator_note'])))).'</em></p>';
					}
					list($msgContent) = split(implode('|',$this->messageDividers),$msgContent,2);
					$content.='<p>'.nl2br($this->processContent(htmlspecialchars(trim($msgContent)))).'</p>';
					if ($GLOBALS['TSFE']->loginUser && $this->listEmail && t3lib_div::validEmail($this->listEmail))	{
						if ($this->piVars['reply']==$rootItem['uid'])	{
							$content.=$this->replyForm(
								$rootItem['uid'],
								$ccEmailFlag ? $ccEmailArr : array()
							);
						} else {
							$rplLabel = $this->pi_getLL('pilistsear_reply','');
							$content.='<p'.$this->pi_classParam('replyMsg').'>'.$this->pi_linkTP_keepPIvars('<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/reply_small.gif" width="20" height="16" border="0" alt="'.$rplLabel.'" title="'.$rplLabel.'" align="absmiddle"> '.$rplLabel,array('reply'=>$rootItem['uid'])).'</p>';
						}
					}
				}

					// Print thread items:
				reset($result);
				while(list($kk,$resRec)=each($result))	{
					$this->internal['currentRow']=$resRec;
					$links='<a href="#" style="font-size:10px;">'.$this->pi_getLL('pilistsear_top','').'</a>';
					if ($resRec['next'])	$links.='<a href="#childUid'.$resRec['next'].'" style="font-size:10px;">'.$this->pi_getLL('pilistsear_next','').'</a>';

					if ($resRec['moderator_fe_user'])	{
#						$username=strip_tags($this->getUserNameLink($resRec['moderator_fe_user']));
#						$M='<img src="t3lib/gfx/icon_ok.gif" width="18" height="16" border="0" align="absmiddle" alt="Managed by '.htmlspecialchars($username).'">';
						$M='';
						$Mprefix='';
					} else {
						$M='';
						$Mprefix='-nonMod';
					}

					$reply='';
					$reply.='<p'.$this->pi_classParam('replyauthor'.$Mprefix).'>'.$M.$this->setStar($resRec['rating']).$links.'&nbsp;&nbsp;<strong>'.$this->getFieldContent('mail_date').'</strong>&nbsp; '.$this->pi_getLL('pilistsear_by','').' &nbsp;<strong>'.
						$this->getFieldContent('sender_name').', &nbsp; '.
						$this->getFieldContent('sender_email').' &nbsp; '.
						($resRec['fe_user'] ? ' &nbsp; '.$this->getUserNameLink($resRec['fe_user'],$showUserUidPid,'<img src="t3lib/gfx/i/'.(isset($this->mList[$resRec['fe_user']])?'user2':'fe_users').'.gif" width="18" height="16" border="0" align="absmiddle">'): '').
						'</strong></p>';

					if ($this->piVars['cmd']=='mod' || !$resRec['ot_flag'])	{
						$msgContent= $this->getContentForMLitem($resRec['uid']);

						if ($this->piVars['cmd']=='mod')	{
	#debug($this->piVars['full']);
							if (!$this->piVars['full'])	{
								list($msgContent) = split(implode('|',$this->messageDividers),$msgContent,2);
							}
							$msgContent=$this->moderatorFields(trim($msgContent),$resRec,1);
						} elseif ($this->piVars['cmd']=='faq') {
							$FAQcontent[] = '---------------------------------------';
							$FAQcontent[] = ($resRec['sender_name']?$resRec['sender_name']:$resRec['sender_email']).', '.$resRec['sender_email'].':';
							$FAQcontent[] = '---------------------------------------';
							$FAQcontent[] = trim($msgContent);
							$FAQcontent[] = "\n\n\n\n\n";

							$FAQcontrib[$resRec['sender_email']]=($resRec['sender_name']?$resRec['sender_name']:$resRec['sender_email']);
						} else {
							$msgContentParts = split(implode('|',$this->messageDividers),$msgContent,2);
							if (count($msgContentParts)==2)	{
								$msgContent = $msgContentParts[0];
							}
							$msgContent= nl2br($this->processContent(htmlspecialchars(trim($msgContent))));
							if (trim($resRec['moderator_note']))	{
								$msgContent='<p'.$this->pi_classParam('modnote').'><strong>'.$this->pi_getLL('pilistsear_moderatorNote','').' ('.$this->mList[$resRec['moderator_fe_user']].'):</strong> <em>'.nl2br($this->processContent(htmlspecialchars(trim($resRec['moderator_note'])))).'</em></p>'.
									$msgContent;
							}
							if ($GLOBALS['TSFE']->loginUser && $this->listEmail && t3lib_div::validEmail($this->listEmail))	{
								if ($this->piVars['reply']==$resRec['uid'])	{
									$msgContent.=$this->replyForm(
										$resRec['uid'],
										$ccEmailFlag ? $ccEmailArr : array()
									);
								} else {
									$url = $this->pi_linkTP_keepPIvars_url(array('reply'=>$resRec['uid']));
									$rplyLabel = $this->pi_getLL('pilistsear_reply','');
									$msgContent.='<p'.$this->pi_classParam('replyMsg').'><a href="'.$url.'#childUid'.$resRec['uid'].'"><img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/reply_small.gif" width="20" height="16" border="0" alt="'.$rplyLabel.'" title="'.$rplyLabel.'" align="absmiddle"> '.$rplyLabel.'</a></p>';
								}
							}
						}

						$reply.='<p>'.$msgContent.'</p>';
							// Saved-my-day answer
						if (!$this->insertRecord && $GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['uid']!=$resRec['fe_user'])	{
							$reply.='<p'.$this->pi_classParam('saveday').'>'.$this->pi_linkTP_keepPIvars($this->pi_getLL('pilistsear_clickHereIfThis',''),array('DATA'=>array('answerSavedDay'=>$resRec['uid']))).'</p>';
						}
		#debug($resRec['rating'],1);
					} else {	// ot:
						if ($this->conf['listView.']['showHiddenOTmsgHeaders'])	{
							$reply.='<p><em>'.$this->pi_getLL('pilistsear_hiddenOffTopicBy','').'</em></p>';
						} else $reply='';
					}
					$content.=$reply?'<div'.$this->pi_classParam('reply'.$Mprefix).' style="margin-left:'.($resRec['_LEVEL']*20).'px;" name="childUid'.$resRec['uid'].'" id="childUid'.$resRec['uid'].'">'.$reply.'</div>':'';
				}
				$this->internal['currentRow']=$tempROW;

					// Management:
				if ($this->internal['currentRow']['moderator_fe_user'])	{
					$content.='<p'.$this->pi_classParam('managed').'>'.$this->pi_getLL('pilistsear_thisThreadHasBeen','').' '.$this->mList[$this->internal['currentRow']['moderator_fe_user']].'</p>';
				}
				$content.=$cmdLinks;


					// Printing FAQ items:
				$query = 'SELECT * FROM tx_maillisttofaq_faq WHERE pid='.$this->thisPID.
							' AND thread='.intval($rootUid).
							$this->cObj->enableFields('tx_maillisttofaq_faq');
				$res_faq=mysql(TYPO3_db,$query);
				if (mysql_num_rows($res_faq))	{
					$content.='<br/><p'.$this->pi_classParam('faqhead').'>'.$this->pi_getLL('pilistsear_faqHowtoItemsFor','').'</p>';
					while($faq_row=mysql_fetch_assoc($res_faq))	{
						$content.=$this->printSingleFaqItems($faq_row);
					}
				}

				if ($this->piVars['cmd']=='faq')	{
					$content=$headerCode;
					$content.='<p'.$this->pi_classParam('back').'>'.$this->pi_linkTP_keepPIvars($this->pi_getLL('pilistsear_backToThread',''),array('cmd'=>'','full'=>'','editFaqUid'=>'')).'</p>';

					$content.='<h4>'.$this->pi_getLL('pilistsear_makingAFaqItem','').'</h4>';

					$content.='<p>'.implode('</p><p>',t3lib_div::trimExplode(chr(10),trim($this->pi_getLL('pilistsear_theDocumentationOfTypo3','')))).'</p>';

					$content.='<br/>';

					$faqC['answer'] = implode(chr(10),$FAQcontent)."\n\n\n---------------\n".$this->pi_getLL('pilistsear_contributers','').":\n".implode(chr(10),$FAQcontrib);
					$faqUid='NEW';

					if ($this->piVars['editFaqUid'])	{
						$faqC=$this->pi_getRecord('tx_maillisttofaq_faq',$this->piVars['editFaqUid']);
						if (!is_array($faqC))	{
							return;	// error...
						} else {
							$faqUid = $faqC['uid'];
						}
					}

					$content.=$this->renderFAQForm($faqC,$faqUid);
				}
				if ($this->piVars['cmd']=='mod')	{
					$content='<form action="'.t3lib_div::getIndpEnv('REQUEST_URI').'" method="post" style="margin: 0px 0px 0px 0px;" name="'.$this->prefixId.'_form">
						'.$content.'<br/>
						<input type="submit" name="'.$this->prefixId.'[DATA][_moderate]" value="'.$this->pi_getLL('modview_submit').'">
						<input type="submit" name="'.$this->prefixId.'[DATA][_moderate_return]" value="'.$this->pi_getLL('modview_submitreturn').'">
						<input type="submit" name="'.$this->prefixId.'[DATA][_cancel]" value="'.$this->pi_getLL('modview_cancel').'">

						<input type="hidden" name="'.$this->prefixId.'[cmd]" value=""></form>';
				}
			}

				// Finalize output:
			if ($this->piVars['cmd']!='faq' && !$this->insertRecord)	{
				$back='<p'.$this->pi_classParam('back').'>'.$this->pi_list_linkSingle($this->pi_getLL('moderatorf_back',''),0).'</p>';
			} else $back='';

				// If insertRecord link to thread
			if ($this->insertRecord)	{
				$content.='<p'.$this->pi_classParam('back').'>'.
						$this->pi_linkToPage($this->pi_getLL('moderatorf_goThread',''),$rootItem['pid'],'',array($this->prefixId.'[showUid]'=>$rootUid)).
						'</p>';
			}

			$content='<div'.$this->pi_classParam('singleView').'>
				'.$back.'
				'.$content.'
				'.$back.'
			</div>'.$this->pi_getEditPanel();
		}

		return $content;
	}

	/**
	 * This makes the form fields for moderators of a thread
	 * The function is called recursively from the singleView function, one time for each message
	 *
	 * @param	string		The content of the message
	 * @param	array		The message row.
	 * @param	boolean		1 if the message is a child-message (has a parent record)
	 * @return	string		HTML content
	 */
	function moderatorFields($content,$cRow,$child=0)	{
		$out='';

			// Only for thread starters:
		if (!$child)	{
			$out.='<br/><input type="submit" name="'.$this->prefixId.'[DATA][_moderate]" value="'.$this->pi_getLL('modview_submit').'"> '.
					'<input type="submit" name="'.$this->prefixId.'[DATA][_moderate_return]" value="'.$this->pi_getLL('modview_submitreturn').'"> '.
					'<input type="submit" name="'.$this->prefixId.'[DATA][_cancel]" value="'.$this->pi_getLL('modview_cancel').'"><br/><br/>';

				# Editing the subject - which is stored in an alternative subject field since the original subject should not be changed.
			$out.='<p><strong>'.$this->pi_getLL('listFieldHeader_subject','Subject:').'</strong></p>';
			$out.='<input type="hidden" value="'.htmlspecialchars($cRow['subject']).'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][subject]">';	// Original subject submitted for comparison.
			$out.='<input type="text" value="'.htmlspecialchars(trim($cRow['moderated_subject']) ? $cRow['moderated_subject'] : $cRow['subject']).'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][moderated_subject]" style="'.$this->conf['listView.']['textarea_style'].'"><br/>';

				// Answer state for thread starter.
			$out.='<p><strong>'.$this->pi_getLL('modview_astate','Answer state').':</strong></p>';
			$out.='<select name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][answer_state]">
						<option value="0"></option>
						<option value="1"'.($cRow['answer_state']==1?' selected="selected"':'').'>'.$this->pi_getLL('modview_stillnotanswered','Needs answer').'</option>
						<option value="2"'.($cRow['answer_state']==2?' selected="selected"':'').'>'.$this->pi_getLL('modview_answered','Answered').'</option>
					</select>';

			if ($cRow['faq_email_sent'])	{
				$out.='<p><em>Email request to create a FAQ item was sent to "'.htmlspecialchars($cRow['faq_email_sent']).'"</em></p>';
			} else {
				$out.='<p><input type="checkbox" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][faq_email_sent]" value="'.htmlspecialchars($cRow['sender_email']).'">Send request about making a FAQ entry to "<em>'.htmlspecialchars($cRow['sender_name']).', '.htmlspecialchars($cRow['sender_email']).'</em>"</p>';
				$datA = array(
					'name' => $cRow['sender_name'],
					'question' => $this->removeSubjectPrefix(trim($cRow['moderated_subject']) ? $cRow['moderated_subject'] : $cRow['subject']),
					'listmgr' => $GLOBALS['TSFE']->fe_user->user['name'].' ['.$GLOBALS['TSFE']->fe_user->user['username'].']',
					'mainUrl' => t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR'),
					'faqUrl' => t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR').$this->pi_linkTP_keepPIvars_url(array('showUid'=>$this->piVars['showUid'],'cmd'=>'faq'),0,1)
				);
#debug($datA);
				$out.='<input type="hidden" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_DAT_faq_email_sent]" value="'.htmlspecialchars(serialize($datA)).'">';
			}

					// Category selected.
			$out.='<p><strong>'.$this->pi_getLL('modview_cat','Category').':</strong></p>';
			$out.='<p>'.$this->pi_getLL('modview_bestcat','Select the category where this thread fits in best').':</p>';
			$opt=array();
			$opt[]='<option value="0"></option>';
			reset($this->categories);
			while(list($catuid,$catrec)=each($this->categories))	{
				$opt[]='<option value="'.$catuid.'"'.($cRow['cat']==$catuid?' selected="selected"':'').'>'.htmlspecialchars($catrec['title']).'</option>';
			}
			$out.='<select name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][cat]">'.implode("",$opt).'</select><br/>';

				// If the manager is also SUPERVISOR, then he can make a thread "sticky"
			if ($this->sList[$GLOBALS['TSFE']->fe_user->user['uid']])	{
				$out.='<input type="hidden" value="0" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][sticky]">';
				$out.='<p><input type="checkbox" value="1" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][sticky]"'.($cRow['sticky']?' CHECKED':'').'>'.$this->pi_getLL('modview_sticky','Sticky thread (superv. only)').'</p>';
			}

			$out.='<br/>';
		}

			// Edit content+mod-note of message:
		$out.='<textarea cols="80" rows="'.t3lib_div::intInRange(count(explode(chr(10),$content)),3,20).'" style="'.$this->conf['listView.']['textarea_style'].'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][content]">'.t3lib_div::formatForTextarea($content).'</textarea><br/>';
		$out.='<p>'.$this->pi_getLL('modview_modnote','Manager note to above message').':</p>';
		$out.='<input type="text" value="'.htmlspecialchars($cRow['moderator_note']).'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][moderator_note]" style="'.$this->conf['listView.']['textarea_style'].'"><br/>';
			// Send as reply
		$out.='<input type="hidden" value="0" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][ot_flag]">';
		$out.='<p><input type="checkbox" value="1" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_sendAsReply]">'.$this->pi_getLL('modview_sendasreply').'</p>';
		$out.='<br/>';
			// Set manager-fe_user value (hidden field)
		$out.='<input type="hidden" value="'.$GLOBALS['TSFE']->fe_user->user['uid'].'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][moderator_fe_user]">';

			// OT-check box:
		$out.='<input type="hidden" value="0" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][ot_flag]">';
		$out.='<p><input type="checkbox" value="1" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][ot_flag]"'.($cRow['ot_flag']?' CHECKED':'').'>'.$this->pi_getLL('modview_othide','OT / Hide').'</p>';

			// Move
		$out.='<input type="hidden" value="0" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_move]">';
		$out.='<p><input type="checkbox" value="'.htmlspecialchars(t3lib_div::fixed_lgd($this->removeSubjectPrefix($cRow['subject']),50).' / '.trim($cRow['sender_name'].' <'.$cRow['sender_email'].'>')).'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_move]"'.($this->clip[$cRow['uid']]?' checked="checked"':'').'>'.$this->pi_getLL('modview_move','Mark node for moving').' (#'.$cRow['uid'].')</p>';

			// Paste in thread
		if (is_array($this->clip) && count($this->clip))	{
			$out.='<p>'.$this->pi_getLL('modview_paste','Select a node to paste as reply to this message').':</p>';
			$opt=array();
			$opt[]='<option value="0"></option>';
			reset($this->clip);
			foreach ($this->clip as $kUid => $title)	{
				$opt[]='<option value="'.$kUid.'">'.htmlspecialchars('#'.$kUid.': '.$title).'</option>';
			}
			$out.='<select name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_paste]">'.implode('',$opt).'</select><br/>';
		}

			// If NOT thread-starter:
		if ($child)	{
				// Breaks the thread?
			$out.='<p><input type="checkbox" value="1" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][_break]">'.$this->pi_getLL('modview_breaknew','Break into new thread').'</p>';
				// Supervisors can set/edit a rating value
			if ($this->sList[$GLOBALS['TSFE']->fe_user->user['uid']])	{
				$out.='<input type="text" value="'.htmlspecialchars($cRow['rating']).'" name="'.$this->prefixId.'[DATA]['.$cRow['uid'].'][rating]" style="width:30px;" size="10"> '.$this->pi_getLL('modview_ratingvalue','Rating value (superv. only)').'<br/>';
			}
		}

		return $out;
	}

	/**
	 * Post form for archive messages
	 *
	 * @return	string		HTML content for the form.
	 */
	function postForm()	{
		if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['email'])	{
			$url = $this->pi_linkTP_keepPIvars_url(array('reply'=>''));

			$content='<form action="'.$url.'" method="post" style="margin: 0px 0px 0px 0px;">
				<p'.$this->pi_classParam('rpHead').'>'.$this->pi_getLL('moderatorf_onlinePost','').'</p>
				<p>'.$this->pi_getLL('moderatorf_from','').' <em>'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name'].' <'.$GLOBALS['TSFE']->fe_user->user['email'].'>').'</em></p>
				<p>'.$this->pi_getLL('moderatorf_subject','').'</p>
				<input type="text" value="" name="'.$this->prefixId.'[DATA][reply][subject]"><br/>
				<p>'.$this->pi_getLL('moderatorf_yourPost','').'</p>
				<textarea cols="80" rows="10" style="'.$this->conf['listView.']['textarea_style'].'" name="'.$this->prefixId.'[DATA][reply][msg]">
'.sprintf(trim($this->pi_getLL('moderatorf_hiLWriteYour','')),
	$GLOBALS['TSFE']->fe_user->user['name'].' ('.$GLOBALS['TSFE']->fe_user->user['username'].')',
	t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR').$this->pi_linkTP_keepPIvars_url(array(),0,1)
	).'
				</textarea><br/>
				<input type="checkbox" name="'.$this->prefixId.'[DATA][reply][cc]" value="1">'.$this->pi_getLL('moderatorf_ccMySelf','').'<br/>

				<p'.$this->pi_classParam('notice').'><strong>'.$this->pi_getLL('moderatorf_notice','').'</strong> '.nl2br(sprintf(trim($this->pi_getLL('moderatorf_youEmMustEm','')),$GLOBALS['TSFE']->fe_user->user['email'])).'</p>
				<input type="hidden" value="'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name']).'" name="'.$this->prefixId.'[DATA][reply][name]">
				<input type="hidden" value="'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['email']).'" name="'.$this->prefixId.'[DATA][reply][email]">
				<input type="submit" name="'.$this->prefixId.'[DATA][_send_reply]" value="'.$this->pi_getLL('moderatorf_sendpost','').'" onClick="return confirm(unescape(\''.rawurlencode($this->pi_getLL('moderatorf_nowYouAreA','')).'\'));"> <input type="submit" name="'.$this->prefixId.'[DATA][_cancel]" value="'.$this->pi_getLL('printsingl_cancel','').'">
			</form>';

			$content='<div'.$this->pi_classParam('postForm').'>
					'.$content.'
				</div>';

			return $content;
		}
	}

	/**
	 * Reply form for archive messages
	 *
	 * @param	integer		Uid of the messag to which a response should be sent.
	 * @param	[type]		$ccEmails: ...
	 * @return	string		HTML contnet for the form.
	 */
	function replyForm($replyUid,$ccEmails=array())	{
		if ($GLOBALS['TSFE']->loginUser && $GLOBALS['TSFE']->fe_user->user['email'])	{
			$url = $this->pi_linkTP_keepPIvars_url(array('reply'=>''));

			$replyTo = $this->pi_getRecord('tx_maillisttofaq_ml',$replyUid);
			if (is_array($replyTo))	{
				$subject='Re: '.$replyTo['subject'];
				$expStr=$this->subjectPrefix;

					// Get reply text:
				$indentedMsg = $this->getContentForMLitem($replyUid);
				$charWidth=t3lib_div::intInRange($this->conf['listView.']['replyIndented_BreakNumChar'],20,1000,80);
				$indentedMsg = t3lib_div::breakTextForEmail($indentedMsg,"\n",$charWidth);
				$indentedMsg = "> ".implode(chr(10)."> ",explode(chr(10),$indentedMsg));

				if (trim($expStr))	{
					list($pre,$subject) = explode($expStr,$replyTo['subject'],2);
#	debug(array($pre,$subject));
					$subject='Re: '.trim($expStr).' '.trim($subject);
				}
				list($sName) = 	split('[[:space:]]',$replyTo['sender_name']);
				$content='<form action="'.$url.'" method="post" style="margin: 0px 0px 0px 0px;" name="'.$this->prefixId.'_replyform">
					<p'.$this->pi_classParam('rpHead').'>'.$this->pi_getLL('moderatorf_onlineReply','').'</p>
					<p>'.$this->pi_getLL('moderatorf_subject','').' <em>'.htmlspecialchars($subject).'</em></p>
					<p>'.$this->pi_getLL('moderatorf_from','').' <em>'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name'].' <'.$GLOBALS['TSFE']->fe_user->user['email'].'>').'</em></p>
					<p>'.$this->pi_getLL('moderatorf_yourReply','').'</p>
					<textarea cols="80" rows="10" style="'.$this->conf['listView.']['textarea_style'].'" name="'.$this->prefixId.'[DATA][reply][msg]">
'.sprintf(
		trim($this->pi_getLL('moderatorf_hiSWriteYour','')),
		$sName,
		$GLOBALS['TSFE']->fe_user->user['name'].' ('.$GLOBALS['TSFE']->fe_user->user['username'].')',
		t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR').$this->pi_linkTP_keepPIvars_url(array('showUid'=>$replyUid),0,1)
	).'
					</textarea><br/>
					<input type="hidden" name="_reply_text" value="'.htmlspecialchars($indentedMsg).'">
					<input type="checkbox"  name="_load_reply_text" value="1" onclick="
						if (this.checked){
							document.'.$this->prefixId.'_replyform[\''.$this->prefixId.'[DATA][reply][msg]\'].value = \'\'+document.'.$this->prefixId.'_replyform[\''.$this->prefixId.'[DATA][reply][msg]\'].value+\'\n\'+document.'.$this->prefixId.'_replyform._reply_text.value;
						} else {
							this.checked=1;
						}
						">'.$this->pi_getLL('moderatorf_loadReplyText','').'<br/>

					<input type="checkbox"  name="'.$this->prefixId.'[DATA][reply][cc]" value="1">'.$this->pi_getLL('moderatorf_ccMySelf','').'<br/>
					'.(count($ccEmails) ? '<input type="checkbox"  name="'.$this->prefixId.'[DATA][reply][cc_email]" value="'.htmlspecialchars(implode(',',$ccEmails)).'">'.sprintf($this->pi_getLL('moderatorf_ccAll',''),$this->conf['listView.']['daysBeforeDirectNotificationsWhenReply']).'<br/><p><em>('.htmlspecialchars(implode(', ',$ccEmails)).')</em></p>' : '').'

					<p'.$this->pi_classParam('notice').'><strong>'.$this->pi_getLL('moderatorf_notice','').'</strong> '.nl2br(sprintf(trim($this->pi_getLL('moderatorf_youEmMustEm','')),$GLOBALS['TSFE']->fe_user->user['email'])).'</p>
					<input type="hidden" value="'.htmlspecialchars($subject).'" name="'.$this->prefixId.'[DATA][reply][subject]">
					<input type="hidden" value="'.htmlspecialchars($replyTo['message_id']).'" name="'.$this->prefixId.'[DATA][reply][message_id]">
					<input type="hidden" value="'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name']).'" name="'.$this->prefixId.'[DATA][reply][name]">
					<input type="hidden" value="'.htmlspecialchars($GLOBALS['TSFE']->fe_user->user['email']).'" name="'.$this->prefixId.'[DATA][reply][email]">
					<input type="submit" name="'.$this->prefixId.'[DATA][_send_reply]" value="'.$this->pi_getLL('moderatorf_sendreply','').'" onClick="return confirm(unescape(\''.rawurlencode($this->pi_getLL('moderatorf_nowYouAreA','')).'\'));"> <input type="submit" name="'.$this->prefixId.'[DATA][_cancel]" value="'.$this->pi_getLL('printsingl_cancel','').'">
				</form>';

				$content='<div'.$this->pi_classParam('replyForm').'>
						'.$content.'
					</div>';
			}

			return $content;
		}
	}

	/**
	 * Prints a single FAQ item with link to editing.
	 *
	 * @param	array		The FAQ item record
	 * @param	boolean		If set it allows the form to place a link back to the original thread from which is sprung originally.
	 * @return	string		HTML output returned.
	 */
	function printSingleFaqItems($record,$single=0)	{
		$content='';
		$content.='<p'.$this->pi_classParam('faqsbj').'>'.htmlspecialchars($record['subject']).'</p>';

		$content.='<p'.$this->pi_classParam('faqq').'><span'.$this->pi_classParam('faqqhead').'>'.($record['howto']?$this->pi_getLL('printsingl_scenario',''):$this->pi_getLL('printsingl_question','')).'</span><br/> '.nl2br($this->processContent(htmlspecialchars(trim($record['question'])))).'</p>';
		$content.=trim($record['question_pre'])?'<br/><p><strong>'.$this->pi_getLL('printsingl_codeListing','').'</strong></p><pre'.$this->pi_classParam('faqqpre').'>'.$this->processContent(htmlspecialchars(trim($record['question_pre']))).'</pre>':'';

		$content.='<p'.$this->pi_classParam('faqa').'><span'.$this->pi_classParam('faqahead').'>'.$this->pi_getLL('printsingl_answer','').'</span><br/> '.nl2br($this->processContent(htmlspecialchars(trim($record['answer'])))).'</p>';
		$content.=trim($record['pre'])?'<br/><p><strong>'.$this->pi_getLL('printsingl_codeListing','').'</strong></p><pre'.$this->pi_classParam('faqapre').'>'.$this->processContent(htmlspecialchars(trim($record['pre']))).'</pre>':'';

		$link='';
		$showUserUidPid=intval($this->conf['tx_newloginbox_pi3-showUidPid']);
		if ($GLOBALS['TSFE']->loginUser && ($record['fe_user']==$GLOBALS['TSFE']->fe_user->user['uid'] || isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']])))	{
			$link = ' '.$this->pi_linkTP_keepPIvars($this->pi_getLL('printsingl_edit',''),array('cmd'=>'faq','editFaqUid'=>$record['uid']));
		}
		$content.='<p'.$this->pi_classParam('faqedit').'>'.$this->pi_getLL('printsingl_createdBy','').' '.$this->getUserNameLink($record['fe_user'],$showUserUidPid,'<img src="t3lib/gfx/i/'.(isset($this->mList[$record['fe_user']])?'user2':'fe_users').'.gif" width="18" height="16" border="0" align="absmiddle">').$link.'</p>';
		if ($single && $record['thread'])	{
			$content.='<p'.$this->pi_classParam('faqedit').'>'.$this->pi_linkTP_keepPIvars($this->pi_getLL('renderfaqf_goToOriginalMailing',''),array('mode'=>'','showUid'=>$record['thread'])).'</p>';
		}

		$content='<div'.$this->pi_classParam('faqitem').'>'.$content.'</div>';
		return $content;
	}

	/**
	 * This makes the form for entry of a FAQ item.
	 *
	 * @param	array		The FAQ/HOWTO item record
	 * @param	integer		The uid field of the item record
	 * @return	string		HTML content for the FAQ/HOWTO editing form
	 */
	function renderFAQForm($faqC,$faqUid)	{
		$content='';

			// Subject:
		$content.='<br/>';

		$content.='<p><strong>'.$this->pi_getLL('renderfaqf_type','').'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_selectFaqOrHowto','').'</p>';
		$opt=array();
		$opt[]='<option value="0"'.($faqC['howto']==0?' SELECTED':'').'>'.htmlspecialchars($this->pi_getLL('renderfaqf_faq','')).'</option>';
		$opt[]='<option value="1"'.($faqC['howto']==1?' SELECTED':'').'>'.htmlspecialchars($this->pi_getLL('renderfaqf_howto','')).'</option>';
		$content.='<select name="'.$this->prefixId.'[DATA]['.$faqUid.'][howto]">'.implode('',$opt).'</select><br/>';
		$content.='<br/>';

		$content.='<p><strong>'.$this->pi_getLL('renderfaqf_basicQuestionSubject','').'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_mostLikelyThisIs','').'</p>';
		$content.='<input type="text" name="'.$this->prefixId.'[DATA]['.$faqUid.'][subject]" value="'.htmlspecialchars($faqC['subject']).'" style="'.$this->conf['listView.']['textarea_style'].'" size=80><br/>';
		$content.='<br/>';

		$content.='<p><strong>'.$this->pi_getLL('renderfaqf_detailedQuestionScenario','').'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_pleaseRephraseTheThread','').'</p>';
		$content.='<textarea cols="80" rows="'.t3lib_div::intInRange(count(explode(chr(10),$faqC['question'])),3,20).'" style="'.$this->conf['listView.']['textarea_style'].'" name="'.$this->prefixId.'[DATA]['.$faqUid.'][question]">'.t3lib_div::formatForTextarea($faqC['question']).'</textarea><br/>';
		$content.='<br/>';

		$content.='<p><strong>'.htmlspecialchars($this->pi_getLL('renderfaqf_questionPreCodeField','')).'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_ifTheQuestionIncludes','').'</p>';
		$content.='<textarea cols="80" rows="5" style="'.$this->conf['listView.']['textarea_style'].'" nowrap name="'.$this->prefixId.'[DATA]['.$faqUid.'][question_pre]">'.t3lib_div::formatForTextarea($faqC['question_pre']).'</textarea><br/>';
		$content.='<br/>';

		$content.='<p><strong>'.$this->pi_getLL('renderfaqf_answer','').'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_writeTheFaqAnswer','').'</p>';
		$content.='<textarea cols="80" rows="'.t3lib_div::intInRange(count(explode(chr(10),$faqC['answer'])),3,20).'" style="'.$this->conf['listView.']['textarea_style'].'" name="'.$this->prefixId.'[DATA]['.$faqUid.'][answer]">'.t3lib_div::formatForTextarea($faqC['answer']).'</textarea><br/>';
		$content.='<br/>';

		$content.='<p><strong>'.htmlspecialchars($this->pi_getLL('renderfaqf_answerPreCodeField','')).'</strong></p>';
		$content.='<p>'.$this->pi_getLL('renderfaqf_ifTheAnswerIncludes','').'</p>';
		$content.='<textarea cols="80" rows="5" style="'.$this->conf['listView.']['textarea_style'].'" nowrap name="'.$this->prefixId.'[DATA]['.$faqUid.'][pre]">'.t3lib_div::formatForTextarea($faqC['pre']).'</textarea><br/>';
		$content.='<br/>';

		if (isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{
			$content.='<p><strong>'.$this->pi_getLL('renderfaqf_categoryModerators','').'</strong></p>';
			$content.='<p>'.$this->pi_getLL('renderfaqf_selectTheCategoryWhere','').'</p>';

			$opt=array();
			$opt[]='<option value="0"></option>';
			$opt[]='<option value="-1"'.($faqC['cat']==-1?' SELECTED':'').'>'.$this->pi_getLL('renderfaqf_unmoderatedItems','').'</option>';
			reset($this->categories);
			while(list($catuid,$catrec)=each($this->categories))	{
				$opt[]='<option value="'.$catuid.'"'.($faqC['cat']==$catuid?' SELECTED':'').'>'.htmlspecialchars($catrec['title']).'</option>';
			}
			$content.='<select name="'.$this->prefixId.'[DATA]['.$faqUid.'][cat]">'.implode('',$opt).'</select><br/>';
		} else {
			$content.='<input type="hidden" name="'.$this->prefixId.'[DATA]['.$faqUid.'][cat]" value="'.($faqUid=='NEW' ? -1 : $faqC['cat']).'">';
#			$content.=($faqUid=="NEW" ? -1 : $faqC["cat"]);
		}



		if (isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]) && $faqUid!='NEW')	{
			$content.='<p><input type="checkbox" name="'.$this->prefixId.'[DATA]['.$faqUid.'][hidden]" value="1"> '.$this->pi_getLL('renderfaqf_deleteVisibleForModerators','').'</p>';
		}

		$content='<form action="'.t3lib_div::getIndpEnv('REQUEST_URI').'" method="post" style="margin: 0px 0px 0px 0px;">'.$content.'<br/><input type="submit" name="'.$this->prefixId.'[DATA][_faq]" value="'.$this->pi_getLL('moderatorl_submitFaqHowtoItem','').'"><input type="hidden" name="'.$this->prefixId.'[cmd]" value=""><input type="hidden" name="'.$this->prefixId.'[editFaqUid]" value=""></form>';
		return $content;
	}



















	/***********************************************
	 *
	 * Functions assisting the major display functions (above)
	 *
	 **********************************************/

	/**
	 * Creates the HTML-output for the list of managers and supervisors as shown under the thread archive form.
	 * Will select managers and supervisors from the tt_content fields 'tx_maillisttofaq_moderators' and 'tx_maillisttofaq_supervisors' (and thus requires the plugin to be fired up from a tt_content element)
	 *
	 * @return	string		HTML-output
	 */
	function moderatorList()	{
		$output='';
		$showUserUidPid=intval($this->conf['tx_newloginbox_pi3-showUidPid']);

			# Moderators:
		$moderators=t3lib_div::intExplode(',',$this->cObj->data['tx_maillisttofaq_moderators'].','.$this->cObj->data['tx_maillisttofaq_supervisors']);
		$this->mList=array();
		reset($moderators);
		while(list(,$uid)=each($moderators))	{
			$m=$this->getUserNameLink($uid,$showUserUidPid);
			if ($m)	{
				$this->mList[$uid]=$m;
			}
		}
		if (count($this->mList))	{
			$output.='<p><strong>'.$this->pi_getLL('list_manager_team','List manager team:').'</strong> '.implode(', ',$this->mList).'</p>';
		}
			# Supervisors:
		$supervisors=t3lib_div::intExplode(',',$this->cObj->data['tx_maillisttofaq_supervisors']);
		$this->sList=array();
		reset($supervisors);
		while(list(,$uid)=each($supervisors))	{
			$m=$this->getUserNameLink($uid,$showUserUidPid);
			if ($m)	{
				$this->sList[$uid]=$m;
			}
		}
		if (count($this->sList))	{
			$output.= '<p><strong>'.$this->pi_getLL('supervisors','Supervisors:').'</strong> '.implode(', ',$this->sList).'</p>';
		}

		return $output;
	}

	/**
	 * Returns true if the current user is allowed as manager OR supervisor to manage a certain thread.
	 *
	 * @param	integer		UID of the fe_user which are CURRENTLY managing the thread (zero if no manager has been assigned).
	 * @return	boolean		True if management is OK
	 */
	function canModifyThread($threadModUser)	{
		if ($GLOBALS['TSFE']->loginUser &&
				isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]) &&
				(!$threadModUser || $threadModUser==$GLOBALS['TSFE']->fe_user->user['uid'] || $this->sList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{
					return 1;
		}
	}

	/**
	 * Returns the number of faq items for a _ml root record (thread starter)
	 *
	 * @param	integer		The UID of the thread-starting message from the _ml table.
	 * @return	integer		The number of FAQ items written for this particular thread (found in this PID)
	 */
	function getFaqItemCount($rootUid)	{
		// Printing FAQ items:
		$query = 'SELECT count(*) FROM tx_maillisttofaq_faq WHERE pid='.$this->thisPID.
					' AND thread='.intval($rootUid).
					$this->cObj->enableFields('tx_maillisttofaq_faq');
		$res_faq=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res_faq);
		return $count;
	}

	/**
	 * Processes the mail list content with http:// and email address and search words made into links.
	 *
	 * @param	string		The input string to process for http:// and email addresses.
	 * @return	string		The processed string
	 */
	function processContent($str)	{
		$conf=array();
		$conf['keep']='scheme,path,query';
		$str = $this->cObj->http_makelinks($str,$conf);

		$str = str_replace('@','(at)',$str);
#		$str = str_replace('@','&#'.ord('@').';',$str);

		$srArr = $this->searchWordReplaceArray();
		if (count($srArr))	{
			$str = str_replace($srArr['search'],$srArr['replace'],$str);
		}

		return $str;
	}

	/**
	 * Generates a Search-word replace array based on search words found in piVars[sword]
	 *
	 * @return	array		Array of search words if any are found in piVars[sword]
	 */
	function searchWordReplaceArray()	{
		$srArr=array();
		if ($this->piVars['sword'])	{
			$kw=split('[ ,]',$this->piVars['sword']);

			while(list(,$val)=each($kw))	{
				$val=trim($val);
				if (strlen($val)>=2)	{
					$srArr['search']=$val;
					$srArr['replace']='<span style="color:red;">'.$val.'</span>';
				}
			}
		}
		return $srArr;
	}

	/**
	 * Set rating-star based on input value.
	 *
	 * @param	integer		The rating value. If larger than zero a star-icon is returned and the image text will indicate the rating level.
	 * @return	string		The HTML for the star-icon. If no start, then blank value.
	 */
	function setStar($rating)	{
		if ($rating>0)	{
			return '<img src="'.t3lib_extMgm::siteRelPath('maillisttofaq').'res/star.gif" width="14" height="12" hspace=3 border="0" alt="'.$rating.' votes" title="'.$rating.' votes" align="absmiddle" />';
		}
	}

	/**
	 * Returns a list mode selector, clickmenu in a table.
	 * This function is overriding a function in the parent class, pibase
	 *
	 * @param	array		The elements to put into the mode selector.
	 * @return	string		HTML content for the mode selector.
	 */
	function pi_list_modeSelector($items=array())	{
			// Making menu table:
		$cells=array();
		reset($items);
		while(list($k,$v)=each($items))	{
			$cells[]='<td'.($this->piVars['mode']==$k?$this->pi_classParam('modeSelector-SCell'):'').'><p>'.
				$this->pi_linkTP($v,array($this->prefixId=>array('mode'=>$k,'expThr'=>$this->piVars['expThr']))).
				'</p></td>';
		}

		$sTables = '<div'.$this->pi_classParam('modeSelector').'><table>
			<tr>'.implode('',$cells).'</tr>
		</table></div>';
		return $sTables;
	}

	/**
	 * Returns a results browser (copy from PIbase)
	 *
	 * @param	boolean		$showResultCount: Whether or not to display result counter
	 * @param	string		$tableParams: attributes for the table-tag
	 * @return	string		HTML content
	 */
	function pi_list_browseresults($showResultCount=1,$tableParams="")	{

			// Initializing variables:
		$pointer=$this->piVars["pointer"];
		$count=$this->internal["res_count"];
		$results_at_a_time = t3lib_div::intInRange($this->internal["results_at_a_time"],1,1000);
		$maxPages = t3lib_div::intInRange($this->internal["maxPages"],1,100);
		$max = t3lib_div::intInRange(ceil($count/$results_at_a_time),1,$maxPages);
		$pointer=intval($pointer);
		$links=array();


			// BEGIN. THIS section is added to make the pages-browsing "dynamic"
		$offset=0;
		if ($pointer > $max/2)	{
			$offset=t3lib_div::intInRange($pointer-($max/2),0,t3lib_div::intInRange(ceil($count/$results_at_a_time)-$maxPages,0));
		}
			// END.


			// Make browse-table/links:
		if ($this->pi_alwaysPrev>=0)	{
			if ($pointer>0)	{
				$links[]='<td nowrap><p>'.$this->pi_linkTP_keepPIvars($this->pi_getLL("pi_list_browseresults_prev","< Previous"),array("pointer"=>($pointer-1?$pointer-1:"")),0).'</p></td>';
			} elseif ($this->pi_alwaysPrev)	{
				$links[]='<td nowrap><p>'.$this->pi_getLL("pi_list_browseresults_prev","< Previous").'</p></td>';
			}
		}
		for($a=$offset;$a<($max+$offset);$a++)	{
			$links[]='<td'.($pointer==$a?$this->pi_classParam("browsebox-SCell"):"").' nowrap><p>'.$this->pi_linkTP_keepPIvars(trim($this->pi_getLL("pi_list_browseresults_page","Page")." ".($a+1)),array("pointer"=>($a?$a:"")),$this->pi_isOnlyFields($this->pi_isOnlyFields)).'</p></td>';
		}
		if ($pointer<ceil($count/$results_at_a_time)-1)	{
			$links[]='<td nowrap><p>'.$this->pi_linkTP_keepPIvars($this->pi_getLL("pi_list_browseresults_next","Next >"),array("pointer"=>$pointer+1)).'</p></td>';
		}

		$pR1 = $pointer*$results_at_a_time+1;
		$pR2 = $pointer*$results_at_a_time+$results_at_a_time;
		$sTables = '<DIV'.$this->pi_classParam("browsebox").'>'.
			($showResultCount ? '<P>'.sprintf(
				str_replace("###SPAN_BEGIN###","<span".$this->pi_classParam("browsebox-strong").">",$this->pi_getLL(($this->piVars['mode']==4 || $this->piVars['mode']==5) ? 'pi_list_browseresults_items' : 'pi_list_browseresults_displays')),
				$pR1,
				min(array($this->internal["res_count"],$pR2)),
				$this->internal["res_count"]
				).'</P>':''
			).
		'<'.trim('table '.$tableParams).'>
			<tr>'.implode("",$links).'</tr>
		</table></DIV>';
		return $sTables;
	}

	/**
	 * Makes checkbox for expanded threads. This has an onclick handler which will reload the page with the piVars[expThr] inversed from its current state.
	 *
	 * @return	string		HTML content for the checkbox
	 */
	function expThreadsCheck()	{
		$url = $this->pi_linkTP_keepPIvars_url(array('expThr'=>$this->piVars['expThr']?'':1));

		$content = '<form action="'.t3lib_div::getIndpEnv('REQUEST_URI').'" method="post" style="margin: 0px 0px 0px 0px;">
		<input type="checkbox" name="_" value="" onClick="document.location=unescape(\''.rawurlencode($url).'\');"'.($this->piVars['expThr']?' CHECKED':'').'> '.$this->pi_getLL('getfieldco_expandedThreadsView','').'
		</form>';
		$sTables = '<div'.$this->pi_classParam('expThrCheck').'>'.$content.'</div>';
		return $sTables;
	}

	/**
	 * Returns content for a given field
	 *
	 * @param	string		Fieldname from $this->internal["currentRow"] for which to get the value. The value is formatted.
	 * @return	string		The formatted value.
	 */
	function getFieldContent($fN)	{
		switch($fN) {
			case 'subject':
			case 'moderated_subject':
				return $this->pi_list_linkSingle(htmlspecialchars($this->removeSubjectPrefix($this->internal['currentRow']['moderated_subject']?$this->internal['currentRow']['moderated_subject']:$this->internal['currentRow'][$fN])),$this->internal['currentRow']['uid'],0);
			break;
			case 'mail_date':
				if ($this->internal['currentRow'][$fN])	{
					if (date('d-m-Y',$this->internal['currentRow'][$fN])==date('d-m-Y',time()))	{
						return date('H:i',$this->internal['currentRow'][$fN]);
					} elseif (date('Y',$this->internal['currentRow'][$fN])==date('Y',time())) {
						return date('d-m H:i',$this->internal['currentRow'][$fN]);
					} else {
						return date('d-m-Y H:i',$this->internal['currentRow'][$fN]);
					}
				}
			break;
			case 'all_latest':
				$dateVal = $this->internal['currentRow'][$fN];
				return  $dateVal ? $this->cObj->calcAge(time()-$dateVal,0) : '-';
			break;
			case 'all_replies':
				return htmlspecialchars($this->internal['currentRow'][$fN]?$this->internal['currentRow'][$fN]:'-');
			break;
			case 'sender_name':
				return htmlspecialchars($this->internal['currentRow']['sender_name']?$this->internal['currentRow']['sender_name']:$this->internal['currentRow']['sender_email']);
			break;
			case 'sender_email':
				return $this->cObj->getTypoLink($this->internal['currentRow']['sender_email'],$this->internal['currentRow']['sender_email']);
			break;
			default:
				return htmlspecialchars($this->internal['currentRow'][$fN]);
			break;
		}
	}

	/**
	 * Returns the input string but where the first occurance of $this->subjectPrefix (eg. "[Typo3]") has been removed.
	 *
	 * @param	string		Input string (subject)
	 * @return	string		Output
	 */
	function removeSubjectPrefix($str)	{
		if ($this->subjectPrefix)	{
			$str = trim(implode('',explode($this->subjectPrefix,$str,2)));
		}
		return $str;
	}

	/**
	 * Returns header value for a field
	 *
	 * @param	string		Field name
	 * @return	string		The label for the field header, probably found in locallang file.
	 */
	function getFieldHeader($fN)	{
		switch($fN) {
			default:
				return $this->pi_getLL('listFieldHeader_'.$fN,'['.$fN.']');
			break;
		}
	}

	/**
	 * Returns the header value with sorting-link put on
	 *
	 * @param	string		Field name
	 * @param	string		Alternative label, overriding the one that would otherwise come from ->getFieldHeader()
	 * @return	string		The label for the field header, probably found in locallang file.
	 */
	function getFieldHeader_sortLink($fN,$label='')	{
		$sortStr = $fN.':';
			// If sortField is the same as previously:
		if (!strcmp($fN,$this->internal['orderBy']))	{
			$sortStr.= $this->internal['descFlag']?0:1;
		} else {	// Otherwise set default order depending on fieldtype:
			$sortStr.= t3lib_div::inList('all_replies,all_latest,view_stat,crdate,mail_date',$fN)?1:0;
		}

		return $this->pi_linkTP_keepPIvars($label?$label:$this->getFieldHeader($fN),array('sort'=>$sortStr));
	}















	/***********************************************
	 *
	 * Data processing functions
	 *
	 ***********************************************/

	/**
	 * This function processes the submitted data in all kinds of situations: Management of threads, sending replys, marking an answered as "Saved my day" etc.
	 *
	 * @param	integer		For FAQ/HOWTO items: The PID where to create the FAQ record.
	 * @param	integer		For FAQ/HOWTO items: The reference uid to the mailing list thread from which it was created.
	 * @return	mixed		-1 = OK, otherwise error string
	 */
	function processingOfInData($pid,$rootUid=0)	{
		if (is_array($this->piVars['DATA']))	{
			if ($GLOBALS['TSFE']->loginUser)	{
					// Saved my day...
				if ($this->piVars['DATA']['answerSavedDay'])	{
					$savedDayUid=$this->piVars['DATA']['answerSavedDay'];
					if (t3lib_div::testInt($savedDayUid))	{
						$savedDayRec=$this->pi_getRecord('tx_maillisttofaq_ml',$savedDayUid);
						if (is_array($savedDayRec))	{
							$dataArr=array('rating'=>$savedDayRec['rating']+1);
							$query = $this->cObj->DBgetUpdate('tx_maillisttofaq_ml', intval($savedDayUid), $dataArr, 'rating');
							$res = mysql(TYPO3_db,$query);
							$this->updateThread($savedDayUid);
							return -1;
						}
					}
				}

					// FAQ entry:
				if ($this->piVars['DATA']['_faq'])	{
					if (is_array($this->piVars['DATA']['NEW']))	{
						$dataArr = $this->piVars['DATA']['NEW'];
						$dataArr['thread']=$rootUid;
						$dataArr['fe_user']=$GLOBALS['TSFE']->fe_user->user['uid'];
						$query = $this->cObj->DBgetInsert('tx_maillisttofaq_faq', $pid, $dataArr, 'subject,question,question_pre,answer,pre,fe_user,cat,thread,howto');
						$res = mysql(TYPO3_db,$query);
						if (mysql_error())	{
							return mysql_error();
						} else return -1;
					} else {
						reset($this->piVars['DATA']);
						$uid = key($this->piVars['DATA']);
						$faqC=$this->pi_getRecord('tx_maillisttofaq_faq',$uid);
						if (is_array($faqC))	{
							if ($faqC['fe_user']==$GLOBALS['TSFE']->fe_user->user['uid'] || isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{	// Only owners and the moderators can edit faq-items.
								$dataArr = $this->piVars['DATA'][$uid];
								$dataArr['last_edited_by']=$GLOBALS['TSFE']->fe_user->user['uid'];
								if (!isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{
									unset($dataArr['hidden']);
								}

								$query = $this->cObj->DBgetUpdate('tx_maillisttofaq_faq', $uid, $dataArr, 'subject,question,question_pre,answer,pre,last_edited_by,cat,hidden,howto');
								$res = mysql(TYPO3_db,$query);
								if (mysql_error())	{
									return mysql_error();
								} else return -1;
							} else return 'ERROR: You did not have rights to edit this item';
						} else return 'ERROR: Could not find faq-record.';
					}
				}
					// Moderation
				if ($this->piVars['DATA']['_moderate'] || $this->piVars['DATA']['_moderate_return'])	{
					$UPDATE_TH=0;

						// Get 'clipboard'
					$clip = $GLOBALS['TSFE']->fe_user->getKey('ses','tx_maillisttofaq_clip');

					$break_threads=array();
					reset($this->piVars['DATA']);
					while(list($uid,$recContent)=each($this->piVars['DATA']))	{
						if (is_array($recContent) && t3lib_div::testInt($uid))	{
							if (isset($this->mList[$GLOBALS['TSFE']->fe_user->user['uid']]))	{	// Only moderators. However it does not check for ownership since that requires the real record... But not so important...
								$dataArr = $recContent;
								unset($dataArr['parent']);
								unset($dataArr['reply']);

									// If the 'moderated_subject' is no different from the original subject, then it is not written into the field.
								if (!strcmp($dataArr['subject'],$dataArr['moderated_subject']))	{
									$dataArr['moderated_subject']='';
								}
								unset($dataArr['subject']);

									// Send FAQ email:
								if ($dataArr['faq_email_sent'] && t3lib_div::validEmail($dataArr['faq_email_sent']))	{
									$postMsg=array();

									$postMsg['email']=$this->conf['faq_email.']['admin_email'] ? $this->conf['faq_email.']['admin_email'] : $GLOBALS['TSFE']->fe_user->user['email'];
									$postMsg['name']=$GLOBALS['TSFE']->fe_user->user['name'].' - '.$this->conf['faq_email.']['admin_name'];
									$postMsg['subject']=trim($this->pi_getLL('faq_email_subj'));
									$postMsg['cc_email']=$this->conf['faq_email.']['cc_email'];

									$theMSGcontent = unserialize($dataArr['_DAT_faq_email_sent']);
									$theMSG = sprintf(trim($this->pi_getLL('faq_email_msg')),
										$theMSGcontent['name'],
										$theMSGcontent['question'],
										$theMSGcontent['listmgr'],
										$theMSGcontent['mainUrl'],
										$this->pi_getLL('makenewfaq'),
										$theMSGcontent['faqUrl'],
										$this->conf['faq_email.']['regards']
									);
									$postMsg['msg'] = $theMSG;

									$this->sendReplyMail($postMsg,$dataArr['faq_email_sent']);

									$dataArr['faq_email_sent'].=' '.date('d-m-Y H:i');
#$dataArr['faq_email_sent']='';
								} else unset($dataArr['faq_email_sent']);

									// break;
								if ($recContent['_break'])	{
									$break_threads[]=$uid;
									$dataArr['parent']=0;
									$dataArr['reply']=0;
								}

									// move;
								if ($recContent['_move'])	{
									$clip[$uid]=$recContent['_move'];
								} else unset($clip[$uid]);

									// paste
								if ($recContent['_paste'])	{
									$uidToMove = intval($recContent['_paste']);
									$rootLineUidsFromHere = $this->getRootMessage($uid);
#				debug(array($uidToMove,$rootLineUidsFromHere,$uid));
									if (!t3lib_div::inList($rootLineUidsFromHere,$uidToMove))	{
										$uidToMove_rec = $GLOBALS['TSFE']->sys_page->getRawRecord('tx_maillisttofaq_ml',$uidToMove);
										if (is_array($uidToMove_rec))	{
												// So, update the parent field if it is allowed:
											$query="UPDATE tx_maillisttofaq_ml SET parent=".intval($uid)." WHERE uid=".intval($uidToMove);
											$res = mysql(TYPO3_db,$query);
												// ... and update that thread.
											$this->updateThread($uidToMove);
#debug('UPDATE THR:'.$uidToMove);
												// Then, check if the original parent value was set - if so that thread needs to be updated as well.
											if ($uidToMove_rec['parent'])	{
												$this->updateThread($uidToMove_rec['parent']);
#debug('UPDATE THR:'.$uidToMove_rec['parent']);
											}

											unset($clip[$uidToMove]);
										} else return 'ERROR: Could not find the record of the mail you are trying to paste... strange.';
									} else return 'ERROR: You tried to paste a message (#'.$recContent['_paste'].') as a reply to itself (or sub-reply found in list "'.$rootLineUidsFromHere.'")';
								}

								if ($recContent['_sendAsReply'] && $this->listEmail)	{
									$replyToMsg = $GLOBALS['TSFE']->sys_page->getRawRecord('tx_maillisttofaq_ml',$uid);

									$fakeReply=array();
									$fakeReply['email']=$GLOBALS['TSFE']->fe_user->user['email'];
									$fakeReply['name']=$GLOBALS['TSFE']->fe_user->user['name'];
									$fakeReply['subject']='Re: '.$replyToMsg['subject'];
									if (trim($this->subjectPrefix))	{
										list(,$_subject) = explode($this->subjectPrefix,$fakeReply['subject'],2);
										if ($_subject)	$fakeReply['subject']='Re: '.trim($this->subjectPrefix).' '.trim($_subject);
									}


									$fakeReply['message_id']=$replyToMsg['message_id'];
									$fakeReply['cc_email']=$replyToMsg['sender_email'];
									$fakeReply['msg']=str_replace('//',chr(10),$recContent['moderator_note']).'

'.$this->pi_getLL('mgrNoticeFooter').'
';
									$this->sendReplyMail($fakeReply);
									unset($dataArr['moderator_note']);
								}
#debug($dataArr);

								$dataArr['content_lgd']=strlen($dataArr['content']);
								$query = $this->cObj->DBgetUpdate('tx_maillisttofaq_ml', intval($uid), $dataArr, 'moderator_note,ot_flag,moderator_fe_user,reply,parent,moderated_subject,cat,rating,sticky,answer_state,faq_email_sent,content_lgd');
								$res = mysql(TYPO3_db,$query);
								if (mysql_error())	{
									return mysql_error();
								} else {
									$query="UPDATE tx_maillisttofaq_mlcontent SET content='".addslashes($dataArr['content'])."' WHERE ml_uid=".intval($uid);
									$res = mysql(TYPO3_db,$query);
									if (mysql_error())	{
										return mysql_error();
									} elseif (!$UPDATE_TH) {
										$UPDATE_TH=$uid;	// First item...
									}
								}
							} else return 'ERROR: YOU were not a moderator!';
						}
					}

						// Store clipboard
					$GLOBALS['TSFE']->fe_user->setKey('ses','tx_maillisttofaq_clip',$clip);

						// Updating threads.
					if ($UPDATE_TH)	{
						$this->updateThread($UPDATE_TH);
						if (count($break_threads))	{
							reset($break_threads);
							while(list(,$brthuid)=each($break_threads))	{
								$this->updateThread($brthuid);
							}
						}
						return -1;
					}
				}

					// REPLY to mail:
				if ($this->piVars['DATA']['_send_reply'] && trim($this->piVars['DATA']['reply']['email']) && $this->listEmail)	{
					return $this->sendReplyMail($this->piVars['DATA']['reply']);
				}
			} else return 'ERROR: No fe-user.';
		}
	}

	/**
	 * Sending a mail / reply to the list.
	 *
	 * @param	array		Array with the data to send
	 * @param	[type]		$altEmail: ...
	 * @return	string		Error message if applicable. Otherwise void
	 */
	function sendReplyMail($replyArray,$altEmail='')	{
		if (trim($replyArray['subject']))	{
			$enc='quoted-printable';
			$charset='ISO-8859-1';
			$headersArr=array();
			$sender = trim($replyArray['name'].' <'.trim($replyArray['email']).'>');
			$headersArr[]='From: '.$sender;
			$headersArr[]='Return-Path: <'.trim($replyArray['email']).'>';
			if (trim($replyArray['message_id']))	{
				$headersArr[]='In-Reply-To: '.trim($replyArray['message_id']);
				$headersArr[]='References: '.trim($replyArray['message_id']);
			}
			$headersArr[]='X-Mailer: TYPO3 maillisttofaq extension';
			$recip = ($altEmail?$altEmail:$this->listEmail).
						($replyArray['cc_email']?','.$replyArray['cc_email']:'').
						($replyArray['cc']?','.$replyArray['email']:'');

				// Seeing if the subject must be QP'ed (that is testing if any QP is added to string and if so ONLY QP the part of the string that is affected - otherwise the mailman software might not remove any [Typo3] prefixes because it apparently doesn't decode the subject!)
			$temp_subject=explode('=',t3lib_div::quoted_printable($replyArray['subject'],1000),2);
			$finalSubject = $temp_subject[0];	// First part - no problem
			if (strlen($temp_subject[1]))	{	// Second part - if exists, then do something.
				$finalSubject.= '=?'.$charset.'?Q?'.t3lib_div::quoted_printable(ereg_replace('[[:space:]]','_',substr($replyArray['subject'],strlen($temp_subject[0]))),1000).'?=';
			}

/*			debug(array(
				$recip,
				trim($finalSubject),
				$replyArray['msg'],
				implode(chr(10),$headersArr),
				$enc,
				$charset,
				1
			));
*/			t3lib_div::plainMailEncoded(
				$recip,
				trim($finalSubject),
				$replyArray['msg'],
				implode(chr(10),$headersArr),
				$enc,
				$charset,
				1
			);

#					debug('Email: sent to '.$recip);
#					debug($this->piVars['DATA']);
		} else return 'ERROR: You did not specify a subject! No mail was sent.';
	}

	/**
	 * This takes a uid of a _ml record and will update the whole thread with new "cached" information (like last-entry, number of items etc).
	 * Call this function when a new record has been inserted into the thread or if something else has changed.
	 *
	 * @param	integer		UID of any element in a thread.
	 * @return	void
	 */
	function updateThread($itemUid)	{
		// Get root item:
		$rootItem = intval($this->getRootMessage($itemUid));
		if ($rootItem)	{
			$rootRecord = $this->pi_getRecord('tx_maillisttofaq_ml',$rootItem);

				// Getting children:
			$result=array();
			$children = $this->getChildren($rootItem,$result,'uid,mail_date,subject,sender_name,sender_email,ot_flag,rating,moderator_fe_user,fe_user',1,0);

				// Going through the children:
			$all_content='';
			$uidList=array();
			$mailDate=array();
			$replyUsers=array();
			$replyCount=0;
			$ratingAccum=0;
			$modStatus=0;


			$mailDate[]=$rootRecord['mail_date'];
			$all_content.=' '.$rootRecord['subject'];
			$all_content.=' '.$rootRecord['sender_name'];
			$all_content.=' '.$rootRecord['sender_email'];
			$all_content.=' '.$this->getContentForMLitem($rootItem);

			reset($result);
			while(list(,$resRec)=each($result))	{

				if (!$resRec['ot_flag']) $replyCount++;
				if (!$resRec['moderator_fe_user'])	$modStatus--;
				$ratingAccum+=$resRec['rating'];

				$uidList[]=$resRec['uid'];
				if ($resRec['fe_user'])	{$replyUsers[]=intval($resRec['fe_user']);}
				$mailDate[]=$resRec['mail_date'];
				$all_content.=' '.$resRec['subject'];
				$all_content.=' '.$resRec['sender_name'];
				$all_content.=' '.$resRec['sender_email'];
				$all_content.=' '.$this->getContentForMLitem($resRec['uid']);
#debug($resRec);
			}

			if (!$rootRecord['moderator_fe_user'])	{
				$modStatus=0;
			} elseif ($rootRecord['moderator_fe_user'] && !$modStatus)	{
				$modStatus=1;
			}

			$replyUserList = count($replyUsers) ? ','.implode(',',array_unique($replyUsers)).',' : '';
#debug($replyUserList);
#debug($rootRecord);
#debug($mailDate);
#debug(array($all_content,$mailDate));
				// UPDATE root record:
			$query="UPDATE tx_maillisttofaq_ml SET
				moderator_status=".$modStatus.",
				all_useruidlist='".$replyUserList."',
				all_replies=".$replyCount.",
				all_rating=".$ratingAccum.",
				all_latest=".intval(count($mailDate)?max($mailDate):0)." WHERE uid=".$rootItem;
			$res2=mysql(TYPO3_db,$query);
			$query="UPDATE tx_maillisttofaq_mlcontent SET all_content='".addslashes($all_content)."' WHERE ml_uid=".$rootItem;
			$res2=mysql(TYPO3_db,$query);

				// UPDATE children:
			if (count($uidList))	{
				$query="UPDATE tx_maillisttofaq_ml SET moderator_status=".$modStatus.", reply=1, all_replies=".$replyCount.", all_latest=".intval(max($mailDate))." WHERE uid IN (".implode(',',$uidList).")";
				$res2=mysql(TYPO3_db,$query);
				$query="UPDATE tx_maillisttofaq_mlcontent SET all_content='' WHERE ml_uid IN (".implode(',',$uidList).")";
				$res2=mysql(TYPO3_db,$query);
			}
		}
	}

	/**
	 * Updates the view-stat field of _ml or _faq records
	 *
	 * @param	string		$type: If "faq" then the faq-table is updated. Default is "_ml" table.
	 * @param	integer		$uid: The UID of the element for which to increase the view-count
	 * @param	integer		$currentCount: The current view-stat value. This is re-written to the record after being increased by one.
	 * @return	void
	 */
	function updateViewStat($type,$uid,$currentCount)	{
		$didReg = $GLOBALS['TSFE']->fe_user->getKey('ses','tx_maillisttofaq_view_stat');
		if (!isset($didReg[$type.$uid]))	{
				// ..
			$didReg[$type.$uid]=1;
			$GLOBALS['TSFE']->fe_user->setKey('ses','tx_maillisttofaq_view_stat',$didReg);
				// Update:
			$query = 'UPDATE '.
					($type=='faq'?'tx_maillisttofaq_faq':'tx_maillisttofaq_ml').
					' SET view_stat='.intval($currentCount+1).
					' WHERE uid='.intval($uid);
			$res = mysql(TYPO3_db,$query);
#debug(array($query));
		}
	}

	/**
	 * Get sticker-elements: Loads the internal array $this->stickingElements with "ml_uid"/"uid" pairs for the CURRENT fe_user
	 *
	 * @return	void
	 */
	function getSticking()	{
		$this->stickingElements=array();
		$query='SELECT * FROM tx_maillisttofaq_ml_stick WHERE fe_user='.intval($GLOBALS['TSFE']->fe_user->user['uid']);
		$res=mysql(TYPO3_db,$query)	;
		while($row=mysql_fetch_assoc($res))	{
			$this->stickingElements[$row['ml_uid']]=$row['uid'];
		}
	}

	/**
	 * Manage sticker-elements for the current logged in user. Expect a form to be sent with the [DATA][stick] input being an array with thread starters marked for sticking.
	 *
	 * @return	void
	 */
	function manageSticking()	{
		if (is_array($this->piVars['DATA']) && $this->piVars['DATA']['stick'] && $GLOBALS['TSFE']->loginUser)	{
			$stickParts = t3lib_div::intExplode(':',$this->piVars['DATA']['stick']);

				// Just delete any item:
			$query='DELETE FROM tx_maillisttofaq_ml_stick WHERE fe_user='.intval($GLOBALS['TSFE']->fe_user->user['uid']).
						' AND ml_uid='.intval($stickParts['0']);
			$res=mysql(TYPO3_db,$query)	;
				// SET stick-element if that is what is asked for:
			if ($stickParts[1])	{
				$query='INSERT INTO tx_maillisttofaq_ml_stick (fe_user,ml_uid) VALUES ('.intval($GLOBALS['TSFE']->fe_user->user['uid']).','.intval($stickParts['0']).')';
				$res=mysql(TYPO3_db,$query)	;
			}
		}
	}

	/**
	 * Get categories from storage folder loaded into internal array, $this->categories
	 *
	 * @return	void
	 */
	function getFAQCategories()	{
		$d=$GLOBALS['TSFE']->getStorageSiterootPids();
		$storagePID = intval($d['_STORAGE_PID']);

		$query = 'SELECT * FROM tx_maillisttofaq_faqcat WHERE pid='.$storagePID.
				$this->cObj->enableFields('tx_maillisttofaq_faqcat').
				' ORDER BY title';

		$this->categories=array();

		$res = mysql(TYPO3_db,$query);
		while($row=mysql_fetch_assoc($res))	{
			$this->categories[$row['uid']]=$row;
		}
	}

	/**
	 * Select online users from storage folder and into internal array, $this->onlineUsers, where key is 'uid' and value is the users name (looked up by $this->getUsersNameLink)
	 * Online users are users with their 'is_online' value set within the last 10 minutes (default)
	 *
	 * @return	void
	 */
	function getOnlineUsers()	{
		$d=$GLOBALS['TSFE']->getStorageSiterootPids();
		$storagePID = intval($d['_STORAGE_PID']);

		$showUserUidPid=intval($this->conf['tx_newloginbox_pi3-showUidPid']);

		$query = 'SELECT uid,username FROM fe_users WHERE is_online>'.(time()-60*10).
				$this->cObj->enableFields('fe_users').
				' ORDER BY is_online DESC';

		$this->onlineUsers=array();
		$res = mysql(TYPO3_db,$query);
		while($row=mysql_fetch_assoc($res))	{
#			$this->onlineUsers[$row['uid']]=$row['username'];
			$m=$this->getUserNameLink($row['uid'],$showUserUidPid);
			if ($m)	{
				$this->onlineUsers[$row['uid']]=$m;
			}
		}
	}

	/**
	 * This will go back in the "root-line" of any message in the _ml table and return the id-list of elements found in the rootline.
	 * Use "intval()" on the output to get the uid of the root-record!
	 *
	 * @param	integer		UID from _ml table to get parent root line for.
	 * @param	integer		Max levels - a security for not ending in endless recursivity
	 * @return	string		Comma list of UIDs
	 */
	function getRootMessage($parent_uid,$maxLevels=50)	{
		if ($maxLevels<=0)	return '';

		$query='SELECT parent FROM tx_maillisttofaq_ml WHERE uid='.intval($parent_uid);
		$res2=mysql(TYPO3_db,$query);
		if ($row2=mysql_fetch_assoc($res2))	{
			if ($row2['parent']>0)	{
				return $this->getRootMessage($row2['parent'],$maxLevels-1).','.$parent_uid;
			} else {
				return $parent_uid;
			}
		}
	}

	/**
	 * This gets the child records for a record in the _ml table.
	 * The return value is a table with the structure hierarchically arranged.
	 * The pass-by-reference array $result will have the same records but in a plain list.
	 * Designed for recursive calling
	 *
	 * @param	integer		The parent record UID to get children for.
	 * @param	array		Flat list of records
	 * @param	string		List of fields to select and include in return value.
	 * @param	integer		Level indicator. Will be counted up for each recursive call.
	 * @param	mixed		I have NO idea - is not used!
	 * @param	boolean		If set, then only records with the "ot_flag" not set will be selected.
	 * @return	array		Rows selected in an array-structure.
	 */
	function getChildren($parent_uid,&$result,$fields='uid',$level=1,$enFields=1,$otCheck=0)	{
		if (intval($parent_uid)<=0)	return;

		$rows=array();
		$query='SELECT '.$fields.' FROM tx_maillisttofaq_ml WHERE parent='.intval($parent_uid).
			' AND pid="'.$this->thisPID.'"'.
			($otCheck ? ' AND ot_flag=0' : '').
			$this->cObj->enableFields('tx_maillisttofaq_ml');
		$res2=mysql(TYPO3_db,$query);
		while ($row2=mysql_fetch_assoc($res2))	{
			$result[$row2['uid']]=$row2;
			$result[$row2['uid']]['_LEVEL']=$level;

			$rows[$row2['uid']]=$row2;
			$sub=$this->getChildren($row2['uid'],$result,$fields,$level+1,$enFields,$otCheck);
			if (count($sub))	$rows[$row2['uid']]['SUB']=$sub;
		}
		return $rows;
	}

	/**
	 * Get content for a _ml element
	 *
	 * @param	integer		UID of the ml-item to get content for.
	 * @return	string		Content for the item given by parameter.
	 */
	function getContentForMLitem($uid)	{
		$query='SELECT content FROM tx_maillisttofaq_mlcontent WHERE ml_uid='.intval($uid);
		$res = mysql(TYPO3_db,$query);
		$row=mysql_fetch_assoc($res);
		return $row['content'];
	}

	/**
	 * Returns the user name of a fe_users.uid
	 *
	 * @param	integer		UID of the fe_user to find the name for.
	 * @param	integer		PID of the page where details for this user is displayed
	 * @param	string		Any string before the username. Is used here to put an icon before the name.
	 * @return	string		A HTML-string with the username linked, and prefixed, ready for output.
	 */
	function getUserNameLink($fe_users_uid,$showUserUidPid=0,$prefix='')	{
		if ($fe_users_uid)	{
			if (!isset($this->cache_fe_user_names[$fe_users_uid]))	{
				$R_URI = t3lib_div::getIndpEnv('REQUEST_URI');

				$fe_user_rec = $this->pi_getRecord('fe_users', $fe_users_uid);
				$this->cache_fe_user_names[$fe_users_uid] = $prefix.($showUserUidPid ? $this->pi_linkToPage($fe_user_rec['username'],$showUserUidPid,'',array('tx_newloginbox_pi3[showUid]' => $fe_user_rec['uid'], 'tx_newloginbox_pi3[returnUrl]'=>$R_URI)) : $fe_user_rec['username']);
			}
			return $this->cache_fe_user_names[$fe_users_uid];
		}
	}















	/***********************************************
	 *
	 * MAIL transfer functions:
	 *
	 ***********************************************/

	/**
	 * This will select the [$number] most recent mails from the 'inbox' - the 'inmail' table.
	 *
	 * @param	integer		Number of mails to select at a time.
	 * @return	void
	 */
	function transferMailsFromInBox($number=10)	{
			// Make query:
		$whereCl=$this->selectField."='".$this->selectValue."'";
		$query = 'SELECT * FROM tx_maillisttofaq_inmail WHERE '.$whereCl.' AND NOT deleted ORDER BY uid LIMIT '.intval($number);

			// Find storage PID
		$d=$GLOBALS['TSFE']->getStorageSiterootPids();
		$this->storagePID = intval($d['_STORAGE_PID']);

			// Selecting:
		$res = mysql(TYPO3_db,$query);
		echo mysql_error();

			// Storing result in archive table:
		$c=0;
		$this->mailParser = t3lib_div::makeInstance('t3lib_readmail');
		while($row=mysql_fetch_assoc($res))	{
			$this->storeMailInMLtable($row);
			$c++;
		}

		$GLOBALS['TT']->setTSlogMessage('Transferring '.$number.' mails from "inmail" table ('.$c.' selected)');
		$GLOBALS['TT']->setTSlogMessage('Query: '.$query);

			// Now, looking for mails which did not arrive in the right order and therefore did not correctly relate to a thread.
		$this->collectOrphans();
	}

	/**
	 * This takes a record from the _inmail table and parses the content, puts it in relation to the records in the _ml table
	 *
	 * @param	array		Record from "inmail" table.
	 * @return	void
	 * @internal
	 */
	function storeMailInMLtable($row)	{
			// Gets the total parsed mail with everything (in an array):
		$fullDecodedMailParts=$this->mailParser->fullParse($row['mailcontent']);
#debug($fullDecodedMailParts);

			// Beginning to put trivial information in the content array:
		$mlRow=array();
		$mlRow_content=array();
		$mlRow['subject']=$fullDecodedMailParts['subject'];
		$mlRow['sender_email']=$fullDecodedMailParts['_FROM']['email'];
		$mlRow['sender_name']=$fullDecodedMailParts['_FROM']['name'];
		$mlRow['message_id']=$fullDecodedMailParts['message-id'];
		$mlRow['message_id_hash']=md5($mlRow['message_id']);
		$mlRow['mail_date']=$fullDecodedMailParts['_DATE'];
		$mlRow['raw_mail_uid']=$row['uid'];
		$mlRow['pid']=$this->thisPID;


			// Find fe_user of the sender_email
		if ($mlRow['sender_email'])	{
			$query = 'SELECT uid FROM fe_users WHERE pid='.intval($this->storagePID).' AND email="'.addslashes($mlRow['sender_email']).'"';
			$res2 = mysql(TYPO3_db,$query);
			echo mysql_error();
			if($row2=mysql_fetch_assoc($res2))	{
				$mlRow['fe_user']=$row2['uid'];
			}
		}

			// Finding plain text content from mime (or not) message
		$mlRow_content['content'] = $this->getPlainTextContentOut($fullDecodedMailParts['CONTENT']);
		$mlRow['content_lgd'] = strlen($mlRow_content['content']);

			// -------------------------------------
			// Searching for relation/reply parent.
			// ---
		$TRYED=0;
		$mlRow['reply']=0;
			// First, try the 'in-reply-to' and 'references' header values:
		$items = array_unique(array_reverse(t3lib_div::trimExplode(' ',$fullDecodedMailParts['references'].' '.$fullDecodedMailParts['in-reply-to'],1)));
		if (count($items))	{
			$mlRow['references_list']=implode(' ',$items);
			$reply = $this->searchForParent($items);
			$mlRow['reply'] = t3lib_div::intInRange($reply,-1,1);
			if ($reply>0)	$mlRow['parent']=$reply;
			$TRYED=1;
		} elseif ($fullDecodedMailParts['thread-topic']) {	// .. if that didn't work out the 'thread-topic' header might point to a valid subject line:
			$query='SELECT uid FROM tx_maillisttofaq_ml WHERE pid="'.$this->thisPID.'" AND subject="'.addslashes($fullDecodedMailParts['thread-topic']).'" ORDER BY uid DESC LIMIT 1';
			$res2=mysql(TYPO3_db,$query);
			if ($row2=mysql_fetch_assoc($res2))	{
				$mlRow['parent']=$row2['uid'];
				$mlRow['reply']=1;
			}
			$TRYED=1;
		}

			// Subject-line prefix/detection:
			// If there was NOT found a reference earlier then we use the subject line AFTER the string $this->subjectPrefix to look up the nearest possible thread and then attaches the record to that.
		if ($this->conf['enableFinalDesperateTryToLocateThreadBySubject'])	{
			$expStr=$this->subjectPrefix;
			if (!$TRYED && trim($expStr))	{
				list($pre,$subject) = explode($expStr,$fullDecodedMailParts['subject'],2);
				$pre = trim(strtolower($pre));

				if ($pre && (strstr($pre,'re:') || strstr($pre,'aw:')))	{
					$mlRow['reply']=-1;	// Means that this is supposed to be a reply.
					$subject=$expStr.$subject;
					$query='SELECT uid FROM tx_maillisttofaq_ml WHERE pid="'.$this->thisPID.'" AND subject="'.addslashes($subject).'" ORDER BY uid DESC';
					$res2=mysql(TYPO3_db,$query);
					if ($row2=mysql_fetch_assoc($res2))	{
						$mlRow['parent']=$row2['uid'];
						$mlRow['reply']=1;
#	debug('WOW: Found one! '.$mlRow['subject'],1);
					}
				}
			}
		}

			// Insert row:
			// FIRST check if the maillist record HAS already been deleted by another process running the same.
		$query = 'SELECT uid FROM tx_maillisttofaq_inmail WHERE uid="'.$row['uid'].'" AND NOT deleted';
		$nres = mysql(TYPO3_db,$query);
			echo mysql_error();
		if($nrow=mysql_fetch_assoc($nres))	{
				// Then mark it deleted:
			$query = 'UPDATE tx_maillisttofaq_inmail SET deleted=1 WHERE uid="'.$row['uid'].'"';
			$nres = mysql(TYPO3_db,$query);
			echo mysql_error();
				// ...and insert:
			$query = $this->cObj->DBgetInsert('tx_maillisttofaq_ml', $this->thisPID, $mlRow, implode(',',array_keys($mlRow)));
			$nres=mysql(TYPO3_db,$query);
			if (!mysql_error())	{
				$newId = mysql_insert_id();

					// Update content table:
				$query = "INSERT INTO tx_maillisttofaq_mlcontent (content,orig_compr_content,ml_uid) VALUES ('".addslashes($mlRow_content['content'])."', '".addslashes($this->conf['storeCompressedOriginalContent']?gzcompress($mlRow_content['content']):'')."', '".$newId."')";
				$nres=mysql(TYPO3_db,$query);

					// A call to this function will update the whole thread.
				$this->updateThread($newId);
			}
		}
	}

	/**
	 * Looking for mails which did not arrive in the right order and therefore did not correctly relate to a thread (reply=-1)
	 * With such mails we try to look up their relation again. If found we update the mail/thread and all is find ('reply' is then changed to 1). If no thread could be found still, we change reply to '-2' - and thus we will not bother with it anymore.
	 *
	 * @return	string		The plain text content.
	 */
	function collectOrphans()	{
		$query = 'SELECT * FROM tx_maillisttofaq_ml WHERE reply=-1';
		$res = mysql(TYPO3_db,$query);

			// For each of the mails with 'reply'=-1:
		while($row=mysql_fetch_assoc($res))	{
			$items = array_unique(t3lib_div::trimExplode(' ',$row['references_list'],1));
			$mlRow=array();
			$query = '';

				// Looking again for the references:
			$reply = $this->searchForParent($items);
			$mlRow['reply'] = t3lib_div::intInRange($reply,-1,1);
			if ($reply>0)	{
					// REFERENCE FOUND ! Great. Create relation:
				$mlRow['parent']=$reply;
				$query = $this->cObj->DBgetUpdate('tx_maillisttofaq_ml', $row['uid'], $mlRow, implode(',',array_keys($mlRow)));
			} else {
				if ($mlRow['mail_date'] < time()-3600*24)	{	// If no parent mail was found and the mail is older than 24 hours, then mark it to be 'given-up'
					$mlRow['reply']=-2;		// means 'still not attached to something, but now we have given up...'
					$query = $this->cObj->DBgetUpdate('tx_maillisttofaq_ml', $row['uid'], $mlRow, implode(',',array_keys($mlRow)));
				}
			}

				// If a query has been defined, then execute it:
			if ($query)	{
				$res2 = mysql(TYPO3_db,$query);
				$this->updateThread($row['uid']);
#debug($row['subject']);
#debug($mlRow);
			}
		}
	}

	/**
	 * This will try to get some plain text content out of the parsed BODY section of a message.
	 *
	 * @param	mixed		Array or string with mail body content. Normally you pass the value of key ['CONTENT'] from the result of ->fullParse() function
	 * @return	string		The plain text content.
	 */
	function getPlainTextContentOut($cArr)	{
		$output='';
		if (is_array($cArr))	{
			$foundPlain=0;
			foreach ($cArr as $k => $v)	{
				if (strtolower($v['_CONTENT_TYPE_DAT']['_MIME_TYPE'])=='text/plain')	{
					$output=$v['CONTENT'];
					$foundPlain=1;
					break;
				}
			}

			if (!$foundPlain)	{
				$foundHTML=0;
				reset($cArr);
				while(list($k,$v)=each($cArr))	{
					if (strtolower($v['_CONTENT_TYPE_DAT']['_MIME_TYPE'])=='text/html')	{
						$output = $this->htmlToPlain($v['CONTENT']);
						$foundHTML=0;
						break;
					}
				}

				if (!$foundHTML)	{
					reset($cArr);
					while(list($k,$v)=each($cArr))	{
						if (substr(strtolower($v['_CONTENT_TYPE_DAT']['_MIME_TYPE']),0,9)=='multipart')	{
							$output = $this->getPlainTextContentOut($v['CONTENT']);
							break;
						}
					}
				}
			}
		} else {
			if (strtolower($fullDecodedMailParts['_CONTENT_TYPE_DAT']['_MIME_TYPE'])=='text/html')	{
				$output = $this->htmlToPlain($cArr);
			} else {
				$output=$cArr;
			}
		}
		return $output;
	}

	/**
	 * Converts text/html content from HTML-emails to text/plain, basically converting selected entities (eg. '&oslash;' to 'ø')
	 *
	 * @param	string		Input string with entities
	 * @return	string		Output string with entities converted to characters.
	 */
	function htmlToPlain($in)	{
		$srcArr=array('&nbsp;','&gt;','&lt;','&amp;','&quot;','&uuml;','&ouml;','&auml','&aring;','&oslash;','&AElig;');
		$destArr=array(' '    ,'>'   ,'<'   ,'&'    ,'"'     ,'ü'     ,'ö'     ,'ä'    ,'å'      ,'ø'       ,'æ');
		$out = trim(str_replace($srcArr,$destArr,strip_tags($in)));

#		if (ereg('&[a-zA-Z]*;',$out,$reg))	debug($reg);
#		if (ereg('&#([0-9]*);',$out,$reg))	debug($reg);
		return $out;
	}

	/**
	 * This will search for the parent message-id for an array of message-ids (relations + in-reply-to fields)
	 * Example: $items = array_unique(t3lib_div::trimExplode(' ',$fullDecodedMailParts['in-reply-to'].' '.$fullDecodedMailParts['references'],1));
	 *
	 * @param	array		Values are message ids from "references" and/or "in-reply-to" felds
	 * @return	integer		0: Not a reply, since no references are input (empty input array); >0: Parent found, value is the UID of that parent; -1: No parent message was found. Should probably be looked up later or by other means!
	 */
	function searchForParent($items)	{
		$reply=0;

		reset($items);
		while(list(,$value)=each($items))	{
			$value = ereg_replace('[[:space:]]','',$value);	// Some references has been found to have spaces in them, eg "blablabl.d e>" - which is preceived as an error. Probably it is. Not many mails were like this.

			$reply=-1;
			$query="SELECT uid FROM tx_maillisttofaq_ml WHERE pid='".$this->thisPID."' AND message_id_hash='".md5($value)."'";

			$res2=mysql(TYPO3_db,$query);
			if ($row2=mysql_fetch_assoc($res2))	{
				$reply=$row2['uid'];
				break;
			}
		}
		return $reply;
	}












	/***********************************************
	 *
	 * MAIL FEED functions:
	 *
	 ***********************************************/

	/**
	 * Pulls mails off another server and into the inmail-table.
	 * The idea is that the remove inmail table is synchronized with local inmail table.
	 *
	 * @param	array		TypoScript configuration for the "readmail" function.
	 * @return	void
	 */
	function readMails($mconf)	{
			// Find the latest UID:
		$query = 'SELECT uid FROM tx_maillisttofaq_inmail ORDER BY uid DESC LIMIT 0,1';
		$res = mysql(TYPO3_db,$query);
		$row = mysql_fetch_assoc($res);
		$nextAvailableUid = ($row['uid']+1);

			// Prepare URL to select from:
		$p='';
		$p.= '&tx_maillisttofaq_pi1[readMails]='.intval($nextAvailableUid);
		$p.= '&tx_maillisttofaq_pi1[readMails_count]='.intval($mconf['number']);
		$p.= '&tx_maillisttofaq_pi1[readMails_pass]='.addslashes($mconf['password']);
		$p.= '&tx_maillisttofaq_pi1[readMails_compr]='.function_exists('gzcompress');
		$url = $mconf['url'].$p;

			// Read the remote content.
		$GLOBALS["TT"]->setTSlogMessage('READMAIL: Getting content from "'.$url.'"');
		$content = t3lib_div::getUrl($url);

			// Parse result:
		$md5=substr($content,0,32);
		$contentPart=substr($content,35);
		$compression=substr($content,33,1);
		if ($md5 == md5($contentPart))	{	// Checking integrity
			if ($compression && function_exists('gzcompress'))		$contentPart = gzuncompress($contentPart);
			$contentPart = unserialize($contentPart);

				// If we are here the result is well received and we insert the records into the database.
			if (is_array($contentPart['rows']))	{
				foreach($contentPart['rows'] as $mailrow)	{
					$query = 'INSERT INTO tx_maillisttofaq_inmail (uid,mailcontent,from_email,to_email,reply_to_email,sender_email,message_id,subject) VALUES ('.
							'"'.intval($mailrow['uid']).'",'.
							'"'.addslashes($mailrow['mailcontent']).'",'.
							'"'.addslashes($mailrow['from_email']).'",'.
							'"'.addslashes($mailrow['to_email']).'",'.
							'"'.addslashes($mailrow['reply_to_email']).'",'.
							'"'.addslashes($mailrow['sender_email']).'",'.
							'"'.addslashes($mailrow['message_id']).'",'.
							'"'.addslashes($mailrow['subject']).'"'.
						')';
					$res = mysql(TYPO3_db,$query);

					if ($error = mysql_error())	{
						$GLOBALS["TT"]->setTSlogMessage($error.":   ".$query,3);
					}
				}
				$GLOBALS["TT"]->setTSlogMessage('READMAIL: Inserted '.count($contentPart['rows']).' rows in the table tx_maillisttofaq_inmail selected from UID '.$nextAvailableUid);
			} else $GLOBALS["TT"]->setTSlogMessage('READMAIL ERROR: The content received did not contain a "row" array. Content could be corrupt.',3);
			if ($contentPart['remote_msg']) $GLOBALS["TT"]->setTSlogMessage('READMAIL: Remote Server Message: "'.$contentPart['remote_msg'].'"',1);
		} else $GLOBALS["TT"]->setTSlogMessage('READMAIL ERROR: MD5 string and MD5 hash of content did not match.',3);
	}

	/**
	 * Feeding mails to a remote webserver which want records from the "inmail" table
	 * This function will exit brutally (after echoing out the content stream), never returning anything.
	 *
	 * @param	array		TypoScript configuration for the "readmail" function.
	 * @return	void
	 */
	function feedMails($mconf)	{
			// Init data arrays
		$outrows=array();
		$outrows['rows']=array();
		$compression = function_exists('gzcompress') && $this->piVars['readMails_compr'];

			// If feeding is enabled, then select records
		if ($mconf['enableFeed'])	{
				// Check password, if enabled.
			if (!$mconf['password'] || !strcmp($this->piVars['readMails_pass'],$mconf['password']))	{
				$readMailsFrom = intval($this->piVars['readMails']);
				$count = t3lib_div::intInRange($this->piVars['readMails_count'],1,100,10);

				$query = 'SELECT * FROM tx_maillisttofaq_inmail WHERE uid>='.$readMailsFrom.
						' ORDER BY uid LIMIT 0,'.$count;
				$res = mysql(TYPO3_db,$query);
				while($row=mysql_fetch_assoc($res))	{
					$outrows['rows'][$row['uid']]=$row;
				}
				$outrows['remote_msg'] = "OK; compression: ".($compression?'YES':'NO').'; password: '.($mconf['password']?'YES':'NO');
			} else $outrows['remote_msg'] = "Password enabled and did NOT match.";
		} else $outrows['remote_msg'] = "Feed service not available";

			// Prepare out-stream.
		$outstream = serialize($outrows);
		if ($compression)	$outstream = gzcompress($outstream);

		echo md5($outstream).':'.($compression?1:0).':'.$outstream;
		exit;
	}
















	/***********************************************
	 *
	 * Experimental / Development functions
	 *
	 ***********************************************/

	/**
	 * Makes stat over fe_user
	 *
	 * @param	[type]		$fe_user_uid: ...
	 * @return	[type]		...
	 */
	function makeFeUserStat($fe_user_uid)	{
debug($fe_user_uid,1);

			// Counting thread starters
		$query="SELECT count(*) FROM tx_maillisttofaq_ml WHERE fe_user=".intval($fe_user_uid).
				" AND reply<=0".
				$this->cObj->enableFields("tx_maillisttofaq_ml");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("Qs:".$count,1);

			// Counting answers
		$query="SELECT count(*) FROM tx_maillisttofaq_ml WHERE fe_user=".intval($fe_user_uid).
				" AND reply>0".
				$this->cObj->enableFields("tx_maillisttofaq_ml");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("As:".$count,1);

			// Counting answers with stars
		$query="SELECT count(*) FROM tx_maillisttofaq_ml WHERE fe_user=".intval($fe_user_uid).
				" AND reply>0".
				" AND rating>0".
				$this->cObj->enableFields("tx_maillisttofaq_ml");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("Star-As:".$count,1);

			// Managed threads
		$query="SELECT count(*) FROM tx_maillisttofaq_ml WHERE moderator_fe_user=".intval($fe_user_uid).
				" AND reply<=0".
				$this->cObj->enableFields("tx_maillisttofaq_ml");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("Mg:".$count,1);

			// FULLY Managed threads
		$query="SELECT count(*) FROM tx_maillisttofaq_ml WHERE moderator_fe_user=".intval($fe_user_uid).
				" AND reply<=0".
				" AND moderator_status=1".
				$this->cObj->enableFields("tx_maillisttofaq_ml");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("Full-Mg:".$count,1);

			// Counting FAQ items
		$query="SELECT count(*) FROM tx_maillisttofaq_faq WHERE fe_user=".intval($fe_user_uid).
				$this->cObj->enableFields("tx_maillisttofaq_faq");
		$res=mysql(TYPO3_db,$query);
		list($count) = mysql_fetch_row($res);
debug("FAQs:".$count,1);



			// Counting thread starters
		$query="SELECT fe_user,count(*) FROM tx_maillisttofaq_ml WHERE fe_user>0".
				" AND reply<=0".
				$this->cObj->enableFields("tx_maillisttofaq_ml").
				" GROUP BY fe_user";
		$res=mysql(TYPO3_db,$query);
		$items=array();
		while($row=mysql_fetch_assoc($res))	{
			$items[]=$row;
		}
debug(array("ALL_fe_users - thread starters",$items));

			// Counting thread starters
		$query="SELECT fe_user,count(*) FROM tx_maillisttofaq_ml WHERE fe_user>0".
				" AND reply>0".
				$this->cObj->enableFields("tx_maillisttofaq_ml").
				" GROUP BY fe_user";
		$res=mysql(TYPO3_db,$query);
		$items=array();
		while($row=mysql_fetch_assoc($res))	{
			$items[]=$row;
		}
debug(array("ALL_fe_users - answers",$items));

	}
}




if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/maillisttofaq/pi1/class.tx_maillisttofaq_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/maillisttofaq/pi1/class.tx_maillisttofaq_pi1.php']);
}
?>
