<?php
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
	*	array used to iterate over loaded Markdown
	*/
	private $markdown = array();
	/**
	*	@param patterns Regex patterns to parse Markdown
	*/
	private $patterns = array(
		'line_match' => '/([a-zA-Z,\#\%\&\?\(\)0-9_\/<>\;\'\"\s\-]*)([\*]?)[\s]*=[\s]*(.*)/',
		// metadata patterns
		'title' => '/form_title[\s]*=[\s]*([a-zA-Z\/<>0-9\s\?\#\&\;]*)/',
		'header' => '/form_header[\s]*=[\s]*([a-zA-Z0-9\s\?\#\&\;]*)/',
		'footer' => '/form_footer[\s]*=[\s]*([a-zA-Z0-9\s\?\#\&\;]*)/',
		'form' => '/form[\s]*=(.*)/',
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
		'container' => '<div id="md_form" class="md_form_container">
				%s
			</div>',
		'form' => '<form%s>
				%s
			</form>',
		'title' => '<div class="md_title_container">
				<h3 class="md_title">%s</h3>
			</div>',
		'header' => '<div class="md_header_container">
				<p class="md_header">%s</p>
			</div>',
		'footer' => '<p class="md_footer">%s</p>',
		'element' => '<div class="md_element">
				%s
			</div>',
		'select' => '<div class="md_select">
				<select name="%s" class="%s md_select_element">%s</select>
			</div>',
		'option' => '<option value="%s"%s>%s</option>',
		'checkboxgroup' => '<div class="md_checkboxgroup %s">
				%s
				%s
			</div>',
		'checkbox' => '<div class="md_checkbox md_subfield">
				<input type="checkbox" class="md_checkbox_element" name="md_%s" value="%s"%s />
				<span class="md_checkbox_label">%s</span>
			</div>',
		'radiogroup' => '<div class="md_radiogroup %s">
				%s
				%s
			</div>',
		'radio' => '<div class="md_radio md_subfield">	
				<input type="radio" class="md_radio_element" name="md_%s" value="%s"%s />
				<span class="md_radio_label">%s</span>
			</div>',
		'label' => '<div class="md_label">
				<label class="md_label_element">%s %s</label>
			</div>',
		'text' => '<div class="md_text">
				<input 
					onfocus="if(this.value == \'%s\'){this.value=\'\';}" 
					onblur="if(this.value == \'\'){this.value=\'%s\'}" 
					type="text" 
					maxlength="%s" 
					name="%s" 
					class="%s md_text_element" 
					value="%s" />
			</div>',
		'textarea' => '<div class="md_textarea">
				<textarea 
					onfocus="if(this.value == \'%s\'){this.value=\'\';}" 
					onblur="if(this.value == \'\'){this.value=\'%s\'}" 
					name="%s"
					class="md_textarea_element">%s</textarea>
			</div>',
		'submit' => '<div class="md_submit">
				<input type="submit" class="md_submit_element" value="Submit" />
			</div>',
		'required' => '<span class="md_req_star">*</span>'
	);
	
	/**
	*	@param [array] lines of markup text
	*/
	public function __construct($lines){
		foreach($lines as $line){
			array_push($this->markdown, $line);
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
					$rows[] = $this->build_select($element);
					break;
				case 'textarea':
					$rows[] = $this->build_textarea($element);
					break;
				case 'checkbox':
					$rows[] = $this->build_checkbox_input($element);
					break;
				case 'radio':
					$rows[] = $this->build_radio_input($element);
					break;
				case 'text':
					$rows[] = $this->build_text_input($element);
					break;
				default:
					break;
			}
		}
		if(!empty($rows)){
			$rows []= sprintf(
				$this->html_templates['element'],
				$this->html_templates['submit']
			);
		}
		if(isset($data['footer'])){
			$rows []= sprintf(
				$this->html_templates['footer'],
				$data['footer']
			);
		}
		if(!empty($data['form'])){
			$form_attributes = "";
			foreach($data['form'] as $key => $val){
				$form_attributes .= " $key=\"$val\"";
			}
			return sprintf(
				$this->html_templates['container'],
				sprintf(
					$this->html_templates['form'],
					$form_attributes,
					implode("\n", $rows)
				)

			);
		}
		return sprintf(
			$this->html_templates['container'],
			implode("\n", $rows)
		);
	}
	
	/**
	*	@return string html template for a select box
	*/
	protected function build_select($element){
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
		$label = sprintf(
			$this->html_templates['label'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$row = vsprintf(
			$this->html_templates['select'], 
			array(
				$element['required'], 
				$element['label'],
				"\n\t" . implode("\n\t", $options) . "\n"
			)
		);
		$element = sprintf(
			"%s
			%s",
			$label,
			$row
		);
		return sprintf(
			$this->html_templates['element'],
			$element
		);
	}
	
	/**
	*	@return string html template for a textarea
	*/
	protected function build_textarea($element){
		$row = vsprintf(
			$this->html_templates['textarea'],
			array(
				$element['default_text'],
				$element['default_text'],
				$element['label'],
				empty($element['default_text']) ? "" : $element['default_text']
			)
		);
		$label = sprintf(
			$this->html_templates['label'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$element = sprintf(
			"%s
			%s",
			$label,
			$row
		);
		return sprintf(
			$this->html_templates['element'],
			$element
		);
	}
	
	/**
	*	@return string html template for a checkbox (or checkgroup)
	*/
	protected function build_checkbox_input($element){
		$options = array();
		foreach($element['options'] as $option){
			$options []= vsprintf(
				$this->html_templates['checkbox'],
				array(
					$element['label'],
					$option['value'],
					$this->_convert_to_string($option['checked'], ' checked="checked"'),
					trim($option['key'])
				)
			);
		}
		$label = sprintf(
			$this->html_templates['label'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$field_options = implode("\n", $options);
		return sprintf(
			$this->html_templates['element'],
			sprintf(
				$this->html_templates['checkboxgroup'],
				$this->_convert_to_string($element['required']),
				$label,
				$field_options
			)
		);
	}
	
	/**
	*	@return string html template for a radiogroup
	*/
	protected function build_radio_input($element){
		$options = array();
		foreach($element['options'] as $option){
			$options []= vsprintf(
				$this->html_templates['radio'],
				array(
					$element['label'],
					trim($option['key']),
					$this->_convert_to_string($option['checked'], ' checked="checked"'),
					$option['value']
				)
			);
		}
		$label = sprintf(
			$this->html_templates['label'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$field_options = implode("\n", $options);
		return sprintf(
			$this->html_templates['element'],
			sprintf(
				$this->html_templates['radiogroup'],
				$this->_convert_to_string($element['required']),
				$label,
				$field_options
			)
		);
	}
	
	/**
	*	@return string html template for a text box
	*/
	protected function build_text_input($element){
		$label = sprintf(
			$this->html_templates['label'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$element = sprintf("%s\n%s", $label, vsprintf(
			$this->html_templates['text'],
			array(
				$element['default_text'],
				$element['default_text'],
				$element['max_length'],
				$element['label'],
				$this->_convert_to_string($element['required']),
				$element['default_text']
			)
		));
		return sprintf(
			$this->html_templates['element'],
			$element
		);
	}
	

	
	// parses markdown text against patterns
	public function parse(){
		//container for elements and errors that are returned
		$fields = array(
			'elements' => array(),
			'errors' => array()
		);
		foreach($this->markdown as $element){
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
						$this->patterns['form'],
						$element,
						$form
					)
				){
					$attributes = explode('|', $form[1]);
					$options = array();
					foreach($attributes as $attribute){
						list($name, $value) = explode(':', $attribute);
						$options[$name] = $value;
					}
					$fields['form'] = $options;
					continue;
				} else if (
					preg_match(
						$this->patterns['title'],
						$element,
						$title
					)
				){
					$fields['title'] = $title[1];
					continue;
				} else if (
					preg_match(
						$this->patterns['header'],
						$element,
						$header
					)
				){
					$fields['header'] = $header[1];
					continue;
				} else if (
					preg_match(
						$this->patterns['footer'],
						$element,
						$footer
					)
				){	
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
		if(empty($fields['form'])){
			$fields['form'] = array();
		}
		return $fields;
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
}

?>