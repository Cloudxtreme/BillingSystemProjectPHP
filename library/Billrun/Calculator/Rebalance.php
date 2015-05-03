<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Rebalance
 *
 * @author shani
 */
class Billrun_Calculator_Rebalance extends Billrun_Calculator {

	static protected $type = 'rebalance';

	public function __construct($options = array()) {
		parent::__construct($options);
	}

	public function calc() {
		Billrun_Factory::log()->log("Execute reset", Zend_Log::INFO);

		$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
		$limit = Billrun_Config::getInstance()->getConfigValue('resetlines.limit', 10);
		$offset = Billrun_Config::getInstance()->getConfigValue('resetlines.offset', '1 hour');
		$query = array(
			'creation_date' => array(
				'$lt' => new MongoDate(strtotime($offset . ' ago')),
			),
		);
		$sort = array(
			'creation_date' => 1,
		);
		$results = $rebalance_queue->find($query)->sort($sort)->limit($limit);

		$billruns = array();
		$all_sids = array();
		foreach ($results as $result) {
			$billruns[$result['billrun_key']][] = $result['sid'];
			$all_sids[] = $result['sid'];
		}

		foreach ($billruns as $billrun_key => $sids) {
			$model = new ResetLinesModel($sids, $billrun_key);
			try {
				$ret = $model->reset();
				if (isset($ret['err']) && !is_null($ret['err'])) {
					return FALSE;
				}
				$rebalance_queue->remove(array('sid' => array('$in' => $sids)));
			} catch (Exception $exc) {
				Billrun_Factory::log()->log('Error resetting sids ' . implode(',', $sids) . ' of billrun ' . $billrun_key . '. Error was ' . $exc->getTraceAsString(), Zend_Log::ALERT);
				return $this->setError($exc->getTraceAsString(), array('sids' => $sids, 'billrun_key' => $billrun_key));
			}
		}
		Billrun_Factory::log()->log("Success resetting sids " . implode(',', $all_sids), Zend_Log::INFO);
		return true;
	}

	protected function getLines() {
		return array();
	}

	protected function isLineLegitimate($line) {
		return true;
	}

	public function getCalculatorQueueType() {
		return '';
	}

	public function updateRow($row) {
		return true;
	}

	public function write() {
		
	}

	public function removeFromQueue() {
		
	}

}
