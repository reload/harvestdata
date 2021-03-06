<?php
class GeckoChart {
  
  /**
   * @var string root directory
   */
  protected static $_path;  
  
  /**
   * simple autoload function
   * returns true if the class was loaded, otherwise false
   *
   * <code>
   * // register the class auto loader 
   * spl_autoload_register( array('GeckoChart', 'autoload') );
   * </code>
   * 
   * @param string $classname Name of Class to be loaded
   * @return boolean
   */
  public static function autoload($className)
  {
      if (class_exists($className, false) || interface_exists($className, false)) {
          return false;
      }
      $class = self::getPath() . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
      if (file_exists($class)) {
          require $class;
          return true;
      }
      return false;
  }  
  
  /**
   * Get the root path to class
   *
   * @return String
   */
  public static function getPath()
  {
      if ( ! self::$_path) {
          self::$_path = dirname(__FILE__);
      }
      return self::$_path;
  }  
  
  public function getChartPeriodAsDateFormat($chartPeriod) {
    switch($chartPeriod) {
      case "day":
        $dateFormat = "d/m D";
      break;
      case "week":
        $dateFormat = "W";
      break;
      case "month":
        $dateFormat = "m M";
      break;
      default: 
        error_log("Unknown chartperiod: " . $chartPeriod);
        $dateFormat = "Ymd";
      break;
    }
    
    return $dateFormat;
  }
  
  public function formatKeysToDate($keys,$chartPeriod) {
    
    $dateFormat = self::getChartPeriodAsDateFormat($chartPeriod);

    foreach ($keys as &$date) {
      if($date != "statistics") {
        // update the timestamp unless it's the statistics key
        $date = date($dateFormat,strtotime($date));
      }
    }

    $keys = array_unique($keys);
    return $keys;
  }
  
  public function formatValuesToKeys($hourValues, $chartPeriod, $round = null) {
    $formattedHourValues = array();

    $dateFormat = self::getChartPeriodAsDateFormat($chartPeriod);
    foreach ($hourValues as $date => $hours) {
      $keyTime = date($dateFormat,strtotime($date));
      if(!array_key_exists($keyTime,$formattedHourValues)) {
        $formattedHourValues[$keyTime] = 0;
      }
      $formattedHourValues[$keyTime] += $hours;
    }
    
    if(is_integer($round))
    {
      foreach ($formattedHourValues as $key => &$value) {
        $value = round($value,$round);
      }
    }

    return $formattedHourValues;
  }

