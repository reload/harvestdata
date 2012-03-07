# HarvestData

HarvestData is a console application which fetches data Harvest and stores it in a file in a format prepared for [Custom Widgets](http://support.geckoboard.com/forums/207979-geckoboard-api) in [Geckoboard](http://geckoboard.com). 

HarvestData currently supports the time tracking system [Harvest](http://harvestapp.com), and, hence the name, it will probably stay that way :-)

HarvestData has been developed by [Reload](http://reload.dk) and the source code can be found on [GitHub](https://github.com/reload/harvestdata).

## This is how Reloads financial geckoboard looks like

![Financial geckoboard](https://github.com/reload/harvestdata/raw/master/docs/Geckoboard-HarvestData.jpg "Snapshot of Reloads financial Geckoboard")

Quick reference examples:

1. <code>./harvestdata compare --date-from="-8 days" --date-to="yesterday" --chart-type=numberstatbudget --output-file=stat1.xml</code>
2. <code>./harvestdata data --date-from="-8 days" --date-to="yesterday" --chart-type=piechart --chart-period=day --output-file=piechart2.js</code>
3. <code>./harvestdata data --date-from="-8 days" --date-to="yesterday" --chart-type=columnspline --output-file=columnspline3.js
4. Same as 1, just another timespan
5. Same as 1, just another timespan
6. Same as 1, just another timespan
7. <code>./harvestdata data --date-from="-5 mondays" --date-to="sunday last week" --chart-type=columnspline --chart-period=week --output-file=columnspline7.js</code>
8. <code>./harvestdata billable --chart-type=geekometer --date-from="last weekday" --date-to="last weekday" --output-file=geekometer8.xml</code>
9. Same as 8, but using "2 weekdays ago" as date-from and date-to
10. <code>./harvestdata data --date-from="first day of 6 months ago" --date-to="last day of last month" --chart-type=columnspline --chart-period=month --output-file=columnspline10.js</code>
11. Same as 2, just another timespan
12. Same as 2, just another timespan
13. Same as 2, just another timespan

Detailed explanation of the parameters and commands can be found below.


## Installation

Clone the repository on [GitHub](https://github.com/reload/harvestdata).

CURL and **PHP 5.3** must be installed (5.2 won't cut it!).
You might need to change the *#!* path in the "HarvestData" file. Use "*which php*" in the commandline in order to find the proper path.

## Configuration

HarvestData needs to know where and how to access the systems involved. This configuration is handled by a config.yml file. Copy the provided config.sample.yml and update it with account information. If your configuration file is not located in the root directory you can specify the path to the config file using the <code>--config</code> option.

## Usage

HarvestData works by looping through all entries made by users (defaulting to employees) and fetching the data for presentation.

A time period can be specified using the <code>--date-from</code> and/or <code>--date-to</code> options - use a date in the *YYYYMMDD* format, or use "yesterday" or similar [PHP-parsable dates](http://www.php.net/manual/en/datetime.formats.relative.php) (see examples below).

For outputting we have a couple of different parameters.
You can use <code>--chart-type</code> in order to define which kind of chart the data should be outputted as.
This is used in conjuction with <code>--chart-period</code> which is used for grouping the data in the chart. Currently the following values are generally supported:

- day
- week
- month 

The filename of the output can be defined as well, use the <code>--output-file</code> parameter for this. Existing files will be overwritten. See examples later on.

You can also use the <code>--days-back</code> option, requiring an integer.
If no dates are set, the system will use todays date and X days back as defined in the config file (that can differ in each Command).

HarvestData will exclude time entries from contractors by default. Change this behaviour by adding the follow parameter:
<code>--exclude-contractors=false</code> or change it in the config file.

### General commandline options (all are optional)

<code>--date-to</code>: 'Date from in YYYYMMDD format (or anything php parsable). Date is inclusive. Today is default.'

<code>--date-from</code>: 'Date from in YYYYMMDD format (or anything php parsable). Date is inclusive. DaysBack from config is default.'

<code>--output-file</code>: 'Output filename. Will default to a datetime-stamp.'
		
<code>--chart-type</code>: 'Chart-type when outputting data. Only usable for FetchBillable and FetchData. See their descriptions for possible values.'

<code>--chart-period</code>: 'Chart period when outputting data. Only usable for FetchBillable and FetchData. E.g.: day, week or month'

<code>--exclude-contractors</code>: 'Exclude contractors hours from the retrieved dataset. Default is true. Boolean value required.'

<code>--config</code>: 'Path to the configuration file. Default is config.yml'

<code>--days-back</code>: 'Overwrite the config setting. Calculate the from-date by X daysback subtracted from to-date. DEPRECATED as of 0.4.'

<code>--harvest-project</code>: 'One or more Harvest projects (id, name or code) separated by , (comma). Use "all" for all projects or "active" for the active ones. DEPRECATED as of 0.4. Might be reintroduced later.'

### Run it

Run <code>./harvestdata</code> from the command line to show all available commands.

HarvestData currently supports three use cases: 

### Fetch Entries
**_Fetch entries in Harvest_**

As of the current version 0.4 it will be outputted as a Geckoboard [text-widget](http://support.geckoboard.com/entries/231507-custom-widget-type-definitions).
We use this command for showing the latest 30 Harvest entries from our employees and contractors, displaying them on a status Geckoboard. 

#### Examples:
<code>./harvestdata entries --exclude-contractors=false --output-file=today.xml</code>

 
### Fetch Billable 
**_Fetch number of billable hours from Harvest in a specified period_**

Supported chart-type methods:

- **geekometer** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))
- **line** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions))

#### Examples:
Fetch all billable data from september 9th 2011:
 
<code>./harvestdata billable --date-from=20110901 --date-to=20110901</code>

Fetch all billable data from september 9th 2011 and export the data in "geekometer" chart-type format:

<code>./harvestdata billable --date-from=20110901 --date-to=20110901 --chart-type=geekometer</code>



### Fetch Data 
**_Fetch detailed data_**
This is probably the most versatile command.

Supported chart-type methods:

- **singlecolumn** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions)) Show a single column chart, often just billable hours
- **columnspline** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions)) Same as singlecolumn, but with a budget spline overlay
- **stackedcolumn** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions)) Shows billable and non-billable bars in a stacked format
- **piechart** ([custom highcharts widget](http://support.geckoboard.com/entries/274940-custom-chart-widget-type-definitions)) Well, a piechart with billable vs non-billable data


#### Examples

Fetch data from the last 7 days, output it as the chart "singlecolumn" and output it in the file "singlecolumn.js":

<code>./harvestdata data --date-from="-8 days" --date-to="yesterday" --chart-type=singlecolumn --chart-period=day --output-file=singlecolumn.js</code>

Fetch data from the last 4 whole weeks (monday to sunday) group it by week, draw it as chart "stackedcolumn" and output it as the file "stackedcolumn-week.js"

<code>./harvestdata data --date-from="-5 mondays" --date-to="sunday last week" --chart-type=stackedcolumn --chart-period=week --output-file=stackedcolumn-week.js</code>

And a couple of more examples. Note the chart-period and the clever date values:

<code>./harvestdata data --date-from="first day of 6 months ago" --date-to="last day of last month" --chart-type=stackedcolumn --chart-period=month --output-file=stackedcolumn-sixmonths.js</code>

<code>./harvestdata data --date-from="first day of january" --date-to="last day of last month" --chart-type=singlecolumn --chart-period=month --output-file=year-single.js</code>

### Compare Periods 
**_Compare billable hours between the assigned period and the same number of days before or the defined budget_**

Supported chart-type methods:

- **numberstat** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/231507-custom-widget-type-definitions)) Shows a number and a percentage (difference between the two provided numbers). The main number is billable hours in the defined period, and the percentage is the difference compared to the previous number of days.
- **numberstatbudget** ([built-in Geckoboard widget](http://support.geckoboard.com/entries/231507-custom-widget-type-definitions)) Will display billable hours in the chosen period compared to the defined budget.


#### Examples:
Compare billable hours from yesterday with billable hours the previous day (two days ago):
 
<code>./harvestdata compare --date-from="yesterday" --date-to="yesterday"</code>

# Developer notes
At lot of stuff is rather hardcoded right now, quite a bit of the classes are ripe for refactoring. As it works for our usecases it might take a while before we fix this stuff.

Also, take a look at docs/todo.txt and docs/test.txt
