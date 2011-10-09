<?php
/*
EXAMPLE SYNTAX/USAGE
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
print_r($markdown->toHTML());


/**
*	Markdown Class, front end for markdown_parser
*	@author Rob McVey
*/
class forms_markdown{
	/**
	*	@param string content Markdown content
	*/
	public function __construct($content){	
		$lines = explode("\n", $content);	
		$this->parser = new markdown_parser($lines);
	}
	
	/**
	*	@return array Parsed Markdown as array of element data
	*/
	public function toArray(){
		return $this->parser->parse();
	}
	
	/**
	*	@return string Markdown converted to generic, ready-to-validate HTML
	*/
	public function toHTML(){
		return $this->parser->__toHTML();
	}
	
	/**
	*	@return string Parsed Markdown as JSON blob
	*/
	public function toJSON(){
		return $this->parser->__toJSON();
	}
	
	/**
	*	@return object Parsed Markdown to stdObject
	*/
	public function toObject(){
		return $this->parser->__toObject();
	}
}

/**
*	Markdown Form Parser
*	Parses text in markdown format into a data structure to output as an html form
*	@author Rob McVey
*/
class markdown_parser{
	/**
	*	queue object used to iterate over loaded Markdown
	*/
	private $markdown;
	/**
	*	@param patterns Regex patterns to parse Markdown
	*/
	private $patterns = array(
		'line_match' => '/([a-zA-Z,\#\%\&\?\(\)0-9_\/<>\;\'\"\s\-]*)([\*]?)[\s]*=[\s]*(.*)/',
		// metadata patterns
		'title' => '/form_title[\s]*=[\s]*([a-zA-Z\/<>0-9\s\?\#\&\;]*)/',
		'header' => '/form_header[\s]*=[\s]*([a-zA-Z0-9\s\?\#\&\;]*)/',
		'footer' => '/form_footer[\s]*=[\s]*([a-zA-Z0-9\s\?\#\&\;]*)/',
		// element patterns
		'select_list' => '/{(.*)}/',
		'select_default' => '/\((.*)\)/',
		'checkbox' => '/\[([x]*)\][\s]*([a-zA-Z\_\-0-9\'\"\s]*)/', 
		'radio' => '/\(([x]*)\)[\s]*([a-zA-Z\_\-0-9\'\"\s]*)/',
		'text' => '/([_]+)[_]*([a-zA-Z,\#\%\&\(\)0-9_\'\s\-]*)\[?([0-9]*)\]?/',
		'textarea' => '/[”"]+(.*)["”]+/'
	);
	/**
	*	@param html_templates HTML templates that are loaded with parsed data on __toHTML call
	*/
	public $html_templates = array(
		'form'  => '<div id="md_form">%s</div>',
		'title' => '<h3>%s</h3>',
		'header' => '<p>%s</p>',
		'footer' => '<p>%s</p>',
		'select' => '<div class="md_select md_element">
				<select name="%s" class="%s" id="md_%s">%s</select>
			</div>',
		'option' => '<option value="%s"%s>%s</option>',
		'checkbox' => '<div class="md_checkbox md_element">
				<span class="md_checkbox_label">%s</span>
				<input type="checkbox" class="%s" name="md_%s[]" value="%s"%s />
			</div>',
		'radio' => '<div class="md_radio md_element">
				<span class="md_radio_label">%s</span>
				<input type="radio" class="%s" name="md_%s[]" value="%s"%s />
			</div>',
		'label' => '<div class="md_label md_element">
				<label for="md_%s">%s %s</label>
			</div>',
		'text' => '<div class="md_text md_element">
				<input 
					onfocus="if(this.value == \'%s\'){this.value=\'\';}" 
					onblur="if(this.value == \'\'){this.value=\'%s\'}" 
					type="text" 
					maxlength="%s" 
					name="%s" 
					id="md_%s" 
					class="%s" 
					value="%s" />
			</div>',
		'textarea' => '<div class="md_textarea md_element">
				<textarea 
					onfocus="if(this.value == \'%s\'){this.value=\'\';}" 
					onblur="if(this.value == \'\'){this.value=\'%s\'}" 
					name="%s">%s</textarea>
			</div>',
		'submit' => '<div class="md_submit md_element">
				<input type="submit" value="Submit" />
			</div>'
	);
	
	/**
	*	@param [array] lines of markup text
	*/
	public function __construct($lines){
		$this->markdown = new queue();
		foreach($lines as $line){
			$this->markdown->enqueue($line);
		}
	}
	
