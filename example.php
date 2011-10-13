<?php
require_once 'forms_markdown.php';
/*
EXAMPLE SYNTAX/USAGE
form=action:processor.php|onsubmit:return check(this)|class:validate form|id:my_form|method:post
-----------------------------*/
$content =<<<BEGIN
form_title=This is my form
form_header=Fill out this form and magic will happen
form_footer=Thanks for filling out our form
First Name*=__[50]
Last Name*=_____[50]
Phone*=__(444) 555 - 5555
Address=__
City=__
State=__[2]
Are you interested?*= (x) yes () no () not sure
How many do you want?*= [x] 10 [x] 5 [] 2 [] 0
When do you want it?={Now, (Tomorrow), Never}
Comments="Enter any comments you have for us"
BEGIN;

$markdown = new forms_markdown($content);
echo($markdown);

?>