<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Reset lines action class
 *
 * @package  Action
 * @since    0.5
 */
class ResetLinesAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log()->log("Execute reset", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		if (empty($request['sid'])) {
			return $this->setError('Please supply at least one sid', $request);
		}
		
		// remove the aids from current balance cache - on next current balance it will be recalculated and avoid to take it from cache
		if (isset($request['aid'])) {
			$this->cleanAccountCache($request['aid']);
		}

		$billrun_key = Billrun_Util::getBillrunKey(time());

		// Warning: will convert half numeric strings / floats to integers
		$sids = array_unique(array_diff(Billrun_Util::verify_array($request['sid'], 'int'), array(0)));

		if ($sids) {
			try {
				$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
				foreach ($sids as $sid) {
					$rebalance_queue->insert(array('sid' => $sid, 'billrun_key' => $billrun_key, 'creation_date' => new MongoDate()));
				}
			} catch (Exception $exc) {
				Billrun_Util::logFailedResetLines($sids, $billrun_key);
				return FALSE;
			}
		} else {
			return $this->setError('Illegal sid', $request);
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
		)));
		return TRUE;
	}
	
	/**
	 * method to clean account cache
	 * 
	 * @param int $aid
	 * 
	 * @return true on success, else false
	 */
	protected function cleanAccountCache($aid) {
		$cache = Billrun_Factory::cache();
		if (empty($cache)) {
			return false;
		}
		$aids = array_unique(array_diff(Billrun_Util::verify_array(explode(',', $aid), 'int'), array(0)));
		$billrunKey = Billrun_Util::getBillrunKey(time());
		$cachePrefix = 'balance_'; // this is not the action name because it's clear the balance cache
		foreach ($aids as $aid) {
			$cleanCacheKeys = array(
				Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => array(), 'stamp' => $billrunKey))),
				Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => null, 'stamp' => (int) $billrunKey))),
				Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => "", 'stamp' => (int) $billrunKey))),
				Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => 0, 'stamp' => (int) $billrunKey))),
			);
			foreach ($cleanCacheKeys as $cacheKey) {
				$cache->remove($cacheKey, $cachePrefix);
			}
		}
		return true;
	}

}