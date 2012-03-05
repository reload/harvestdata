<?php

namespace HarvestData\Command;

use Symfony\Component\Yaml\Yaml;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

abstract class HarvestDataCommand extends \Symfony\Component\Console\Command\Command {

  /* store the input interface (with commandline options) for later convenience */
  private $input;

  /* data from config.yml */
	private $harvestConfig;
	private $settingsConfig;
	private $budgetConfig;
	
	/* singletons for caching data */
	private $harvestUsers     = null;
	private $harvestTasks     = null;
	private $harvestProjects  = null;
	private $chartTypes       = null;
	private $chartPeriods     = null;

	protected function configure() {
		$this->addOption('harvest-project', NULL, InputOption::VALUE_OPTIONAL, 'One or more Harvest projects (id, name or code) separated by , (comma). Use "all" for all projects or "active" for the active ones.', NULL);
		$this->addOption('date-to', 'e', InputOption::VALUE_OPTIONAL, 'Date from in YYYYMMDD format. Date is inclusive. Today is default.', NULL);
		$this->addOption('date-from', 's', InputOption::VALUE_OPTIONAL, 'Date from in YYYYMMDD format. Date is inclusive. DaysBack from config is default.', NULL);
		$this->addOption('days-back', 'b', InputOption::VALUE_OPTIONAL, 'Overwrite the config setting. Calculate the from-date by X daysback subtracted from to-date. DEPRECATED.', NULL);
		$this->addOption('output-file', 'f', InputOption::VALUE_OPTIONAL, 'Output filename. Will default to a datetime-stamp.', NULL);
		$this->addOption('chart-type', 'c', InputOption::VALUE_OPTIONAL, 'Chart-type when outputting data. Only usable for FetchBillable and FetchData. See their descriptions for possible values.', NULL);
		$this->addOption('chart-period', 'p', InputOption::VALUE_OPTIONAL, 'Chart period when outputting data. Only usable for FetchBillable and FetchData. E.g.: day, week or month', NULL);
		$this->addOption('config', NULL, InputOption::VALUE_OPTIONAL, 'Path to the configuration file', 'config.yml');
	}

	/**
	 * Returns a connection to the Harvest API based on the configuration.
	 * 
	 * @return \HarvestAPI
	 */
	protected function getHarvestApi() {
		$harvest = new \HarvestAPI();
		$harvest->setAccount($this->harvestConfig['account']);
		$harvest->setUser($this->harvestConfig['username']);
		$harvest->setPassword($this->harvestConfig['password']);
		$harvest->setSSL($this->harvestConfig['account']);
		return $harvest;
	}

	protected function getHarvestProjects() {
		return $this->harvestConfig['projects'];
	}
	
	/**
	 * Number of days back compared to today to look for harvestentries
	 * @return Integer Number of days
	 */
	protected function getHarvestDaysBack(InputInterface $input, $fallback = null) {
	  $db = $input->getOption('days-back');

	  if(isset($db) && is_numeric($db) && $db >= 0) {
	    return $db;
	  }
	  else {
	    if(is_null($fallback)) {
        return intval($this->harvestConfig['daysback']);	      
	    }
	    else {
	      return $fallback;
	    }
	  }
	}	

	/**
	 * The from-date in YYYYMMDD format
	 * @return Integer Fromdate
	 */
	protected function getHarvestFromDate(InputInterface $input, $returnFormat = "Ymd", $fallback = null) {
	  $from = $input->getOption('date-from');
	  $db   = $input->getOption('days-back');
	  
	  if(!empty($from) && !empty($db)) {
	    throw new \Exception('You cannot specify "date-from" and "days-back" at the same time');
	  }
	  
	  if(empty($from)) {
	    if(is_null($fallback)) {
	      $from = date($returnFormat,strtotime($this->getHarvestToDate($input))-(86400*$this->getHarvestDaysBack($input)));
	    }
	    else
	    {
	      $from = $fallback;
	    }
	  }
	  
	  if(!is_numeric($from)) { // TODO: This will fail if a date like "YYYY-MM-DD" is provided... Should be refactored as well.
	    // we're guessing is something like "today" or"yesterday"
	    $from = date($returnFormat,strtotime($from));
	  }
		return $from;
	}

