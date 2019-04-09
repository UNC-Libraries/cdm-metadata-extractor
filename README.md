# cdm-metadata-extractor
A suite of php scripts to extract metadata and files from CONTENTdm

build_colldb.php and download_masterfiles.php are adapted from North Carolina Digital Heritage Center's scripts for migrating out of CONTENTdm. build_xml_input.php is adapted from Simon Fraser University's cdminspect tool.

These scripts are not necessicarily written using modern PHP best practices since they were adapted quickly to help with Univeristy of North Carolina at Chapel Hill Library's migration out of CONTENTdm.

### Requirements + Config
All scripts require PHP 5+ command-line interface.
Before use, edit scripts to match CDM server

### build_xml_input.php
This script produces an xml structural report for a CONTENTdm collection. It outputs parent object pointers and child object pointers in nested xml.

The resulting xml file can be fed into build_colldb.php to create a database that includes descriptive, structural, and administrative metadata for the collection.

Output from this script is structured similarly to CDM's native "CONTENTdm standard XML" export type, but is created with api calls, allowing the script to process large collections that cause gateway timeout errors when export is attempted from the CONTENTdm admin interface.

USAGE: 
This script needs to be run from the command line.
Requires the alias argument (which tells it what collection to inspect).

php build_xml_input.php cdmalias

e.g.:
php build_xml_input.php unctshirts

### build_colldb.php
This script attempts to build an SQLite DB from xml file created with build_xml_input.php. The SQLite DB will help with migration processes (downloading files via CDM API, building METS representation, metadata remediation).

USAGE:
This script needs to be run from the command line. Not only does it require arguments to run, it will most likely time out if run via the web.

php build_colldb.php path/to.xml cdmalias override*

e.g.:
php build_colldb.php unctshirts.xml unctshirts

--The override argument is optional: by default, the script will append the records it pulls from the xml file to an existing collection database, if one exists. The 'override' flag deletes any current databases and rebuilds.

### download_masterfiles.php
This script generates a .bat file that can be used to download master files using a CDM collection sqlite3 database. (See build_colldb.php)

USAGE:

php download_masterfiles.php path/to.sqlite3 cdmalias

e.g.:
php download_masterfiles.php unctshirts.sqlite3 unctshirts