  public static function makeSingleColumn($sortedTicketEntries, $chartPeriod) {

      $highchart = "
      {
        chart: {
          renderTo: 'container',
          defaultSeriesType: 'column',
          backgroundColor: null,
          plotBackgroundColor: null,
          plotBorderWidth: null,
          plotShadow: false,
          spacingBottom: 0,
          spacingTop: 5
        },
        credits: {
             enabled: false
    	  },
        title: {
           text: 'Billable hours pr. %s (avg: %s)',
            style: {
              fontSize: '12px'
            }
        },
        xAxis: {
           categories: [%s]
        },
        yAxis: {
           min: 0,
           title: {
              text: 'Hours'
           },
           stackLabels: {
              enabled: true,
              style: {
                 fontWeight: 'bold',
                 color: (Highcharts.theme && Highcharts.theme.textColor) || '#111'
              }
           },
           plotLines: [{
               color: 'darkgrey',
               width: 2,
               value: %s,
               zIndex: null,
               label: {
                   text: '(%s)',
                   align: 'right',
                   style: {
                       color: 'darkgrey'
                   }
               }
           }]           
        },
        legend: {
           align: 'center',
           verticalAlign: 'top',
           y: 20,
           floating: true,
           backgroundColor: (Highcharts.theme && Highcharts.theme.legendBackgroundColorSolid) || 'white',
           borderColor: '#CCC',
           borderWidth: 1,
           shadow: false
        },
        tooltip: {
          formatter: function() {
            var s;
              s = '' + this.x  +': '+ this.y;
            return s;
          }
        },
        plotOptions: {
           column: {
              stacking: 'normal',
              dataLabels: {
                 enabled: false,
                 color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || '#111'
              }
           }
        },
         series: [{
           name: 'Billable',
           color: '#89A54E',
           data: [%s]
        }]
      }    
      ";
    
     // we expect the "statistics" element to be the last of the array
    $statistics = array_pop($sortedTicketEntries);
   
    // prepare the keys and values for the insertion into the javascript
    $keys = array_keys($sortedTicketEntries);
  
    // format the keys to something short and meaningfull
    $xAxis = self::formatKeysToDate($keys, $chartPeriod);
    
    array_walk($xAxis, create_function('&$item', '$item = "\'$item\'";'));
    $xAxisString = implode(",", $xAxis);
    
    /* Format the data according to chartPeriod, eg. if we have a month, then we have to summarize the hours */
    $sortedTicketEntries = self::formatValuesToKeys($sortedTicketEntries,$chartPeriod, 0);  
  
    /* Prepare the values for the highchart javascript */
    $sortedTicketEntriesString = implode(",", $sortedTicketEntries);
    
    // calculate the average billable hours for the period
    $averagePerPeriod         = round($statistics["totalhours"]/count($xAxis),1);

    $response = sprintf($highchart,$chartPeriod,$averagePerPeriod,$xAxisString,$averagePerPeriod,round($averagePerPeriod,0),$sortedTicketEntriesString);
    
    return $response;  
  }
  
  public static function makeSingleColumnWithSpline($sortedTicketEntries, $chartPeriod) {

      $highchart = "
      {
        chart: {
          renderTo: 'container',
          defaultSeriesType: 'column',
          backgroundColor: null,
          plotBackgroundColor: null,
          plotBorderWidth: null,
          plotShadow: false,
          spacingBottom: 0,
          spacingTop: 5
        },
        credits: {
             enabled: false
        },
        title: {
           text: 'Billable hours pr. %s vs. budget',
            style: {
              fontSize: '12px'
            }
        },
        xAxis: {
           categories: [%s]
        },
        yAxis: {
           min: 0,
           title: {
              text: 'Hours'
           },
           stackLabels: {
              enabled: true,
              style: {
                 fontWeight: 'bold',
                 color: (Highcharts.theme && Highcharts.theme.textColor) || '#111'
              }
           },
           plotLines: [{
               color: 'darkgrey',
               width: 2,
               value: %s,
               zIndex: null,
               label: {
                   text: '(%s)',
                   align: 'right',
                   style: {
                       color: 'darkgrey'
                   }
               }
           }]
        },
        legend: {
           align: 'center',
           verticalAlign: 'top',
           y: 20,
           floating: true,
           backgroundColor: (Highcharts.theme && Highcharts.theme.legendBackgroundColorSolid) || 'white',
           borderColor: '#CCC',
           borderWidth: 1,
           shadow: false
        },
        tooltip: {
          formatter: function() {
            var s;
              s = '' + this.x  +': '+ this.y;
            return s;
          }
        },
        plotOptions: {
           column: {
              stacking: 'normal',
              dataLabels: {
                 enabled: false,
                 color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || '#111'
              }
           }
        },
         series: [{
           name: 'Billable',
           color: '#89A54E',
           data: [%s]
         }, {
           type: 'spline',
           name: 'Budget',
           color: '#333',
           data: [%s]
         }]
      }
      ";

     // we expect the "statistics" element to be the last of the array
    $statistics = array_pop($sortedTicketEntries);

    // prepare the keys and values for the insertion into the javascript
    $keys = array_keys($sortedTicketEntries);

    // format the keys to something short and meaningfull
    $xAxis = self::formatKeysToDate($keys, $chartPeriod);

    array_walk($xAxis, create_function('&$item', '$item = "\'$item\'";'));
    $xAxisString = implode(",", $xAxis);

    /* Format the data according to chartPeriod, eg. if we have a month, then we have to summarize the hours */
    $sortedTicketEntries = self::formatValuesToKeys($sortedTicketEntries,$chartPeriod, 0);

    /* Prepare the values for the highchart javascript */
    $sortedTicketEntriesString = implode(",", $sortedTicketEntries);

    // calculate the average billable hours for the period
    $averagePerPeriod         = round($statistics["totalhours"]/count($xAxis),1);

    // budget: format values to the period
    $budgetEntries = self::formatValuesToKeys($statistics["budget"],$chartPeriod, 0);
    $budgetEntriesString = implode(",", $budgetEntries);

    $response = sprintf($highchart,$chartPeriod,$xAxisString,$averagePerPeriod,round($averagePerPeriod,0),$sortedTicketEntriesString,$budgetEntriesString);

    return $response;
  }