	/**
	 * The to-date in YYYYMMDD format
	 * @return Integer Fromdate
	 * @TODO add formatter parameter
	 */
	protected function getHarvestToDate(InputInterface $input, $returnFormat = "Ymd", $fallback = null) {
	  $to = $input->getOption('date-to');
	  
	  if(empty($to)) {
	    if(is_null($fallback)) {
	      $to = "today";
	    }
	    else
	    {
	      $to = $fallback;
	    }
	  }	  
	  
	  if(!is_numeric($to)) { // TODO: This will fail if a date like "YYYY-MM-DD" is provided... Should be refactored as well.
	    // we're guessing is something like "today" or"yesterday"
	    $to = date($returnFormat,strtotime($to));
	  }
		return $to;
	}
  
  protected function setChartTypes($data) {
    if(!is_array($data)) {
      throw new \Exception("Data must be an array in setChartTypes");
    }
    $this->chartTypes = $data;
  }

  protected function setChartPeriods($data) {
    if(!is_array($data)) {
      throw new \Exception("Data must be an array in setChartPeriods");
    }
    $this->chartPeriods = $data;
  }
  
  protected function getChartType(InputInterface $input, $fallback = "stackedcolumn") {
    $chart = $input->getOption('chart-type');

    if(empty($chart)) {
      $chart = $fallback;
    }

    if(!in_array($chart,$this->chartTypes) && !is_null($chart)) {
      if(empty($this->chartTypes)) {
        throw new \Exception(sprintf('Chart-type "%s" is not a valid value for this command, as it does not accept this parameter. Please remove it.', $chart)); 
      }
      else {
        throw new \Exception(sprintf('Chart-type "%s" is not a valid value for this command. Valid types are "%s"', $chart, implode(",",$this->chartTypes)));
      }
    }
    else {
     return $chart;
    } 
  }

  protected function getChartPeriod(InputInterface $input, $fallback = "day") {
    $period = $input->getOption('chart-period');

    if(empty($period)) {
      $period = $fallback;
    }

    if(!in_array($period,$this->chartPeriods)  && !is_null($period)) {
      if(empty($this->chartPeriods)) {
        throw new \Exception(sprintf('Chart-period "%s" is not a valid value for this command, as it does not accept this parameter. Please remove it.', $period)); 
      }
      else {      
        throw new \Exception(sprintf('Chart-period "%s" is not a valid value for this command. Valid periods are "%s"', $period, implode(",",$this->chartPeriods)));
     }
    }
    else {
     return $period;
    } 
  }


	/**
	 * Loads the configuration from a yaml file
	 * 
	 * @param InputInterface $input
	 * @throws Exception
	 */
	protected function loadConfig(InputInterface $input) {
		$configFile = $input->getOption('config');
		if (file_exists($configFile)) {
			$config = Yaml::load($configFile);
			$this->harvestConfig = $config['harvest'];
		} else {
			throw new \Exception(sprintf('Missing configuration file %s', $configFile));
		}
	}
	
	/**
	 * Returns the project ids for this command from command line options or configuration.
	 * 
	 * @param InputInterface $input
	 * @return array An array of project identifiers
	 */
	protected function getProjectIds(InputInterface $input) {
		$projectIds = ($project = $input->getOption('harvest-project')) ? $project : $this->getHarvestProjects();
		if (!is_array($projectIds)) {
			$projectIds = explode(',', $projectIds);
			array_walk($projectIds, 'trim');
		}
		return $projectIds;
	}

