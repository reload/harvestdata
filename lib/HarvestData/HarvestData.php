<?php

namespace HarvestData;

use HarvestData\Command\FetchEntries;
use HarvestData\Command\FetchBillable;
use HarvestData\Command\FetchData;
use HarvestData\Command\ComparePeriods;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Application;

class HarvestData extends \Symfony\Component\Console\Application {

	public function __construct() {
		parent::__construct('HarvestData', '0.4');
		$this->addCommands(array(new FetchEntries()));
		$this->addCommands(array(new FetchBillable()));
		$this->addCommands(array(new FetchData()));
		$this->addCommands(array(new ComparePeriods()));		
	}

}