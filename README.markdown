#Usage
---
<?php  
$data =<<< DATA  
form_title=This is my form  
form_header=Fill out this form and magic will happen  
form_footer=Thanks for filling out our form  

<div class="contact-container">
	Phone*=__(444) 555 - 5555
	Address=__
	City=__
	State=__[2]
</div>

How many do you want?*= [x] 10 [x] 5 [] 2 [] 0
Lights=|on|off

Slider*=5--10--50[5]
When do you want it?={Now, (Tomorrow), Never}
Comments="Enter any comments you have for us"

DATA;  
  
$markdown = new forms_markdown($data);  
echo $markdown->toHTML();  
print_r( $markdown->toArray() );  
print_r( $markdown->toObject() );  
echo $markdown->toJSON();  
  
?>  

#Form Markdown
##Form Metadata
---
* form_title=My Form Title
* form_header=Fill out this great form, it is super great
* form_footer=Thanks

##Line Format: Asterisk after LABEL indicates that a field is required
---
LABEL = ELEMENT DECLARATION

##Select List: Curly braces indicate select, VALUE => DISPLAY VALUE, if no DISPLAY VALUE is provided VALUE is used, parenthesis indicate default selected element
---
City* = {St. Louis, Chicago, San Francisco, (New York City)}

##Radio Button: parenthesis - text indicate radio buttons, (x) specifies an element as checked
---
sex* = (x) male () female () all'em () don't know

##Checkbox: square brackets indicate checkbox, [x] specifies an element as checked
---
phones = [] Android [x] iPhone [x] Blackberry

##Text Input: the underscore indicates a text input, text following an underscore is used as default text, integer in square brackets indicates maxlength
---
* name = _[50]
* Form Name = _Enter your form name
* name* = _Default text[50]

##Range (slider): Double-dashes delimit min default and max, step in square brackets
---
* Price you would pay = 1--50--100
* Increments of two that you would pay = --2--6--10[2]

##Toggle (jQuery dependent): If using jqm, you will have a toggle element, other wise it's a select with two options
---
* Lights = |on|off

##Textarea: Quotes indicate textarea, text within quotes is used as default text
---
* Comments = ""
* comments = "Enter your comments here"

##Example Markup
---
form=action:test.php|method:post|class:form-class
form_title=This is my form  
form_header=Fill out this form and magic will happen  
form_footer=Thanks for filling out our form  
First Name\*=\__\__[50]  
Last Name\*=\__\__[50]  
Phone=\__\__(444) 555 - 5555  
Address=\__  
City=\__  
State=\__[2]  
Are you interested?\*= (x) yes () no () not sure  
How many do you want?\*= [x] 10 [] 5 [] 2 [] 0  
When do you want it?={(Now), Tomorrow, Never}  
Comments=”Enter any comments you have for us”