  public static function makeStackedColumn($assembledHours, $chartPeriod) {

      $highchart = "
      {
        chart: {
          renderTo: 'container',
          defaultSeriesType: 'column',
          backgroundColor: null,
          plotBackgroundColor: null,
          plotBorderWidth: null,
          plotShadow: false,
          spacingBottom: 0,
          spacingTop: 5
        },
        credits: {
             enabled: false
    	  },
        title: {
           text: 'Billable hours pr. %s (avg: %s)',
            style: {
              fontSize: '12px'
            }
        },
        xAxis: {
           categories: [%s]
        },
        yAxis: {
           min: 0,
           title: {
              text: 'Total hours'
           },
           stackLabels: {
              enabled: true,
              style: {
                 fontWeight: 'bold',
                 color: (Highcharts.theme && Highcharts.theme.textColor) || 'gray'
              }
           },
           plotLines: [{
               color: 'darkgrey',
               width: 2,
               value: %s,
               zIndex: null,
               label: {
                   text: '(%s)',
                   align: 'right',
                   style: {
                       color: 'darkgrey'
                   }
               }
           }]           
        },
        legend: {
           align: 'center',
           verticalAlign: 'top',
           y: 20,
           floating: true,
           backgroundColor: (Highcharts.theme && Highcharts.theme.legendBackgroundColorSolid) || 'white',
           borderColor: '#CCC',
           borderWidth: 1,
           shadow: false
        },
        tooltip: {
           formatter: function() {
              return '<b>'+ this.x +'</b><br/>'+
                  this.series.name +': '+ this.y +'<br/>'+
                  'Total: '+ this.point.stackTotal;
           }
        },
        plotOptions: {
           column: {
              stacking: 'normal',
              dataLabels: {
                 enabled: true,
                 color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || '#111'
              }
           }
        },
        series: [{ 
           name: 'Non-billable',
           color: '#AA4643',
           data: [%s]
        }, {   
           name: 'Billable',
           color: '#89A54E',
           data: [%s]
        }]
      }    
      ";
    
    $billableHours     = $assembledHours["billable"];
    $nonBillableHours  = $assembledHours["non-billable"];
    
    
    // we expect the "statistics" element to be the last of the array
    $billableStatistics     = array_pop($billableHours);
    $nonBillableStatistics  = array_pop($nonBillableHours);
    
      // prepare the keys and values for the insertion into the javascript. Should be the same for all hours, so we just use one of the arrays
    $keys = array_keys($billableHours);
    
    // format the keys to something short and meaningfull
    $xAxis = self::formatKeysToDate($keys, $chartPeriod);
    
    array_walk($xAxis, create_function('&$item', '$item = "\'$item\'";'));
    $xAxisString = implode(",", $xAxis);
    
    /* Format the data according to chartPeriod, eg. if we have a month, then we have to summarize the hours */
    $billableHours            = self::formatValuesToKeys($billableHours,$chartPeriod,0);
    $nonBillableHours         = self::formatValuesToKeys($nonBillableHours,$chartPeriod,0);
    
    /* Prepare the values for the highchart javascript */
    $billableValuesString     = implode(",", $billableHours);
    $nonBillableValuesString  = implode(",", $nonBillableHours);
    
    // calculate the average billable hours for the period
    $billableAveragePerPeriod = round($billableStatistics["totalhours"]/count($xAxis),1);
    
    $response = sprintf($highchart,$chartPeriod,$billableAveragePerPeriod,$xAxisString,$billableAveragePerPeriod,round($billableAveragePerPeriod,0),$nonBillableValuesString,$billableValuesString);
    
    return $response;  
  }  
  