	/**
	 * Collect projects from Harvest
	 *
	 * @param array $projectIds An array of project identifiers - ids, names or codes
	 * @param mixed $updated_since DateTime - format "Y-m-d G:i"
	 */
	protected function getProjects($projectIds, $updated_since = null) {
		$projects = array();

		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

		//Prepare by getting all projects
		$result = $harvest->getProjects($updated_since);
		$harvestProjects = ($result->isSuccess()) ? $result->get('data') : array();

		//Collect all requested projects
		$unknownProjectIds = array();
		foreach ($projectIds as $projectId) {
			if (is_numeric($projectId)) {
				//If numeric id then try to get a specific project
				$result = $harvest->getProject($projectId);
				if ($result->isSuccess()) {
					$projects[$project->id] = $result->get('data');
				} else {
					$unknownProjectIds[] = $projectId;
				}
			} else {
				$identified = false;
				foreach($harvestProjects as $project) {
					if (is_string($projectId)) {
						//If "all" then add all projects
						if ($projectId == 'all') {
							$projects[$project->id] = $project;
							$identified = true;
						}
						elseif ($projectId == 'active') {
          		if( $project->active == "true" ) {
          				$projects[$project->id] = $project;
          		}
          		$identified = true;
						}
						//If string id then get project by name or shorthand (code)
						elseif ($project->get('name') == $projectId || $project->get('code') == $projectId) {
							$projects[$project->id] = $project;
							$identified = true;
						}
					}
				}
				if (!$identified) {
					$unknownProjectIds[] = $projectId;
				}
			}
		}
		return $projects;
	}

	/**
	 * Collect users from Harvest
	 *
	 */
	protected function getUsers() {

    if(is_array($this->harvestUsers))
    {
      return $this->harvestUsers;
    }  
      
		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

		//Prepare by getting all projects
		$result = $harvest->getUsers();
		$harvestUsers = ($result->isSuccess()) ? $result->get('data') : array();

    $this->harvestUsers = $harvestUsers;

    // Array of Harvest_User objects
		return $harvestUsers;

	}

	/**
	 * Collect tasks from Harvest
	 *
	 */
	protected function getTasks() {

    if(is_array($this->harvestTasks))
    {
      return $this->harvestTasks;
    }  
      
		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

		//Prepare by getting all tasks
		$result = $harvest->getTasks();
		$harvestTasks = ($result->isSuccess()) ? $result->get('data') : array();

    $this->harvestTasks = $harvestTasks;

    // Array of Harvest_User objects
		return $harvestTasks;

	}

  /**
   * Collect projects from Harvest
   *
   */
  protected function getAllProjects() {

    if(is_array($this->harvestProjects))
    {
      return $this->harvestProjects;
    }

    //Setup Harvest API access
    $harvest = $this->getHarvestApi();

    //Prepare by getting all projects
    $result = $harvest->getProjects();
    $harvestProjects = ($result->isSuccess()) ? $result->get('data') : array();

    $this->harvestProjects = $harvestProjects;

    // Array of Harvest_Projects objects
    return $harvestProjects;
  }


	/**
	 * Return ticket entries from projects.
	 *
	 * @param array $projects An array of projects
	 * @param boolean $ignore_locked Should we filter the closed/billed entries? We cannot update them...
	 * @param Integer $from_date Date in YYYYMMDD format
	 * @param Integer $to_date Date in YYYYMMDD format  
	 */
	protected function getTicketEntries($projects, $ignore_locked = true, $from_date = null, $to_date = null, $user_id = null, $billable = null) {
		//Setup Harvest API access
		$harvest = $this->getHarvestApi();
		$project_count = count($projects);
		$project_counter = 0;

		 if(!is_numeric($from_date)) {
		   $from_date = "19000101";
		 }
		 
		 if(!is_numeric($to_date)) {
		   $to_date = date('Ymd');
		 }
		
		$range = new \Harvest_Range($from_date, $to_date);
		 
		//Collect the ticket entries
		$ticketEntries = array();
		foreach($projects as $project) {
		  $project_counter++;
		  //echo "[".$project_counter . "/" . $project_count . "]>";
		  
			$result = $harvest->getProjectEntries($project->get('id'), $range, $user_id, $billable);
			if ($result->isSuccess()) {
				foreach ($result->get('data') as $entry) {
				    echo ".";
						$ticketEntries[] = $entry;
				}
			}
			else {
			  echo "\nResult was no success...: ProjectID: " . $project->get('id') . " From: " . $from_date . " To: " . $to_date;
			}
		}

		return $ticketEntries;
	}
	
