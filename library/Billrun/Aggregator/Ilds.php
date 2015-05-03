<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Ilds extends Billrun_Aggregator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ilds';

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));

		foreach ($this->data as $item) {
			Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$item, &$this));
			$time = $item->get('call_start_dt');

			// @TODO make it configurable
			$previous_month = date("Ymt235959", strtotime("previous month"));

			if ($time > $previous_month) {
				Billrun_Factory::log()->log("time frame is not till the end of previous month " . $time . "; continue to the next line", Zend_Log::INFO);
				continue;
			}

			// load subscriber
			$phone_number = $item->get('caller_phone_no');
			$subscriber = Billrun_Factory::subscriber()->load(array('NDC_SN' => $phone_number, 'time' => $time));

			if (!$subscriber) {
				Billrun_Factory::log()->log("subscriber not found. phone:" . $phone_number . " time: " . $time, Zend_Log::INFO);
				continue;
			}

			$sid = $subscriber->id;

			// update billing line with billrun stamp
			if (!$this->updateBillingLine($subscriber, $item)) {
				Billrun_Factory::log()->log("subscriber " . $sid . " cannot update billing line", Zend_Log::INFO);
				continue;
			}

			if (isset($this->excludes['subscribers']) && in_array($sid, $this->excludes['subscribers'])) {
				Billrun_Factory::log()->log("subscriber " . $sid . " is in the excluded list skipping billrun for him.", Zend_Log::INFO);
				//mark line as excluded.
				$item['billrun_excluded'] = true;
			}

			$save_data = array();

			//if the subscriber should be excluded dont update the billrun.
			if (!(isset($item['billrun_excluded']) && $item['billrun_excluded'])) {
				// load the customer billrun line (aggregated collection)
				$billrun = $this->loadSubscriberBillrun($subscriber);

				if (!$billrun) {
					Billrun_Factory::log()->log("subscriber " . $sid . " cannot load billrun", Zend_Log::INFO);
					continue;
				}

				// update billrun subscriber with amount
				if (!$this->updateBillrun($billrun, $item)) {
					Billrun_Factory::log()->log("subscriber " . $sid . " cannot update billrun", Zend_Log::INFO);
					continue;
				}

				$save_data[Billrun_Factory::db()->billrun] = $billrun;
			}


			$save_data[Billrun_Factory::db()->lines] = $item;

			Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));

			if (!$this->save($save_data)) {
				Billrun_Factory::log()->log("subscriber " . $sid . " cannot save data", Zend_Log::INFO);
				continue;
			}

			Billrun_Factory::log()->log("subscriber " . $sid . " saved successfully", Zend_Log::INFO);
		}
		// @TODO trigger after aggregate
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
	}

	/**
	 * load the subscriber billrun raw (aggregated)
	 * if not found, create entity with default values
	 * @param type $subscriber
	 *
	 * @return Mongodloid_Entity
	 */
	public function loadSubscriberBillrun($subscriber) {

		$billrun = Billrun_Factory::db()->billrunCollection();
		$resource = $billrun->query()
			//->exists("subscriber.{$subscriber['id']}")
			->equals('aid', $subscriber->aid)
			->equals('stamp', $this->getStamp());

		if ($resource && $resource->count()) {
			foreach ($resource as $entity) {
				break;
			} // @todo make this in more appropriate way
			return $entity;
		}

		$values = array(
			'stamp' => $this->stamp,
			'aid' => $subscriber->aid,
			'subscribers' => array($subscriber->id => array('cost' => array())),
			'cost' => array(),
			'source' => 'ilds',
		);

		return new Mongodloid_Entity($values, $billrun);
	}

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillrun($billrun, $line) {
		// @TODO trigger before update row

		$current = $billrun->getRawData();
		$added_charge = $line->get('aprice');

		if (!is_numeric($added_charge)) {
			//raise an error
			return false;
		}

		$type = $line->get('type');
		$subscriberId = $line->get('sid');
		if (!isset($current['subscribers'][$subscriberId])) {
			$current['subscribers'][$subscriberId] = array('cost' => array());
		}
		if (!isset($current['cost'][$type])) {
			$current['cost'][$type] = $added_charge;
			$current['subscribers'][$subscriberId]['cost'][$type] = $added_charge;
		} else {
			$current['cost'][$type] += $added_charge;
			$subExist = isset($current['subscribers'][$subscriberId]['cost']) && isset($current['subscribers'][$subscriberId]['cost'][$type]);
			$current['subscribers'][$subscriberId]['cost'][$type] = ($subExist ? $current['subscribers'][$subscriberId]['cost'][$type] : 0 ) + $added_charge;
		}

		$billrun->setRawData($current);
		// @TODO trigger after update row
		// the return values will be used for revert
		return array(
			'newCost' => $current['cost'],
			'added' => $added_charge,
		);
	}

	/**
	 * update the billing line with stamp to avoid another aggregation
	 *
	 * @param int $sid the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillingLine($subscriber, $line) {
		if (isset($subscriber->id)) {
			$sid = $subscriber->id;
		} else {
			// todo: alert to log
			return false;
		}
		$current = $line->getRawData();
		$added_values = array(
			'sid' => $sid,
			'billrun' => $this->getStamp(),
		);

		if (isset($subscriber->aid)) {
			$added_values['aid'] = $subscriber->aid;
		}

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		return true;
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {

		$lines = Billrun_Factory::db()->linesCollection();
		$this->data = $lines->query()
				->equals('source', 'ilds')
				->notExists('billrun')
				->exists('pprice')
				->exists('aprice')
				->cursor()->hint(array('source' => 1));

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = Billrun_Factory::db()->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}
