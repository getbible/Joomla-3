<?php
/**
* 
* 	@version 	1.0.3  November 25, 2014
* 	@package 	Get Bible API
* 	@author  	Llewellyn van der Merwe <llewellyn@vdm.io>
* 	@copyright	Copyright (C) 2013 Vast Development Method <http://www.vdm.io>
* 	@license	GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
*
**/

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.helper');

class GetbibleModelControl extends JModelList
{
	protected $app_params;
	
	public function __construct() 
	{		
		parent::__construct();
		
		// get params
		$this->app_params = JComponentHelper::getParams('com_getbible');
		
	}
	
	public function getChapters($book_nr,$version)
	{
		if($this->app_params->get('jsonAPIaccess') && $this->app_params->get('jsonQueryOptions') == 1){

			// Get the input data
			$jinput 	= JFactory::getApplication()->input;
			$URLkey 	= $jinput->get('key', NULL, 'ALNUM');
			$APIkey 	= $this->app_params->get('jsonAPIkey');
			$appKey 	= $jinput->get('appKey', NULL, 'ALNUM');
			$token 		= JSession::getFormToken();
	
		} else {
			$URLkey = 'free';
			$APIkey = 'free';
		}
		if ($URLkey == $APIkey || $appKey == $token){
			// Get a db connection.
			$db = JFactory::getDbo();
			
			// Create a new query object.
			$query = $db->getQuery(true);
			
			$query->select('a.chapter_nr');
			$query->from('#__getbible_chapters AS a');		
			$query->where($db->quoteName('a.version') . ' = '.$db->quote($version));		
			$query->where($db->quoteName('a.book_nr') . ' = '.$book_nr);		
			$query->where($db->quoteName('a.access') . ' = 1');
			$query->where($db->quoteName('a.published') . ' = 1');
			$query->order('a.chapter_nr ASC');
				 
			// Reset the query using our newly populated query object.
			$db->setQuery($query);
			 
			// Load the results
			return $db->loadColumn();
		}
		return false;
				
	}
	
	public function getBooks($version)
	{
		if($this->app_params->get('jsonAPIaccess') && $this->app_params->get('jsonQueryOptions') == 1){

			// Get the input data
			$jinput 	= JFactory::getApplication()->input;
			$URLkey 	= $jinput->get('key', NULL, 'ALNUM');
			$APIkey 	= $this->app_params->get('jsonAPIkey');
			$appKey 	= $jinput->get('appKey', NULL, 'ALNUM');
			$token 		= JSession::getFormToken();
	
		} else {
			$URLkey = 'free';
			$APIkey = 'free';
		}
		if ($URLkey == $APIkey || $appKey == $token){
			// get book for this translation
			$savedBooks = GetHelper::getSavedBooks($version);
	
			if(is_array($savedBooks) && count($savedBooks)){
				foreach($savedBooks as $book_nr){
					// load the book details for this translation or that of KJV if none is found
					$books[] = GetHelper::getSetBook($version,$book_nr,true);
				}
				return $books;
			}
		}
		return false;
	}
	
	public function getAppDefaults($search_app = 0, $check_search = 'repent', $defaultVersion = 'kjv', $crit = '1_1_1', $searchType = 'all')
	{		
		if($this->app_params->get('jsonAPIaccess') && $this->app_params->get('jsonQueryOptions') == 1){

			// Get the input data
			$jinput 	= JFactory::getApplication()->input;
			$URLkey 	= $jinput->get('key', NULL, 'ALNUM');
			$APIkey 	= $this->app_params->get('jsonAPIkey');
			$appKey 	= $jinput->get('appKey', NULL, 'ALNUM');
			$token 		= JSession::getFormToken();
	
		} else {
			$URLkey = 'free';
			$APIkey = 'free';
		}
		if ($URLkey == $APIkey || $appKey == $token){
			if($search_app === 1){
				$load 			= (preg_match('~[0-9]~', $check_search) > 0);
				// if number is found in search request load passage
				if($load){
					// load search to get passage
					$passage 		= $check_search;
					// Load default passage
					$defaultPassage	= $this->app_params->get('defaultStartBook'). ' '. $this->app_params->get('defaultStartChapter');
					// get the defaults from request
					if($passage == $defaultPassage){
						$loadDefaults 			= new stdClass();
						$loadDefaults->Book 	= $this->app_params->get('defaultStartBook');
						$loadDefaults->Chapter 	= $this->app_params->get('defaultStartChapter');
					} else {
						$loadDefaults			= $this->getLoadDefaults($passage);
					}
					// load defaults
					$defaults 					= $this->bookDefaults($loadDefaults->Book,$defaultVersion);
					
					if(is_object($defaults)){
						// set defaults to loading request
						$defaults->chapter 		= $loadDefaults->Chapter;
						$defaults->lastchapter	= $defaults->chapter - 1;
						$defaults->load 		= 1;
						return $defaults;
					}
				}
				
				// Load the results
				$result 			= new stdClass();
				$result->search 	= 1;
				$result->searchFor 	= $check_search;
				$result->version 	= $defaultVersion;
				// ensure the criteria is set correctly
				$result->crit 		= (string) preg_replace('/[^0-9_]/i', '', $crit);
				// check to see if its a book name
				$result->type 		= (string) $searchType;
				if($result->type == 'ot' || $result->type == 'nt' || $result->type == 'all'){
					$result->book_ref = NULL;
				} else {
					$result->book_ref = $result->type;
				}
				return $result;
			} else {
				$defaultVersion 		= $this->app_params->get('defaultStartVersion');
				$defaultStartBook 		= $this->app_params->get('defaultStartBook');
				$defaults 				= $this->bookDefaults($defaultStartBook,$defaultVersion);
						
				return $defaults;
			}
		}
		return false;
	}
	
