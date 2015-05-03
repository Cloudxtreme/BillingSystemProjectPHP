<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
defined('APPLICATION_PATH') || define('APPLICATION_PATH', dirname(__DIR__));

require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');

$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

try {
	$app->bootstrap()->run();
} catch (Exception $e) {
	$log = print_R($_SERVER, TRUE) . PHP_EOL . print_R('Error code : ' . $e->getCode() . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'Host: ' . gethostname() . PHP_EOL . $e->getTraceAsString(), TRUE); // we don't cast the exception to string because Yaf_Exception could cause a segmentation fault
	Billrun_Factory::log()->log('Crashed When running... exception details are as follow : ' . PHP_EOL . $log, Zend_Log::CRIT);
}
