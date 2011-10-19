<?php

namespace HarvestData\Command;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;

class FetchBillable extends HarvestDataCommand {

	protected function configure() {
		$this
		->setName('HarvestData:FetchBillable')
		->setAliases(array('billable', 'FetchBillable'))
		->setDescription('Fetch and store data from Harvest. Chart-types "geekometer" and "line" are eligible.');
		
  	$this->setChartTypes(array("geekometer","line"));
  	$this->setChartPeriods(array("day","week","year"));
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

	  $ignore_locked  = false;
	  $from_date      = $this->getHarvestFromDate($input, "Ymd","yesterday");
	  $to_date        = $this->getHarvestToDate($input, "Ymd", "yesterday");
    $chartType      = $this->getChartType($input, "geekometer");
    $chartPeriod    = $this->getChartPeriod($input, "day");	  
	  
/*	  
    $updated_since  = null;  // NULL meeans all projects (and is thereby slow), but it doesnt seem to work correctly if I set the date otherwise
                             // Ahh: http://forum.getharvest.com/forums/api-and-developer-chat/topics/announcement-greater-availability-of-updated_since-filtering-in-api
//    $updated_since  = urlencode($this->getHarvestFromDate($input, "Y-m-d 00:00"));
*/

    if(!$outputFilename = $input->getOption("output-file")) {
      $outputFilename = 'FetchBillable-'.$from_date.'-'.$to_date.'.xml';
    }   

		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

    $output->writeln('FetchBillable executed: ' . date('Ymd H:i:s'));
		$output->writeln('Verifying projects in Harvest');
		$output->writeln('Output filename: ' . $outputFilename);
 		$output->writeln(sprintf('Chart type is "%s" and period is "%s"',$chartType,$chartPeriod));		
		$output->writeln(sprintf("Collecting Harvest entries between %s to %s",$from_date,$to_date));

    $sortedTicketEntries = $this->fetchBillableHoursInPeriod($from_date, $to_date);

		$output->writeln(sprintf('Collected %d ticket entries', sizeof($sortedTicketEntries)-1));
		if (!sizeof($sortedTicketEntries) > 0) {
			//We have no entries containing ticket ids so bail
			return;
		}

  $output->writeln(sprintf('OutputFormat for Geckoboard: %s', $chartType));
  
  switch($chartType) {

    default:
    case "geekometer":

      $output->writeln(sprintf('"%s" will show data for the entire period regardless of what is specified', $chartType));

      // prepare the response!
      $geckoresponse = new \GeckoResponse();

      $billableHours = $sortedTicketEntries["statistics"]["totalhours"];
 
      $output->writeln(sprintf('Billable hours from %s to %s: %s', $from_date, $to_date, $billableHours));

      $data['item'] = round($billableHours);
      $data['type'] = "standard";
      $data['min'][] = array('value' => 0, 'text' => '');
      $data['max'][] = array('value' => 75, 'text' => '');   
      
      // fetch data
      $response = $geckoresponse->getResponse($data, true);       

    break;

    case "line":

      $output->writeln(sprintf('Billable hours from %s to %s: %s', $from_date, $to_date, $sortedTicketEntries["statistics"]["totalhours"]));

      // lets strip the statistics data
      array_pop($sortedTicketEntries);

      $sortedTicketEntries = \GeckoChart::formatValuesToKeys($sortedTicketEntries,$chartPeriod);
      $response = \GeckoChart::formatXmlGeckoboardLine($sortedTicketEntries);
    
    break;
    
  }

    // let's write the response to a file
    $outputFile = new StreamOutput(fopen('data/'.$outputFilename, 'w', false));
    $outputFile->doWrite($response, false);



		$output->writeln("FetchBillable completed -> ".$outputFilename." updated");
	}	
}