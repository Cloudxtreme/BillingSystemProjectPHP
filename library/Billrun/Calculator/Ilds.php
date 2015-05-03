<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Ilds extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "ilds";

	/**
	 * load the data to run the calculator for
	 * 
	 * @param boolean $initData reset the data in the calculator before loading
	 * 
	 */
	public function load($initData = true) {

		if ($initData) {
			$this->data = array();
		}

		$resource = $this->getLines();

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		Billrun_Factory::log()->log("entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorLoadData', array('calculator' => $this));
	}

	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		//@TODO  change to the  queue system....
		$lines = Billrun_Factory::db()->linesCollection();

		$query = $lines->query()
			->equals('source', static::$type)
			->notExists('aprice');
//			->notExists('pprice'); // @todo: check how to do or between 2 not exists		

		if ($this->limit > 0) {
			$query->cursor()->limit($this->limit);
		}

		return $query;
	}

	/**
	 * Execute the calculation process
	 */
	public function calc() {

		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		foreach ($this->data as $item) {
			$this->updateRow($item);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}

	/**
	 * Execute write down the calculation output
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * Write the calculation into DB
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));

		$current = $row->getRawData();
		$charge = $this->calcChargeLine($row->get('type'), $row->get('call_charge'));
		$added_values = array(
			'aprice' => $charge,
			'pprice' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

	/**
	 * Method to calculate the charge from flat rate
	 *
	 * @param string $type the type of the charge (depend on provider)
	 * @param double $charge the amount of charge
	 * @return double the amount to charge
	 *
	 * @todo: refactoring it by mediator or plugin system
	 */
	protected function calcChargeLine($type, $charge) {
		switch ($type):
			case '012':
			case '015':
				$rating_charge = round($charge / 1000, 3);
				break;

			case '013':
			case '018':
				$rating_charge = round($charge / 100, 2);
				break;
			case '014':
			case '019':
				$rating_charge = round($charge, 3);
				break;
			default:
				$rating_charge = floatval($charge);
		endswitch;
		return $rating_charge;
	}

	protected function getLineRate($row, $usage_type) {
		
	}

	protected function getLineUsageType($row) {
		
	}

	protected function getLineVolume($row, $usage_type) {
		
	}

}