	/**
	*	@return string Parsed Markdown inserted into HTML templates
	*/
	public function __toHTML(){
		$data = $this->parse();
		$rows = array();
		
		foreach(array('title', 'header') as $elem){
			if(isset($data[$elem])){
				$rows []= sprintf(
					$this->html_templates[$elem],
					$data[$elem]
				);
			}
		}
		foreach($data['elements'] as $index => $element){
			switch($element['type']){
				case 'select':
					$options = array();
					foreach($element['options'] as $option){
						$options []= vsprintf(
							$this->html_templates['option'],
							array(
								ltrim($option['key']),
								$this->_convert_to_string($option['selected'], ' selected="selected"'),
								ltrim($option['value'])
							) 
						);
					}
					$rows []= sprintf(
						$this->html_templates['label'],
						$element['label'],
						$element['label'],
						$this->_convert_to_string($element['required'], '<span class="md_req_star">*</span>')
					);
					$rows []= vsprintf(
						$this->html_templates['select'], 
						array(
							$element['label'], 
							$element['required'], 
							$element['label'],
							"\n\t" . implode("\n\t", $options) . "\n"
						)
					);
					break;
				case 'textarea':
					$row = vsprintf(
						$this->html_templates['textarea'],
						array(
							$element['default_text'],
							$element['default_text'],
							$element['label'],
							empty($element['default_text']) ? "" : $element['default_text']
						)
					);
					$rows []= sprintf(
						$this->html_templates['label'],
						$element['label'],
						$element['label'],
						$this->_convert_to_string($element['required'], '<span class="md_req_star">*</span>')
					);
					$rows []= $row;
					break;
				case 'checkbox':
					$options = array();
					foreach($element['options'] as $option){
						$options []= vsprintf(
							$this->html_templates['checkbox'],
							array(
								trim($option['key']),
								$this->_convert_to_string($element['required']),
								$element['label'],
								$element['value'],
								$this->_convert_to_string($option['checked'], ' checked="checked"')
							)
						);
					}
					$rows []= sprintf(
						$this->html_templates['label'],
						$element['label'],
						$element['label'],
						$this->_convert_to_string($element['required'], '<span class="md_req_star">*</span>')
					);
					$rows []= implode("\n", $options);
					break;
				case 'radio':
					$options = array();
					foreach($element['options'] as $option){
						$options []= vsprintf(
							$this->html_templates['radio'],
							array(
								trim($option['key']),
								$this->_convert_to_string($element['required']),
								$element['label'],
								$element['value'],
								$this->_convert_to_string($option['checked'], ' checked="checked"')
							)
						);
					}
					$rows []= sprintf(
						$this->html_templates['label'],
						$element['label'],
						$element['label'],
						$this->_convert_to_string($element['required'], '<span class="md_req_star">*</span>')
					);
					$rows []= implode("\n", $options);
					break;
					break;
				case 'text':
					$rows []= sprintf(
						$this->html_templates['label'],
						$element['label'],
						$element['label'],
						$this->_convert_to_string($element['required'], '<span class="md_req_star">*</span>')
					);
			    	$rows []= vsprintf(
						$this->html_templates['text'],
						array(
							$element['default_text'],
							$element['default_text'],
							$element['max_length'],
							$element['label'],
							$element['label'],
							$this->_convert_to_string($element['required']),
							$element['default_text']
						)
					);
					break;
				default:
					break;
			}
		}
		if(!empty($rows)){
			$rows []= $this->html_templates['submit'];	
		}
		if(isset($data['footer'])){
			$rows []= sprintf(
				$this->html_templates['footer'],
				$data['footer']
			);
		}
		return sprintf(
			$this->html_templates['form'],
			implode("\n", $rows)
		);
	}
	
	/**
	*	@param boolean val Value to test
	*	@param string replacement What the value should be replaced with if it's true
	*	@return string 'required' or supplied replacement string
	*/
	private function _convert_to_string($val, $replacement='required'){
		if($val === true){
			return $replacement;
		}else{
			return "";
		}
	}
	
	/**
	*	Converts parsed Markdown into a JSON string
	*	@return string JSON blob
	*/
	public function __toJSON(){
		return json_encode(
			$this->parse()
		);
	}
	
	/**
	*	Converts parsed Markdown into a stdObject object
	*	@return object
	*/
	public function __toObject(){
		return json_decode(
			$this->__toJSON()
		);
	}
	
