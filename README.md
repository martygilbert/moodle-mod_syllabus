# Moodle Syllabus Activity [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0) ![example workflow](https://github.com/martygilbert/moodle-mod_syllabus/actions/workflows/syllabus-ci.yml/badge.svg)
This is a fork of the 'file' resource in Moodle that will allow instructors to upload their course syllabus.

## Motivation
Our institution had many different methods of collecting course syllabi for accreditation purposes. This is an attempt to unify those approaches and utilize our institution's LMS to streamline the process.

## Installation
From your Moodle installation's `/mod` directory:
```
git clone -b SYLLABUS_401_STABLE git@github.com:martygilbert/moodle-mod_syllabus.git syllabus
```
You can replace `SYLLABUS_401_STABLE` with the branch that best matches your installation.
## Usage
Instructors can upload a single file using the Syllabus activity. By turning on 'Enable reminder emails' and selecting some categories in 'Categories to check' in the Syllabus site settings, reminder emails will be sent to the instructors of the courses that don't currently have a Syllabus activity.

![image](https://github.com/martygilbert/moodle-mod_syllabus/assets/616253/af6cd29a-f3e7-4e78-9aa3-c9124638947f)

## Collecting Syllabi
Currently there is a CLI script that collects all of the Syllabi uploaded using the Syllabus activity. To use the script, you must have shell access to your server. Future work would integrate this into the interface somehow.

To run the CLI script, change to the `/mod/syllabus/cli/` directory and execute:
```
$ sudo -u <www-user> /path/to/php download_syllabi.php --catid=X --path=/path/to/store/syllabi
```
Where `X` corresponds to a category id and `/path/to/store/syllabi` is a path writeable by the user.
