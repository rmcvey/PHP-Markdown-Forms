<?php
/**
*	Markdown Class, front end for markdown_parser
*	@author Rob McVey
*/
class forms_markdown extends markdown_parser{
	/**
	*	@param string content Markdown content
	*/
	public function __construct($content, $options = array()){	
		$lines = explode("\n", $content);	
		parent::__construct($lines, $options);
	}
	
	/**
	*	@return array Parsed Markdown as array of element data
	*/
	public function toArray(){
		return $this->parse();
	}
	
	/**
	*	@return string Markdown converted to generic, ready-to-validate HTML
	*/
	public function toHTML(){
		return $this->__toHTML();
	}
	
	/**
	*	@return string Parsed Markdown as JSON blob
	*/
	public function toJSON(){
		return $this->__toJSON();
	}
	
	/**
	*	@return object Parsed Markdown to stdObject
	*/
	public function toObject(){
		return $this->__toObject();
	}
}

/**
*	Markdown Form Parser
*	Parses text in markdown format into a data structure to output as an html form
*	@author Rob McVey
*/
class markdown_parser{
	/**
	*	options to set for the form programmatically
	*/
	private $options = array(
		'extra-attributes' => array(
			'elems' => array(),
			'labels' => array(),
			'container' => array()
		)
	);
	/**
	*	array used to iterate over loaded Markdown
	*/
	private $markdown = array();
	/**
	*	cache to prevent rerunning the same parsed text
	*/
	private $cache = array();
	/**
	*	@param patterns Regex patterns to parse Markdown
	*/
	private $patterns = array(
		'line_match' => '/\|?([a-zA-Z,\#\%\&\?\(\)0-9_\/<>\;\'\"\s\-]*)([\*]?)[\s]*=[\s]*(.*)/',
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
		'textarea' => '/[”"]+(.*)["”]+/',
		'range' => '/[-]{0,2}([0-9]*)--([0-9]*)(?:--([0-9]*))?(\[([0-9]*)\])?/',
		'toggle' => '/\|(.*)\|(.*)/',
		'rawhtml' => '/[\s]*\<[\/]?.*>\s?/'
	);
	/**
	*	@param html_templates HTML templates that are loaded with parsed data on __toHTML call
	*/
	private $html_templates = array(
		'container' => "<div id=\"md_form\" class=\"md_form_container\">\n\t%s\n</div>",
		'form' => "<form%s>\n\t%s\n</form>",
		'title' => "<div class=\"md_title_container\">\n\t\t<h3 class=\"md_title\">%s</h3>\n\t</div>",
		'header' => "\t<div class=\"md_header_container\">\n\t\t<div class=\"md_header\" data-role=\"header\">%s</div>\n\t</div>",
		'footer' => "\t<div class=\"md_footer_container\">\n\t\t<div class=\"md_footer\" data-role=\"footer\">%s</div>\n\t</div>",
		'element' => "\t<div class=\"md_element\">\n\t\t%s\n\t</div>",
		'select' => "<div class=\"md_select\">\n\t\t\t<select name=\"%s\" class=\"%s md_select_element\">\n\t\t\t\t%s\n\t\t\t</select>\n\t\t</div>",
		'option' => '<option value="%s"%s>%s</option>',
		'checkboxgroup' => "\t\t<div class=\"md_checkboxgroup %s\">\n%s\n\t\t</div>",
		'checkbox' => "\t\t\t<div class=\"md_checkbox md_subfield\">\n\t\t\t\t<input type=\"checkbox\" id=\"%s\" class=\"md_checkbox_element\" name=\"md_%s\" value=\"%s\"%s />\n\t\t\t\t<label for=\"%s\" class=\"md_checkbox_label\">%s</label>\n\t\t\t</div>",
		'radiogroup' => "\t\t<div class=\"md_radiogroup %s\">\n%s\n\t\t</div>",
		'radio' => "\t\t\t<div class=\"md_radio md_subfield\">\n\t\t\t\t<input type=\"radio\" id=\"%s\" class=\"md_radio_element\" name=\"md_%s\" value=\"%s\"%s />\n\t\t\t\t<label for=\"%s\" class=\"md_radio_label\">%s</label>\n\t\t\t</div>",
		'label' => "<div class=\"md_label\">\n\t\t\t<label for=\"%s\" class=\"md_label_element\">%s %s</label>\n\t\t</div>",
		'range' => "<div class=\"md_range %s\">\n\t\t\t<span class=\"md_range_min\">%s</span>
			<input class=\"md_range_element\" type=\"range\" name=\"%s\" min=\"%s\" max=\"%s\" step=\"%s\" value=\"%s\" />
			<span class=\"md_range_max\">%s</span>",
		'toggle' => "<div class=\"md_toggle %s\">\n\t\t\t<select data-role=\"slider\" name=\"%s\" class=\"%s md_toggle_element\">\n\t\t\t\t%s\n\t\t\t</select>\n\t\t</div>",
		'text' => "\t\t<div class=\"md_text\">
			<input 
				onfocus=\"if(this.value == '%s'){this.value='';}\" 
				onblur=\"if(this.value == ''){this.value='%s'}\" 
				type=\"text\" 
				maxlength=\"%s\" 
				name=\"%s\" 
				class=\"%s md_text_element\" 
				value=\"%s\" />\n\t\t</div>",
		'textarea' => "\t\t<div class=\"md_textarea\">\n\t\t\t<textarea 
					onfocus=\"if(this.value == '%s'){this.value='';}\" 
					onblur=\"if(this.value == ''){this.value='%s'}\" 
					name=\"%s\"
					class=\"md_textarea_element\">%s</textarea>\n\t\t</div>",
		'submit' => "<div class=\"md_submit\">\n\t\t\t<input type=\"submit\" class=\"md_submit_element\" value=\"Submit\" />\n\t\t</div>",
		'required' => '<span class="md_req_star">*</span>',
		'rawhtml' => "\t\t%s\n"
	);
	