  public static function makePieChart($assembledHours, $chartPeriodTitle) {

    $highchart = "
      {
    	chart: {
    		renderTo: 'container',
    		backgroundColor: null,
    		plotBackgroundColor: null,
    		plotBorderWidth: null,
    		plotShadow: false,
    		spacingBottom: 0,
    		spacingTop: 0
    	},
  	  credits: {
           enabled: false
  	  },    	
    	title: {
    		text: '%s',
    		style: {
    		  fontSize: '10px'
    		}    		
    	},
    	tooltip: {
    		formatter: function() {
    			return '<b>'+ this.point.name +'</b>: '+ this.y +'%s';
    		}
    	},
    	legend: {
  			itemWidth: 95,
  			margin: 5,
  			width: '100%s'
		  },
    	plotOptions: {
    		pie: {
    			allowPointSelect: true,
    			cursor: 'pointer',
    			dataLabels: {
    				enabled: true,
    				color: '#000000',
    				connectorColor: '#000000',
    				distance: -25,
    				formatter: function() {
    					return '<b>'+ this.y +'</b>%s';
    				}
    			},
    			showInLegend: true,
    			size: '100%s'
    		}
    	},
        series: [{
    		type: 'pie',
    		name: 'Billable vs non-billable',
    		data: [
    			{
    				name: 'Billable',    
    				y: %s,
    				sliced: false,
    				selected: false,
    				color: '#89A54E'
    			},
    			{
    				name: 'Non-billable',    
    				y: %s,
    				sliced: false,
    				selected: false,
    				color: '#AA4643'    			  
    			}
    		]
    	}]
    }";
    
    // we expect the "statistics" element to be the last of the array
    $billableStatistics     = array_pop($assembledHours["billable"]);
    $nonBillableStatistics  = array_pop($assembledHours["non-billable"]);
    
    $billableTotalHours     = $billableStatistics["totalhours"];
    $nonBillableTotalHours  = $nonBillableStatistics["totalhours"];
    
    $totalHours             = $billableTotalHours + $nonBillableTotalHours;
    $billablePercent        = round($billableTotalHours/$totalHours*100,1);
    $nonBillablePercent     = round($nonBillableTotalHours/$totalHours*100,1);
  
    $response = sprintf($highchart,$chartPeriodTitle,"%","%","%","%",$billablePercent,$nonBillablePercent);
    
    return $response;  
  }
  
	public function formatXmlGeckoboardLine($data,$colour = 'ff9900') {

    $xml = new \XmlWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('root');

      foreach ($data as $time) {
        if($time == 0) continue;
        $xml->writeElement('item', $time);
      }

      $xml->startElement('settings');

        foreach ($data as $keyTime => $time) {
          if($time == 0) continue;
          $xml->writeElement('axisx', $keyTime);
        }

        $xml->writeElement('axisy', floor(min($data)));
        $xml->writeElement('axisy', ceil(max($data)));
        $xml->writeElement('colour', $colour);
      $xml->endElement();

    $xml->endElement();
    $response = $xml->outputMemory(true);
    
    return $response;	  
	}
  
  
}
