<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Receiver extends Billrun_Base {

	use Billrun_Traits_FileActions;

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'receiver';

	/**
	 * the receiver workspace path of files
	 * this is the place where the files will be received
	 * 
	 * @var string
	 */
	protected $workspace;

	/**
	 * A regular expression to identify the files that should be downloaded
	 * 
	 * @param string
	 */
	protected $filenameRegex = '/.*/';

	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['filename_regex'])) {
			$this->filenameRegex = $options['filename_regex'];
		}
		if (isset($options['receiver']['limit']) && $options['receiver']['limit']) {
			$this->setLimit($options['receiver']['limit']);
		}
		if (isset($options['receiver']['preserve_timestamps'])) {
			$this->preserve_timestamps = $options['receiver']['preserve_timestamps'];
		}
		if (isset($options['backup_path'])) {
			$this->backupPaths = $options['backup_path'];
		} else {
			$this->backupPaths = Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', array('./backups/' . $this->getType()));
		}
		if (isset($options['receiver']['backup_granularity']) && $options['receiver']['backup_granularity']) {
			$this->setGranularity((int) $options['receiver']['backup_granularity']);
		}
		
		if (Billrun_Util::getFieldVal($options['receiver']['backup_date_fromat'],false) ) {
			$this->setBackupDateDirFromat( $options['receiver']['backup_date_fromat']);
		}
		
		if (isset($options['receiver']['orphan_time']) && ((int) $options['receiver']['orphan_time']) > 900 ) {
			$this->file_fetch_orphan_time =  $options['receiver']['orphan_time'];
		}
	}

	/**
	 * general function to receive
	 *
	 * @return array list of files received
	 */
	abstract protected function receive();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($fileData) {
		$log = Billrun_Factory::db()->logCollection();
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));
		
		$query = array(
			'stamp' =>  $fileData['stamp'],
			'received_time' => array('$exists' => false)
		);
	
		$addData = array(
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => date(self::base_dateformat),
		);

		$update = array(
			'$set' => array_merge($fileData, $addData)
		);

		if (empty($query['stamp'])) {
			Billrun_Factory::log()->log("Billrun_Receiver::logDB - got file with empty stamp :  {$fileData['stamp']}", Zend_Log::NOTICE);
			return FALSE;
		}

		$result = $log->update($query, $update, array('w' => 1));

		if ($result['ok'] != 1 || $result['n'] != 1) {
			Billrun_Factory::log()->log("Billrun_Receiver::logDB - Failed when trying to update a file log record " . $fileData['file_name'] . " with stamp of : {$fileData['stamp']}", Zend_Log::NOTICE);
		}
		
		return $result['n'] == 1 && $result['ok'] == 1;
	}

}