	/**
	*	@param [array] lines of markup text
	*/
	public function __construct($lines, $options) {
		$this->options = array_merge($this->options, $options);
		
		foreach($lines as $line) {
			if(!empty($line)) {
				array_push($this->markdown, $line);
			}
		}
	}
	
	/**
	*	@return string Parsed Markdown inserted into HTML templates
	*/
	protected function __toHTML(){
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
				case 'rawhtml':
					$rows[] = $this->_build_html($element);
					break;
				case 'select':
					$rows[] = $this->_build_select($element);
					break;
				case 'textarea':
					$rows[] = $this->_build_textarea($element);
					break;
				case 'checkbox':
					$rows[] = $this->_build_checkbox_input($element);
					break;
				case 'radio':
					$rows[] = $this->_build_radio_input($element);
					break;
				case 'text':
					$rows[] = $this->_build_text_input($element);
					break;
				case 'range':
					$rows[] = $this->_build_range($element);
					break;
				case 'toggle':
					$rows[] = $this->_build_toggle($element);
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
	*	@return string html
	*/
	private function _build_html($element){
		return sprintf($this->html_templates['rawhtml'], $element['label']);
	}
	
	/**
	*	@return string html template for a select box
	*/
	private function _build_select($element){
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
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
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$row = vsprintf(
			$this->html_templates['select'], 
			array(
				$element['required'], 
				$element['name'],
				implode("\n\t\t\t\t", $options)
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
	*	HTML5 dependent
	*	@return string html template for a range
	*/
	private function _build_range($element){
		/**
		<span class=\"md_range_min\">%s</span>
			<input class=\"md_range_element\" type=\"range\" name=\"%s\" min=\"%s\" max=\"%s\" step=\"%s\" value=\"%s\" />
			<span class=\"md_range_max\">%s</div>",
		*/
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);

		$template = vsprintf(
			$this->html_templates['range'],
			array(
				$this->_convert_to_string($element['required'], 'required'),
				$element['options']['min'],
				$element['name'],
				$element['options']['min'],
				$element['options']['max'],
				$element['options']['step'],
				$element['options']['value'],
				$element['options']['max']
			)
		);
		$label = sprintf(
			$this->html_templates['label'],
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		
		$template = sprintf("%s\n%s", $label, $template);
		return sprintf(
			$this->html_templates['element'],
			$template
		);
	}
	
	/**
	*	jQuery dependent
	*	@return string html template for a toggle
	*/
	private function _build_toggle($element){
		$options = array();
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
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
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$row = vsprintf(
			$this->html_templates['toggle'], 
			array(
				$this->_convert_to_string($element['required'], 'required'),
				$element['name'], 
				$element['name'],
				implode("\n\t\t\t\t", $options)
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
	private function _build_textarea($element){
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
		$row = vsprintf(
			$this->html_templates['textarea'],
			array(
				$element['default_text'],
				$element['default_text'],
				$element['name'],
				empty($element['default_text']) ? "" : $element['default_text']
			)
		);
		$label = sprintf(
			$this->html_templates['label'],
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$element = sprintf("%s\n%s", $label, $row);
		return sprintf(
			$this->html_templates['element'],
			$element
		);
	}
	
	/**
	*	@return string html template for a checkbox (or checkgroup)
	*/
	private function _build_checkbox_input($element){
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
		$options = array();
		$friendly_name = preg_replace(
			array(
				'/[^A-Za-z0-9_\s]/', '/[\s]/', 
			),
			array(
				'', '_'
			),
			strtolower($element['label'])
		);
		foreach($element['options'] as $option){	
			$id = preg_replace(
				array('/[^A-Za-z0-9_\s]/', '/[\s]/'), 
				array('', '_'), 
				sprintf('md_checkbox_%s_%s', $friendly_name, trim($option['value']))
			);
			$options []= vsprintf(
				$this->html_templates['checkbox'],
				array(
					$id,
					$element['name'],
					$option['value'],
					$this->_convert_to_string($option['checked'], ' checked="checked"'),
					$id,
					trim($option['key'])
				)
			);
		}
		$label = sprintf(
			$this->html_templates['label'],
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$field_options = implode("\n", $options);
		return sprintf(
			$this->html_templates['element'],
			sprintf("%s\n%s", $label, sprintf(
				$this->html_templates['checkboxgroup'],
				$this->_convert_to_string($element['required']),
				$field_options
			))
		);
	}
	
	/**
	*	@return string html template for a radiogroup
	*/
	private function _build_radio_input($element){
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
		$options = array();
		$friendly_name = preg_replace(
			array(
				'/[^A-Za-z0-9_\s]/', '/[\s]/', 
			),
			array(
				'', '_'
			),
			strtolower($element['label'])
		);
		foreach($element['options'] as $option){
			$id = preg_replace(
				array('/[^A-Za-z0-9_\s]/', '/[\s]/'), 
				array('', '_'), 
				sprintf('md_radio_%s_%s', $friendly_name, trim($option['value']))
			);
			$options []= vsprintf(
				$this->html_templates['radio'],
				array(
					$id,
					$element['name'],
					trim($option['key']),
					$this->_convert_to_string($option['checked'], ' checked="checked"'),
					$id,
					$option['value']
				)
			);
		}
		$label = sprintf(
			$this->html_templates['label'],
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$field_options = implode("\n", $options);
		return sprintf(
			$this->html_templates['element'],
			sprintf("%s\n%s", $label, sprintf(
				$this->html_templates['radiogroup'],
				$this->_convert_to_string($element['required']),
				$field_options
			))
		);
	}
	
	/**
	*	@return string html template for a text box
	*/
	private function _build_text_input($element){
		$element['label'] = trim($element['label']);
		$element['name'] = str_replace(' ', '_', $element['label']);
		
		$label = sprintf(
			$this->html_templates['label'],
			$element['name'],
			$element['label'],
			$this->_convert_to_string($element['required'], $this->html_templates['required'])
		);
		$element = sprintf("%s\n%s", $label, vsprintf(
			$this->html_templates['text'],
			array(
				$element['default_text'],
				$element['default_text'],
				$element['max_length'],
				$element['name'],
				$this->_convert_to_string($element['required']),
				$element['default_text']
			)
		));
		return sprintf(
			$this->html_templates['element'],
			$element
		);
	}
	
	private function is_cached(){
		$key = md5(implode($this->markdown));
		if(array_key_exists($key, $this->cache)){
			return $this->cache[$key];
		}
		return false;
	}
	
	// parses markdown text against patterns
	protected function parse(){
		// don't want to parse the same text again
		if($cached = $this->is_cached()){
			return $cached;
		}
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
				) || 
				preg_match(
					$this->patterns['rawhtml'], 
					$element, 
					$matches
				)
			){	
				$field = array(
					'label' => @$matches[1],
					'required' => (@$matches[2] == "*")
				);
			
				// This is the element declaration side of the matched element
				$element_definition = @$matches[3];
			
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
				} else if(
					preg_match(
						$this->patterns['rawhtml'],
						$element,
						$html
					)
				){
					$code = array(
						'label' => $html[0],
						'type' => 'rawhtml'
					);
					array_push($fields['elements'], $code);
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
				} else if(
					preg_match(
						$this->patterns['range'],
						$element_definition,
						$range
					)
				) {
					$field['type'] = 'range';
					if(count($range) === 6){//a step value was passed
						$step = $range[5];
						$min = $range[1];
						$max = $range[3];
						$default = $range[2];
					} else {
						$min = $range[1];
						$max = $range[3];
						$default = $range[2];
						$step = 1;
					}
					$options = array(
						'min' => $min,
						'max' => $max,
						'value' => $default,
						'step' => $step
					);
				} else if(
						preg_match(
							$this->patterns['toggle'],
							$element_definition,
							$toggle
						)
					) {
						$field['type'] = 'toggle';
						for($i = 1; $i <= 2; $i++){
							$options[]= array(
								'key' => $toggle[$i],
								'value' => $toggle[$i],
								'selected' => false
							);
						}
				}else if (
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
					$fields['errors'], sprintf("Line: '%s' does not match any markdown pattern", $element)
				);
			}
		}
		if(empty($fields['form'])){
			$fields['form'] = array();
		}
		$key = md5(implode($this->markdown));
		$this->cache[$key] = $fields;
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
	protected function __toJSON(){
		return json_encode(
			$this->parse()
		);
	}
	
	/**
	*	Converts parsed Markdown into a stdObject object
	*	@return object
	*/
	protected function __toObject(){
		return json_decode(
			$this->__toJSON()
		);
	}
	
	public function __toString(){
		return $this->__toHTML();
	}
}

?>