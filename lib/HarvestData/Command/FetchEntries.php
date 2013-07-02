<?php

namespace HarvestData\Command;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;

class FetchEntries extends HarvestDataCommand {

  protected function configure() {
    $this
    ->setName('HarvestData:FetchEntries')
    ->setAliases(array('entries', 'FetchEntries'))
    ->setDescription('Fetch and store latest harvest entries from period - ^What we are doing right now^');

    // these parameters are not accepted, indicate that by assigning empty arrays.
    $this->setChartTypes(array());
    $this->setChartPeriods(array());

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->loadConfig($input);

    $ignore_locked  = false;
    $from_date      = $this->getHarvestFromDate("Ymd", "today");
    $to_date        = $this->getHarvestToDate("Ymd", "today");
    $chartType      = $this->getChartType(null);
    $chartPeriod    = $this->getChartPeriod(null);

    if(!$outputFilename = $input->getOption("output-file")) {
      $outputFilename = 'FetchEntries-'.$from_date.'-'.$to_date.'.xml';
    }

    $output->writeln('FetchEntries executed: ' . date('Ymd H:i:s'));
    $output->writeln('Verifying projects in Harvest');
    $output->writeln('Output filename: ' . $outputFilename);
    if($this->getHarvestExcludeContractors()) $output->writeln('NOTE: Contractors are excluded from the dataset!');

    $ticketEntries = $this->getEntriesByUsers($from_date, $to_date);

    $output->writeln(sprintf('Collected %d ticket entries', sizeof($ticketEntries)));
    if (sizeof($ticketEntries) == 0) {
      //We have no entries containing ticket ids so bail
      return;
    }

    $sortedEntries = null;

    foreach ($ticketEntries as $userArray) {
      foreach ($userArray as $entry) {
        $notes = strlen($entry->get('notes')) > 0 ? $entry->get('notes') : "...";
        $output->writeln(sprintf('%s | %s - %s: "%s" (%s timer @ %s)', self::getUserNameById($entry->get('user-id')), self::getProjectNameById($entry->get('project-id')),  self::getTaskNameById($entry->get("task-id")), $notes, $entry->get('hours'), $entry->get('spent-at')));
        $sortedEntries[strtotime($entry->get("updated-at"))] = $entry;
      }
    }

    krsort($sortedEntries); 
    
    // get top 30
    $sortedEntries = array_slice($sortedEntries, 0, 30, true);

  // TODO: Refactor and move to GeckoChart.php
  // prepare the response!
  $geckoresponse = new \GeckoResponse();

  // format as text
  foreach ($sortedEntries as $entry) {      
    $notes = strlen($entry->get('notes')) > 0 ? $entry->get('notes') : "[no notes]";  

      $data['item'][] = array('text' => 
      '<span class="t-size-x18">'.self::getProjectNameById($entry->get('project-id')).':</span><br/><span class="t-size-x24">"'.$notes.'"</span><br/><span class="t-size-x18">'.self::getUserNameById($entry->get('user-id')).' - '.self::getTaskNameById($entry->get("task-id")).', '.$entry->get('hours').' timer</span>',
      'type' => 0);
  }

    $response = $geckoresponse->getResponse($data, true);  

    // let's write the response to a file

     $outputFile = new StreamOutput(fopen('data/'.$outputFilename, 'w', false));
     $outputFile->doWrite($response, false);

    $output->writeln("FetchEntries completed -> ".$outputFilename." updated");
  }
}