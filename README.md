# MQF Editor
### Written by chris-m92

Receives an input of a PDF version of a Master Question File (MQF) in order to parse and subsequently generate a JSON file that can be uploaded and used with cbrech's MQF application.

## Usage
First, the MQF must be a PDF that was created either through Adobe Acrobat, or one that was saved through Microsoft Word using the following instructions:

1. File
2. Save As
3. Click the file formats at the bottom
4. Select PDF
5. Save

If you generate the PDF by using the Microsoft Print to PDF option, it will not work as this does not generate a PDF catalog and subsequently breaks the PDF Parser

## Uploading a file
Click on the file input and browse to your PDF file, click open, and then click **Upload File**

## Editing output
Once the output is generated, the webpage will display a form with all parsed outputs for the user to edit any misspelled words or fix any bugs that were caused by the parsing as it does not seem to be perfect.

## MQF Formatting
It is know that not every MQF has the same formatting between various MDS. Currently, the parser looks to use the format used by the C-17 community in the following manner:

- Questions begin with a number followed by a period and a space '. '
- Each option begins on a new line starting with a letter followed by a period and a space '. '
- The correct answer begins on a new line starting with the phrase "Answer"
- The reference to applicable T.O./AFI/AFMAN, etc. begins on a new line starting with the phrase "Reference"

**NOTE** Due to the PDF translation to text, there are some errors that occur that may input erroneous spaces, as such, the code does not look for the full phrase "Reference"

## Features to come
[ ] Addition / subtraction of options and questions
[ ] Turning the output into a form that will then be sent via POST to generate a downloadable JSON file

## Installation
- Download the files into your project
- Create a folder called `pdf_logs` in your project with permissions (0777)
- Create a folder called `pdf_uploads` in your project with permissions (0777)
- Edit the following lines in `parser.php`

- Line 2 to the path to your `vendor/autoload.php` file
- Line 11 to the path to your `pdf_uploads` folder
- Line 17 to the path to your `pdf_logs` folder

For now, in order to debug output, 3 log files are created
1. Raw output in `line-` log
2. Line by Line output with what the program process each line as in logfile with no prefix
3. JSON output in `json-` log (This is what is used to generate the initial form values in output form)

## Libraries used
PDF Parser by smalot - LGPL-3.0
[Website](https://pdfparser.org/)
[Github](https://github.com/smalot/pdfparser)

