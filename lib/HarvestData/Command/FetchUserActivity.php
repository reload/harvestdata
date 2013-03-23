<?php

namespace HarvestData\Command;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;

class FetchUserActivity extends HarvestDataCommand {

  protected function configure() {
    $this
    ->setName('HarvestData:FetchUserActivity')
    ->setAliases(array('useractivity', 'FetchUserActivity'))
    ->setDescription('Fetch and store latest harvest entries from all users');

    // these parameters are not accepted, indicate that by assigning empty arrays.
    $this->setChartTypes(array());
    $this->setChartPeriods(array());

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->loadConfig($input);

    $ignore_locked  = false;
    $from_date      = $this->getHarvestFromDate("Ymd", "3 days ago");
    $to_date        = $this->getHarvestToDate("Ymd", "today");
    $chartType      = $this->getChartType(null);
    $chartPeriod    = $this->getChartPeriod(null);

    if(!$outputFilename = $input->getOption("output-file")) {
      $outputFilename = 'FetchUserActivity-'.$from_date.'-'.$to_date.'.json';
    }

    $output->writeln('FetchUserActivity executed: ' . date('Ymd H:i:s'));
    $output->writeln('Verifying projects in Harvest');
    $output->writeln('Output filename: ' . $outputFilename);
    if($this->getHarvestExcludeContractors()) $output->writeln('NOTE: Contractors are excluded from the dataset!');

    $ticketEntries = $this->getEntriesByUsers($from_date, $to_date);

    $output->writeln(sprintf('Collected %d ticket entries', sizeof($ticketEntries)));
    if (sizeof($ticketEntries) == 0) {
      //We have no entries containing ticket ids so bail
      return;
    }


    // GET THE LATEST ENTRY FOR EACH USER IN PERIOD REGARDLESS OF PROJECT
    $sortedEntries = null;

    foreach ($ticketEntries as $userArray) {
      foreach ($userArray as $entry) {
        $notes = strlen($entry->get('notes')) > 0 ? $entry->get('notes') : "...";
        $output->writeln(sprintf('%s | %s - %s: "%s" (%s timer @ %s)', self::getUserNameById($entry->get('user-id')), self::getProjectNameById($entry->get('project-id')),  self::getTaskNameById($entry->get("task-id")), $notes, $entry->get('hours'), $entry->get('spent-at')));
        $sortedEntries[strtotime($entry->get("updated-at"))] = $entry;
      }
    }

    krsort($sortedEntries); 
    
    $userSortedEntries = null;
    foreach ($sortedEntries as $updated => $entry) {
      if (!isset($userSortedEntries[$entry->get('user-id')])) {
        $userSortedEntries[$entry->get('user-id')]['project']           = self::getProjectNameById($entry->get('project-id'));
        $userSortedEntries[$entry->get('user-id')]['task']              = self::getTaskNameById($entry->get('task-id'));
        $userSortedEntries[$entry->get('user-id')]['username']          = self::getUserNameById($entry->get('user-id'));
        $userSortedEntries[$entry->get('user-id')]['notes']             = $entry->get("notes");
        $userSortedEntries[$entry->get('user-id')]['updated-at']        = $entry->get("updated-at");
        $userSortedEntries[$entry->get('user-id')]['timer-started-at']  = $entry->get("timer-started-at");
        $userSortedEntries[$entry->get('user-id')]['project-id']        = $entry->get('project-id');
        $userSortedEntries[$entry->get('user-id')]['spent-at']          = $entry->get('spent-at');
      }
      else
      {
        continue;
      }
    }
    
    $json = json_encode($userSortedEntries);


    // let's write the response to a file

     $outputFile = new StreamOutput(fopen('data/'.$outputFilename, 'w', false));
     $outputFile->doWrite($json, false);

    $output->writeln("FetchUserActivity completed -> ".$outputFilename." updated");
  }
}