	// parses markdown text against patterns
	public function parse(){
		//container for elements and errors that are returned
		$fields = array(
			'elements' => array(),
			'errors' => array()
		);
		while($element = $this->markdown->dequeue()){
			if(
				preg_match(
					$this->patterns['line_match'], 
					$element, 
					$matches
				)
			){	
				$field = array(
					'label' => $matches[1],
					'required' => ($matches[2] == "*") ? true : false
				);
			
				// This is the element declaration side of the matched element
				$element_definition = $matches[3];
			
				// this will hold any options found in the rat's nest below
				$options = array();

				if(
					preg_match(
						$this->patterns['title'],
						$element,
						$title
					)
				){
					$options['match'] = 'title: ' . $this->patterns['title'];
					$fields['title'] = $title[1];
					continue;
				} else if (
					preg_match(
						$this->patterns['header'],
						$element,
						$header
					)
				){
					$options['match'] = 'header: ' . $this->patterns['header'];
					$fields['header'] = $header[1];
					continue;
				} else if (
					preg_match(
						$this->patterns['footer'],
						$element,
						$footer
					)
				){	
					$options['match'] = 'footer: ' . $this->patterns['footer'];
					$fields['footer'] = $footer[1];
					continue;
				} else if (
					preg_match(//Select list matching
						$this->patterns['select_list'], 
						$element_definition, 
						$select_list
					)
				){
					$field['type'] = 'select';
					$elements = explode(',', $select_list[1]);
					for($i = 0; $i < count($elements); $i++) {
						$default = false;
						if(
							preg_match(// Matches select elements in parenthesis {Foo, Bar, (Foobar)}
								$this->patterns['select_default'],
								$elements[$i], 
								$default_value
							)
						){
							$default = true;
							$elements[$i] = str_replace(array('(', ')'), '', $elements[$i]);
						}
						
						$value = explode('=>', $elements[$i]);
						
						// trim extra space around parsed text
						array_walk(&$value, 'trim');
						
						// if value is given without display value, set value to display value
						if(count($value) == 1){
							$value[1] = $value[0];
						}
						
						$value = array(
							'key' => $value[0],
							'value' => $value[1],
							'selected' => $default
						);
						$options []= $value;
					}
				}else if(
					preg_match_all(// Radio Matching
						$this->patterns['radio'], 
						$element_definition, 
						$radio
					)
				){
					$field['type'] = 'radio';
					$checked = false;
					foreach($radio[1] as $k => $v) {
						if($v == 'x'){
							$checked = $k;
							continue;
						}
					}

					foreach($radio[2] as $key => $value) {
						$options []= array(
							'key' => $value,
							'value' => $value,
							'checked' => ($key === $checked) ? true : false
						);
					}
				} else if (
					preg_match_all(// Checkbox Matching
						$this->patterns['checkbox'], 
						$element_definition, 
						$checkbox
					)
				){
					$field['type'] = 'checkbox';
					$checked = array();
					foreach($checkbox[1] as $k => $v) {
						if($v == 'x'){
							array_push($checked, $k);
						}
					}

					foreach($checkbox[2] as $key => $value) {
						$options []= array(
							'key' => $value,
							'value' => $value,
							'checked' => (in_array($key, $checked)) ? true : false
						);
					}
				} else if (
					preg_match(// Text Matching
						$this->patterns['text'], 
						$element_definition, 
						$text
					)
				){
					$field['type'] = 'text';
					$field['default_text'] = $text[2];
					$field['max_length'] = $text[3];
				} else if (
					preg_match(// Textarea Matching
						$this->patterns['textarea'], 
						$element_definition, 
						$textarea
					)
				){
					$field['type'] = 'textarea';
					$field['default_text'] = $textarea[1];
				}
				
				if(!empty($field['type'])) {
					$field['options'] = $options;
					array_push(
						$fields['elements'], $field
					);
				} else {
					array_push(
						$fields['errors'], sprintf("$element does not match any pattern")
					);
				}
			} else {
				array_push(
					$fields['errors'], sprintf("Line: '%s' does not match markdown pattern", $element)
				);
			}
		}
		return $fields;
	}
}

/**
*	Just a queue, that is all.
*/
class queue{
	private $queue = array();
	public function __construct(){}
	public function enqueue($var){
		array_push($this->queue, $var);
		return (count($this->queue) - 1);
	}
	public function dequeue(){
		return array_shift($this->queue);
	}
}

?>