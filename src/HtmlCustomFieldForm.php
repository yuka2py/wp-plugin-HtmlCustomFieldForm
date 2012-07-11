<?php


class InvalidXhtmlFormatException extends ErrorException
{
	private $_xmlParseErrors;
	function __construct($xmlParseErrors) {
		$this->_xmlParseErrors = $xmlParseErrors;
	}
	function getErrors() {
		return $this->$_xmlParseErrors;
	}
}

class HtmlCustomFieldForm
{
	/**
	 * Original template html.
	 * @var string
	 */
	public $html;
	
	/**
	 * Parsed DOMDocument object
	 * @var DOMDocument
	 */
	public $xdoc;
	
	/**
	 * Parsed Field data.
	 * @var array
	 */
	public $fields = array();



	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Get all field names.
	 * @return array
	 */
	public function getFieldNames() {
		return array_keys($this->fields);
	}

	/**
	 * Get the value of all fields.
	 * @return array $values array($fieldName => $value, ...)
	 */
	public function getValues() {
		$values = array();
		foreach ($this->fields as $fieldName => $field) {
			$values[$fieldName] = $field->getValue();
		}
		return $values;
	}

	/**
	 * Get as html string.
	 * @return string
	 */
	public function saveHTML() {
		$html = array();
		$elements = $this->xpath()->query('//body');
		foreach ($elements as $element) {
			$html = $this->xdoc->saveXML($element); //Use saveXML because To save XHTML
			break;
		}
		$html = preg_replace('#<body>|</body>#', '', $html);
		$html = preg_replace('#<textarea ([^>]+)/>#', '<textarea $1></textarea>', $html);
		return $html;
	}


	/**
	 * Load and initialize object from html string.
	 * @return void
	 * @param string $html
	 */
	public function loadHTML($html) {
		$this->html = $html;
		$html = str_replace("¥r", '', $html);
		// $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
		$html = sprintf('<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></html><body>%s</body></html>', $html);
		
		//Load html data
		$this->xdoc = new DOMDocument();
		$this->xdoc->recover = false;
		$this->xdoc->strictErrorChecking = true;
		libxml_use_internal_errors(true);
		$result = $this->xdoc->loadHTML($html);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors(false);
		if (false === $result && !empty($errors)) {
			throw new InvalidXhtmlFormatException($errors);
		}
		
		//Setup fields
		$this->fields = array();
		$elements = $this->xpath()->query('//input[@name]|//textarea[@name]|//select[@name]');
		foreach ($elements as $element) {
			$fieldName = $element->getAttribute('name');
			$fieldName = preg_replace('/[[][]]$/', '', $fieldName);
			if (! isset($this->fields[$fieldName])) {
				switch (strtolower($element->tagName)) {
				case 'textarea':
					$this->fields[$fieldName] = new HtmlCustomFieldFormTextareaField($this);
					break;
				case 'select':
					$this->fields[$fieldName] = new HtmlCustomFieldFormSelectField($this);
					break;
				case 'input':
					switch (strtolower($element->getAttribute('type'))) {
					case 'checkbox':
					case 'radio':
						$this->fields[$fieldName] = new HtmlCustomFieldFormCheckableField($this);
						break;
					default:
						$this->fields[$fieldName] = new HtmlCustomFieldFormTextField($this);
						break;
					}
				}
			}
			$this->fields[$fieldName]->addDOMElement($element);
		}
	}

	/**
	 * Set the value to field.
	 * @return void
	 * @param string $fieldName
	 * @param mixed $value
	 */
	public function setValue($fieldName, $value) {
		if (isset($this->fields[$fieldName])) {
			$this->fields[$fieldName]->setValue($value);
		}
	}


	/**
	 * Set the value of multiple fields at once.
	 * @return void
	 * @param array $values array($fieldName => $value, ...)
	 */
	public function setValues($values) {
		foreach ($values as $fieldName => $value) {
			$this->setValue($fieldName, $value);
		}
	}


	/**
	 * Returns DOMXPath object.
	 * @return DOMXPath
	 */
	public function xpath() {
		static $xpath = null;
		if (null === $xpath) {
			$xpath = new DOMXPath($this->xdoc);
		}
		return $xpath;
	}


}


abstract class HtmlCustomFieldFormField
{
	/**
	 * Parent form object.
	 * @var HtmlCustomFieldForm
	 */
	public $form;