	/**
	 * Return ticket entries from projects.
	 *
	 * @param array $projects An array of projects
	 * @param boolean $ignore_locked Should we filter the closed/billed entries? We cannot update them...
	 * @param Integer $from_date Date in YYYYMMDD format
	 * @param Integer $to_date Date in YYYYMMDD format  
	 */
	protected function getEntriesByUser($user_id, $from_date = null, $to_date = null, $project_id = null, $billable = null) {
		//Setup Harvest API access
		$harvest = $this->getHarvestApi();

		 if(!is_numeric($from_date)) {
		   $from_date = "19000101";
		 }
		 
		 if(!is_numeric($to_date)) {
		   $to_date = date('Ymd');
		 }
		
		$range = new \Harvest_Range($from_date, $to_date);
		 
		//Collect the ticket entries
		$ticketEntries = array();
	  
		$result = $harvest->getUserEntries($user_id, $range, $project_id, $billable);
		if ($result->isSuccess()) {
		  
		  if($entries = $result->get('data')) {
		    if(is_array($entries) && !empty($entries)) {
		      echo "\nFound entries for ". $this->getUserNameById($user_id);		      
		    }
		  }
			foreach ($entries as $entry) {
					$ticketEntries[] = $entry;
			}
			if($entryCount = count($ticketEntries)) {
			  echo " (".$entryCount.")";  
			}
		}
		else {
		  echo "\nResult was no success...: UserId: " . $user_id . " From: " . $from_date . " To: " . $to_date;
		}


		return $ticketEntries;
	}	
	
  
	/**
	 * Look through the projects array and return a name
	 * @param Integer $projectId 
	 * @return String Name of the project
	 */ 
  protected function getProjectNameById($projectId) {
    $projectName = "Unknown";
    $projects = $this->getAllProjects();
    foreach ($projects as $project) {
      if($project->get("id") == $projectId) {
        $projectName = $project->get("name");
        break;
      }
    }
    return $projectName;
  }

	/**
	 * Fetch the Harvest User by id
	 * @param Integer $harvest_user_id 
	 * @return String Full name
	 */  
  protected function getUserNameById($harvest_user_id) {
    $username = "Unknown";
    
    $harvestUsers = $this->getUsers();
    
    if(isset($harvestUsers[$harvest_user_id])) {
      $Harvest_User = $harvestUsers[$harvest_user_id];
      $username = $Harvest_User->get("first-name") . " " . $Harvest_User->get("last-name");
    }

    return $username;
    
  }  

	/**
	 * Fetch the Harvest User by id
	 * @param Integer $harvest_user_id 
	 * @return String Full name
	 */  
  protected function getTaskNameById($harvest_task_id) {
    $taskname = "Unknown";
    
    $harvestTasks = $this->getTasks();
    
    if(isset($harvestTasks[$harvest_task_id])) {
      $Harvest_Task = $harvestTasks[$harvest_task_id];
      $taskname = $Harvest_Task->get("name");
    }

    return $taskname;
  }
  
