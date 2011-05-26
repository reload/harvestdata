<?php

namespace HarvestData\Command;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;

class FetchToday extends HarvestDataCommand {

	protected function configure() {
		$this
		->setName('HarvestData:FetchToday')
		->setAliases(array('today', 'FetchToday'))
		->setDescription('Fetch and store data from Harvest');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->loadConfig($input);

	  $ignore_locked  = false;
	  $from_date      = date("Ymd",time()-(86400*$this->getHarvestDaysBack()));
	  $to_date        = date("Ymd");
    $updated_since  = date("Y-m-d 0:00",time()-(86400*$this->getHarvestDaysBack()));

		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

    $output->writeln('FetchToday executed: ' . date('Ymd H:i:s'));
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

		$ticketEntries = $this->getTicketEntries($projects, $ignore_locked, $from_date, $to_date);

		$output->writeln(sprintf('Collected %d ticket entries', sizeof($ticketEntries)));
		if (sizeof($ticketEntries) == 0) {
			//We have no entries containing ticket ids so bail
			return;
		}

    $sortedEntries = null;

		foreach ($ticketEntries as $entry) {
		  $notes = strlen($entry->get('notes')) > 0 ? $entry->get('notes') : "...";
      $output->writeln(sprintf('%s | %s - %s: "%s" (%s timer @ %s)', self::getUserNameById($entry->get('user-id')), self::getProjectNameById($projects,$entry->get('project-id')),  self::getTaskNameById($entry->get("task-id")), $notes, $entry->get('hours'), $entry->get('spent-at')));
		  $sortedEntries[strtotime($entry->get("updated-at"))] = $entry;
		}

    krsort($sortedEntries); 
    
    // get top 30
    $sortedEntries = array_slice($sortedEntries, 0, 30, true);

  // prepare the response!
  $geckoresponse = new \GeckoResponse();


  // format as text
	foreach ($sortedEntries as $entry) {      
  	$notes = strlen($entry->get('notes')) > 0 ? $entry->get('notes') : "[no notes]";  
  
      $data['item'][] = array('text' => 
      '<span style="font-size: medium;">'.self::getUserNameById($entry->get('user-id')).':</span><br/><i>"'.$notes.'"</i><br/><span style="font-size: small;">'.self::getProjectNameById($projects,$entry->get('project-id')).' - '.self::getTaskNameById($entry->get("task-id")).', '.$entry->get('hours').' timer</span>',
      'type' => 0);
  }
    
    $response = $geckoresponse->getResponse($data, true);  
    
    // let's write the response to a file
    
     $outputFile = new StreamOutput(fopen('FetchToday.xml', 'w', false));
     $outputFile->doWrite($response, false);

		$output->writeln("FetchToday completed -> FetchToday.xml updated");
	}
}