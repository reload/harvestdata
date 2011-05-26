<?php

namespace HarvestData\Command;

use Symfony\Component\Yaml\Yaml;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

abstract class HarvestDataCommand extends \Symfony\Component\Console\Command\Command {

	private $harvestConfig;
	
	/* singletons for caching data */
	private $harvestUsers = null;
	private $harvestTasks = null;

	protected function configure() {
		$this->addOption('harvest-project', 'p', InputOption::VALUE_OPTIONAL, 'One or more Harvest projects (id, name or code) separated by , (comma). Use "all" for all projects or "active" for the active ones.', NULL);
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
	protected function getHarvestDaysBack() {
		return intval($this->harvestConfig['daysback']);
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
			throw new Exception(sprintf('Missing configuration file %s', $configFile));
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
	 * Return ticket entries from projects.
	 *
	 * @param array $projects An array of projects
	 * @param boolean $ignore_locked Should we filter the closed/billed entries? We cannot update them...
	 * @param Integer $from_date Date in YYYYMMDD format
	 * @param Integer $to_date Date in YYYYMMDD format  
	 */
	protected function getTicketEntries($projects, $ignore_locked = true, $from_date = null, $to_date = null) {
		//Setup Harvest API access
		$harvest = $this->getHarvestApi();
		 
		//Collect the ticket entries
		$ticketEntries = array();
		foreach($projects as $project) {
		  
		 if(!is_numeric($from_date)) {
		   $from_date = "19000101";
		 }
		 
		 if(!is_numeric($to_date)) {
		   $to_date = date('Ymd');
		 }
		
			$range = new \Harvest_Range($from_date, $to_date);
						
			$result = $harvest->getProjectEntries($project->get('id'), $range);
			if ($result->isSuccess()) {
				foreach ($result->get('data') as $entry) {
						$ticketEntries[] = $entry;
				}
			}
			else
			{
			  echo "Result was no success...";
			}
		}

		return $ticketEntries;
	}
  
	/**
	 * Look through the projects array and return a name
	 * @param Array $projects array of Harvest_Project objects
	 * @param Integer $projectId 
	 * @return String Name of the project
	 */  
  protected static function getProjectNameById($projects,$projectId) {
    $projectName = "Unknown";
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
  
    
}