	protected function getLoadDefaults($passage)
	{
		// proces query string
		$passage = (string) preg_replace('/[^A-Z0-9:;, \-]/i', '', $passage);
		$passage = ltrim($passage, '.');
		// strip all other passages we only return one
		if (strpos($passage,';') !== false) {
			$passage = explode(';',$passage);
		} else {
			$passage = array($passage);
		}		
		$ch		= NULL;
		$value	= NULL;
		$value1 = NULL;
		$value2 = NULL;
		$name	= NULL;
		$name2 	= NULL;
		$string = (string) preg_replace('/[^A-Z0-9]/i', '', $passage[0]);
		$value = str_split($string);
		$value1 = array_shift($value);
		$value2 = array_shift($value);
		if (is_numeric($value1) && $value2 && !is_numeric($value2)){
			$num = substr($passage[0], 1);
			$numbers = (string) preg_replace('/[^0-9,:-]/i', '', $num);
			if (strpos($numbers,':') !== false) {
				list($ch,$vers) = explode(':',$numbers);
			} else {
				$ch = (string) preg_replace('/[^0-9]/i', '', $numbers);
			}
			$name = (string) preg_replace('/[^A-Z]/i', '', $passage[0]);
			$name = $value1.$name;
		} else {
			$numbers = (string) preg_replace('/[^0-9,:-]/i', '', $passage[0]);
			if (strpos($numbers,':') !== false) {
				list($ch,$vers) = explode(':',$numbers);
			} else {
				$ch = (string) preg_replace('/[^0-9]/i', '', $numbers);
			}
			$name = (string) preg_replace('/[^A-Z]/i', '', $passage[0]);
		}
		if(strlen($name) == 0){
			$name = $this->app_params->get('defaultStartBook');
		}
		if(1 > $ch){
			$ch = 1;
		}
		$request 			= new stdClass();
		$request->Book 		= $name;
		$request->Chapter 	= $ch;
		
		return $request;
	}
	
	protected function bookDefaults($book,$version)
	{
		// Get a db connection.
		$db = JFactory::getDbo();
		
		// Create a new query object.
		$query = $db->getQuery(true);
		
		$query->select('a.book_nr ,a.book_names');
		$query->from('#__getbible_setbooks AS a');		
		$query->where($db->quoteName('a.access') . ' = 1');
		$query->where($db->quoteName('a.published') . ' = 1');
		$query->where($db->quoteName('a.version') . ' = ' . $db->quote($version));
		$query->where($db->quoteName('a.book_name') . ' = ' . $db->quote($book));
			 
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();
		if($db->getNumRows()){
			// Load the results
			$result 				= $db->loadObject();
			$result->book_ref 		= json_decode($result->book_names)->name2;
			$result->chapter 		= $this->app_params->get('defaultStartChapter');
			$result->lastchapter 	= $this->app_params->get('defaultStartChapter') - 1;
			$result->version 		= $version;
			// remove books from result set
			unset($result->book_names);
			return $result;
		} else {
			return $this->bookSearch($book,$version);
		}
	}
	
	protected function bookSearch($name,$version)
	{
		$found = false;
		if ($name){
			// load all books
			$savedBooks = GetHelper::getSavedBooks($version);						
			$name 		= mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '', $name), 'UTF-8');
			// set query book number and name
			foreach ($savedBooks as $book_nr){
				$book = GetHelper::getSetBook($version,$book_nr,false);
				if(!$found){
					foreach($book->ref as $key => $value){
						if ($value){
							$value = mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value), 'UTF-8');
							if ($name == $value || $book->book_name == $name){
								$book_ref 	= (string) preg_replace('/\s+/', '', $book->ref->name2);
								$book_nr 	= $book->book_nr;
								$found 		= true; 
								break;					
							} else {
								$found 		= false;
							}
						}
					}
				}
				if ($found){
					break;	
				}
			}
		}
		if(!$found){ 
			return false;
		} else {
			// Load the results
			$result 				= new stdClass();
			$result->book_nr		= $book_nr;
			$result->book_ref 		= $book_ref;
			$result->chapter 		= $this->app_params->get('defaultStartChapter');
			$result->lastchapter 	= $this->app_params->get('defaultStartChapter') - 1;
			$result->version 		= $version;
			
			return $result;
		}
	}
}