  public function getEntriesByUsers($from_date, $to_date, $billable = null, $project_id = null) {
    $users = $this->getUsers();  
    $ticketEntries = array();
    
    if(is_array($users)) {
      foreach ($users as $user_id => $Harvest_User) {
        $entries = $this->getEntriesByUser($user_id, $from_date, $to_date, $project_id, $billable);
        if(!empty($entries)) {
          $ticketEntries[$user_id] = $entries;
        }
      }      
    }

    return $ticketEntries;
  }
  
  public function fetchBillableHoursInPeriod($from_date, $to_date, $project_id = null) {
    return $this->fetchHoursInPeriod($from_date, $to_date, "yes", $project_id);
  }

  public function fetchNonBillableHoursInPeriod($from_date, $to_date, $project_id = null) {
    return $this->fetchHoursInPeriod($from_date, $to_date, "no", $project_id);
  }  
  
  /**
  * Return semi-formatted hours pr day in period
  */
  public function fetchHoursInPeriod($from_date, $to_date, $billable = null, $project_id = null) {
    $ticketEntries = $this->getEntriesByUsers($from_date, $to_date, $billable, $project_id);

    $totalHours = 0;
    $sortedTicketEntries = array();
    // sort the ticket entries by date 
    foreach ($ticketEntries as $user_id => $data) {
      foreach ($data as $key => $entry) {
        $keyTime = $entry->get("spent-at");

        if(!array_key_exists($keyTime,$sortedTicketEntries) && floatval($entry->get("hours")) > 0) {
          $sortedTicketEntries[$keyTime] = 0;
        }

        // add hours to this date
        if(floatval($entry->get("hours")) > 0) {
          $sortedTicketEntries[$keyTime] += floatval($entry->get("hours"));
          $totalHours += floatval($entry->get("hours"));          
        }
      }
    }
    
    ksort($sortedTicketEntries);
    if($totalHours >= 0 && count($sortedTicketEntries) >= 0) {
        $averageHoursPerDay = round($totalHours/count($sortedTicketEntries),2);      
    }
    else {
      $averageHoursPerDay   = 0;
    }
    
    $sortedTicketEntries["statistics"] = array("totalhours" => $totalHours, "average" => $averageHoursPerDay);
    
    return $sortedTicketEntries;
  }
  
  // TODO: Refactor -- instead for this function refactor for usage with GeckoChart::formatValuesToKeys() 
  public function formatHoursInPeriod($sortedTicketEntries,$chartPeriod) {
    
    $dateFormat = \GeckoChart::getChartPeriodAsDateFormat($chartPeriod);

    $formattedTicketEntries = array();
    // sort the ticket entries by date
    foreach ($sortedTicketEntries as $date => $hours) {
      if($date != "statistics") {
        // update the timestamp unless it's the statistics key
        $date = date($dateFormat,strtotime($date));
      }
      $formattedTicketEntries[$date] = $hours;
    }

    return $formattedTicketEntries;
  }
  
  /**
   * Fetch both billable and non-billable hours in the periode and return a multidimensional array ready for processing
   */  
  public function fetchAllHoursInPeriod($from_date, $to_date, $project_id = null) {
    $billableEntries      = $this->fetchHoursInPeriod($from_date, $to_date, "yes", $project_id);
    $nonBillableEntries   = $this->fetchHoursInPeriod($from_date, $to_date, "no", $project_id);

    $totalHours             = 0;
    $totalBillableHours     = 0;
    $totalNonBillableHours  = 0;
    
    // lets get the dates
    $dates = array_unique(array_merge(array_keys($billableEntries),array_keys($nonBillableEntries)));
    sort($dates);
    
    $assembledEntries = array();
    foreach ($dates as $date) {
      $assembledEntries["billable"][$date]      = ($date == "statistics" ? $billableEntries[$date] : @floatval($billableEntries[$date]));
      $assembledEntries["non-billable"][$date]  = ($date == "statistics" ? $nonBillableEntries[$date] : @floatval($nonBillableEntries[$date])); 
    }
    
    return $assembledEntries;
  }  
  
  
}