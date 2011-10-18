# HarvestData

HarvestData is a console application which fetches data Harvest and stores it in a file in a format prepared for [Custom Widgets](http://support.geckoboard.com/forums/207979-geckoboard-api) in [Geckoboard](http://geckoboard.com). 

HarvestData currently supports the time tracking system [Harvest](http://harvestapp.com).

## Installation

Clone the repository on [GitHub](https://github.com/reload/harvestdata).

CURL and **PHP 5.3** must be installed (5.2 won't cut it!).
You might need to change the *#!* path in the "HarvestData" file. Use "*which php*" in the commandline in order to find the proper path.

## Configuration

HarvestData needs to know where and how to access the systems involved. This configuration is handled by a config.yml file. Copy the provided config.sample.yml and update it with account information. If your configuration file is not located in the root directory you can specify the path to the config file using the <code>---config</code> option.

## Usage

HarvestData works in the context of one or more Harvest projects identified through their id, full name or code. Projects can be specified in the configuration or using the <code>---harvest-project</code> option. "all", "active" or "project-name" can be used. (This will be DEPRECATED, working on an update that uses users as context for data instead. This is already true for FetchData and FetchBillable).

Also a time period can be specified using the <code>---date-from</code> and/or <code>---date-to</code> options - use a date in the *YYYYMMDD* format, or use "yesterday" or similar [PHP-parsable dates](http://www.php.net/manual/en/datetime.formats.relative.php) (see examples below).

For outputting we have a couple of different parameters.
You can use <code>---chart-type</code> in order to define which kind of chart the data should be outputted as.
This is used in conjuction with <code>---chart-period</code> which is used for grouping the data in the chart. Currently the following values are generally supported:

- day
- week
- month 

The filename of the output can be defined as well, use the <code>---output-file</code> parameter for this. Existing files will be overwritten. See examples later on.

You can also use the <code>---days-back</code> option, requiring an integer.
If no dates are set, the system will use todays date and X days back as defined in the config file (that can differ in each Command).

Run <code>./HarvestData</code> from the command line to show all available commands.

HarvestData currently supports three use cases: 

### Fetch Entries
*** Fetch entries in Harvest ***

As of the current version 0.3 it will be outputted as a Geckoboard [text-widget](http://support.geckoboard.com/entries/231507-custom-widget-type-definitions), so 


 
### Fetch Billable 
*** Fetch number of billable hours from Harvest in a specified period ***

Supported chart-type methods:

- **geekometer** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))
- **line** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))

#### Examples:
Fetch all billable data from september 9th 2011:
 
<code>./harvestdata billable ---date-from=20110901 ---date-to=20110901</code>

Fetch all billable data from september 9th 2011 and export the data in "geekometer" chart-type format:

<code>./harvestdata billable ---date-from=20110901 ---date-to=20110901 ---chart-type=geekometer</code>



### Fetch Data 
*** Fetch detailed data ***
This is probably the most versatile command.

Supported chart-type methods:

- **singlecolum** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))
- **stackedcolumn** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))
- **piechart** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))


#### Examples

Fetch data from the last 7 days, output it as the chart "singlecolumn" and output it in the file "singlecolumn.js":

<code>./harvestdata data ---date-from="-8 days" ---date-to="yesterday" ---chart-type=singlecolumn ---chart-period=day ---output-file=singlecolumn.js</code>

Fetch data from the last 4 whole weeks (monday to sunday) group it by week, draw it as chart "stackedcolumn" and output it as the file "stackedcolumn-week.js"

<code>./harvestdata data ---date-from="-5 mondays" ---date-to="sunday last week" ---chart-type=stackedcolumn ---chart-period=week ---output-file=stackedcolumn-week.js</code>

And a couple of more examples. Note the chart-period and the clever date values:

<code>./harvestdata data ---date-from="first day of 6 months ago" ---date-to="last day of last month" ---chart-type=stackedcolumn ---chart-period=month ---output-file=stackedcolumn-sixmonths.js</code>

<code>./harvestdata data ---date-from="first day of january" ---date-to="last day of last month" ---chart-type=singlecolumn ---chart-period=month ---output-file=year-single.js</code>

