<?php

namespace HarvestData\Command;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;

class FetchData extends HarvestDataCommand {

	protected function configure() {
		$this
		->setName('HarvestData:FetchData')
		->setAliases(array('data', 'FetchData'))
		->setDescription('Fetch and store data from Harvest based on userdata. Chart-types "singlecolumn", "columnspline", "stackedcolumn" and "piechart" are eligible.');
		
		$this->setChartTypes(array("singlecolumn","stackedcolumn","piechart","columnspline"));
		$this->setChartPeriods(array("day","week","month"));	
			
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

	  $from_date      = $this->getHarvestFromDate("Ymd","yesterday");
	  $to_date        = $this->getHarvestToDate("Ymd", "yesterday");
    $chartType      = $this->getChartType();
    $chartPeriod    = $this->getChartPeriod();
    
    if(!$outputFilename = $input->getOption("output-file")) {
      $outputFilename = 'FetchData-'.$from_date.'-'.$to_date.'.xml';
    }
    
    $output->writeln('FetchData executed: ' . date('Ymd H:i:s'));
 		$output->writeln('Output filename: ' . $outputFilename);
 		if($this->getHarvestExcludeContractors()) $output->writeln('NOTE: Contractors are excluded from the dataset!');
 		$output->writeln(sprintf('Chart type is "%s" and period is "%s"',$chartType,$chartPeriod));
		$output->writeln(sprintf("Collecting Harvest entries between %s to %s",$from_date,$to_date));

    switch ($chartType) {
      case 'singlecolumn':
        $sortedTicketEntries = $this->fetchBillableHoursInPeriod($from_date, $to_date);
        $data = \GeckoChart::makeSingleColumn($sortedTicketEntries, $chartPeriod);
      break;      

      // used for displaying budget vs. actual billable hours
      case 'columnspline';
        $sortedTicketEntries = $this->fetchBillableHoursInPeriod($from_date, $to_date);
        $data = \GeckoChart::makeSingleColumnWithSpline($sortedTicketEntries, $chartPeriod);
      break;
      
      case 'stackedcolumn':
        $assembledEntries = $this->fetchAllHoursInPeriod($from_date, $to_date);
        $data = \GeckoChart::makeStackedColumn($assembledEntries, $chartPeriod);
      break;

      case 'piechart':
        $assembledEntries = $this->fetchAllHoursInPeriod($from_date, $to_date);
        $chartPeriodTitle = date("M. jS",strtotime($from_date)) . " - " . date("M. jS",strtotime($to_date));
        $data = \GeckoChart::makePieChart($assembledEntries, $chartPeriodTitle);
      break;

      
      default:
        $output->writeln("FetchData ChartType not recognized -> ".$chartType."");
        return;
      break;
    }

    // lets write the data to a file
    if($data) {
      $outputFile = new StreamOutput(fopen('data/'.$outputFilename, 'w', false));
      $outputFile->doWrite($data, false);
    	$output->writeln("\nFetchData completed -> ".$outputFilename." updated");      
    }

  }
}