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
		->setDescription('Fetch and store data from Harvest');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

	  $ignore_locked  = false;
	  $from_date      = date("Ymd",time()-(86400*1));
	  $to_date        = $from_date;
    $updated_since  = date("Y-m-d 0:00",time()-(86400*1));

		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

    $output->writeln('FetchBillable executed: ' . date('Ymd H:i:s'));
		$output->writeln('Verifying projects in Harvest');
		$output->writeln('Updated since: ' . $updated_since);

		$projects = $this->getProjects($this->getProjectIds($input), $updated_since);
		if (sizeof($projects) == 0) {
			//We have no projects to work with so bail
			$output->writeln(sprintf('Could not find any projects matching: %s', $input));
			return;
		}

		foreach ($projects as $Harvest_Project) {
		  $output->writeln(sprintf('Working with project: %s', $Harvest_Project->get("name")));
		}

		$output->writeln(sprintf("Collecting Harvest entries between %s to %s",$from_date,$to_date));

    $user_id  = null;
    $billable = "yes";
		$ticketEntries = $this->getTicketEntries($projects, $ignore_locked, $from_date, $to_date, $user_id, $billable);

		$output->writeln(sprintf('Collected %d ticket entries', sizeof($ticketEntries)));
		if (sizeof($ticketEntries) == 0) {
			//We have no entries containing ticket ids so bail
			return;
		}

    $billableHours = 0;

		foreach ($ticketEntries as $entry) {
		  $billableHours += floatval($entry->get("hours"));
		}


    $output->writeln(sprintf('Billable hours from %s to %s: %s', $from_date, $to_date, $billableHours));

  // prepare the response!
  $geckoresponse = new \GeckoResponse();

  $data['item'] = round($billableHours);
  $data['type'] = "standard";
  $data['min'][] = array('value' => 0, 'text' => '');
  $data['max'][] = array('value' => 80, 'text' => '');
    
    
    $response = $geckoresponse->getResponse($data, true);  
    
    // let's write the response to a file
    
     $outputFile = new StreamOutput(fopen('FetchBillableYesterday.xml', 'w', false));
     $outputFile->doWrite($response, false);



		$output->writeln("FetchBillable completed -> FetchBillableYesterday.xml updated");
	}
}