	/**
	 * Field name (Does not mean tag name.)
	 * @var string
	 */
	public $name;
	
	/**
	 * Field value.
	 * @var mixed
	 */
	protected $_value;
	
	
	/**
	 * Array of Related DOMElement object.
	 * @var array
	 */
	protected $_elements;
	
	
	/**
	 * このフィールドがマルチか否か
	 * @var boolean
	 */
	public $isMultiField;


	/**
	 * Constructor
	 */
	public function __construct(HtmlCustomFieldForm $form) {
		$this->form = $form;
	}

	/**
	 * 
	 */
	public function addDOMElement(DOMElement $element) {
		if (empty($this->name)) {
			$this->name = $element->getAttribute('name');
			$this->isMultiField = (boolean) preg_match('/¥[¥]$/', $this->name);
		}
		$this->_elements[] = $element;
		$this->onAddDOMElement($element);
	}
	
	abstract protected function onAddDOMElement(DOMElement $element);
	
	public function getValue() {
		return $this->_value;
	}

	public function setValue($value) {
		$this->_value = $value;
		$this->setElementValue($value);
	}
	
	abstract protected function setElementValue($value);
}

class HtmlCustomFieldFormTextField extends HtmlCustomFieldFormField
{
	protected function onAddDOMElement(DOMElement $element) {
		//Get default value
		$defaultValue = $element->getAttribute('value');
		if ($this->isMultiField) {
			$this->_value[] = $defaultValue;
		} else {
			$this->_value = $defaultValue;
		}
	}
		
	protected function setElementValue($value) {
		$value = (array) $value;
		foreach ($this->_elements as $index => $element) {
			$element->setAttribute('value', $value[$index]);
		}
	}
}


class HtmlCustomFieldFormCheckableField extends HtmlCustomFieldFormField
{
	protected function onAddDOMElement(DOMElement $element) {
		//Arrange attribute of 'value'
		if (! $element->hasAttribute('value')) {
			$element->setAttribute('value', 'on');
		}
		//Get default value
		$defaultValue = $element->getAttribute('value');
		if ($element->hasAttribute('checked')) {
			if ($this->isMultiField) {
				$this->_value[] = $defaultValue;
			} else {
				$this->_value = $defaultValue;
			}
		}
	}
	protected function setElementValue($value) {
		$value = (array) $value;
		foreach ($this->_elements as $element) {
			if (in_array($element->getAttribute('value'), $value)) {
				$element->setAttribute('checked', 'checked');
			} else {
				$element->removeAttribute('checked');
			}
		}
	}
}


class HtmlCustomFieldFormTextareaField extends HtmlCustomFieldFormField
{
	protected function onAddDOMElement(DOMElement $element) {
		//Get default value
		$defaultValue = $element->nodeValue;
		if ($this->isMultiField) {
			$this->_value[] = $defaultValue;
		} else {
			$this->_value = $defaultValue;
		}
	}

	protected function setElementValue($value) {
		$value = (array) $value;
		foreach ($this->_elements as $index => $element) {
			$element->nodeValue = $value[$index];
		}
	}
}

class HtmlCustomFieldFormSelectField extends HtmlCustomFieldFormField
{
	public function options() {
		$options = array();
		foreach ($this->_elements as $element) {
			$elementOptions = $this->form->xpath()->query('.//option', $element);
			foreach ($elementOptions as $option) {
				$options[] = $option;
			}
		}
		return $options;
	}

	protected function onAddDOMElement(DOMElement $element) {
		foreach ($this->options() as $option) {
			//Arrange attribute of 'value'
			if (! $option->hasAttribute('value')) {
				$option->setAttribute('value', $option->nodeValue);
			}
			//Get default value
			if ($option->hasAttribute('selected')) {
				$defaultValue = $option->getAttribute('value');
				if ($this->isMultiField) {
					$this->_value[] = $defaultValue;
				} else {
					$this->_value = $defaultValue;
				}
			}
		}
	}

	protected function setElementValue($value) {
		$value = (array) $value;
		foreach ($this->options() as $option) {
			$optionValue = $option->getAttribute('value');
			if (in_array($optionValue, $value)) {
				$option->setAttribute('selected', 'selected');
			} else {
				$option->removeAttribute('selected');
			}
		}
	}
}


