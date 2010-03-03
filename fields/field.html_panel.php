<?php
	
	Class fieldhtml_panel extends Field{
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'HTML Panel';
			$this->_driver = $this->_engine->ExtensionManager->create('html_panel');
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			
			parent::displaySettingsPanel($wrapper, $errors);	

			$label = Widget::Label('URL Expression');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][url_expression]', $this->get('url_expression')));
			$wrapper->appendChild($label);	
									
		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=null) {	
			$status = self::__OK__;			
			return array();
		}
		
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'url_expression'	=> $this->get('url_expression')
			);
			
			$this->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return $this->Database->insert($fields, "tbl_fields_{$handle}");
		}
		
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];
			
			$entryManager = new EntryManager(Administration::instance());
			$entry = reset($entryManager->fetch($entry_id));
						
			$url = $this->parseExpression($entry, $this->get('url_expression'));
			
			if (!preg_match('/$http/', $url)) {
				$url = URL . $url;
			}
			
			$html = file_get_contents($url);
			
			$container = new XMLELement('div', $html);
			$container->setAttribute('class', 'html-partial');
			
			$wrapper->appendChild($container);
			
			if ($this->_engine->Page) {
				$this->_engine->Page->addStylesheetToHead(URL . '/extensions/html_panel/assets/html-panel.css', 'screen', ($this->get('id') * $entry_id));
			}
			
		}
		
		public function createTable(){
			return $this->Database->query(			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`)
				) TYPE=MyISAM;"			
			);			
		}
		
		private function parseExpression($entry, $expression) {
		
			$xpath = $this->getXPath($entry);
			
			$replacements = array();
			
			// Find queries:
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);
			
			// Find replacements:
			foreach ($matches[0] as $match) {
				$results = @$xpath->query(trim($match, '{}'));
				
				if ($results->length) {
					$replacements[$match] = $results->item(0)->nodeValue;
				} else {
					$replacements[$match] = '';
				}
			}
			
			// Apply replacements:
			$value = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);
			
			return $value;
		}
		
		private function getXPath($entry) {
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->_fields['section_id'];
			$data = $entry->getData();			
			$fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));

			$associated = $entry->fetchAllAssociatedEntryCounts();

			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$handle = Administration::instance()->Database->fetchVar('handle', 0, "
						SELECT
							s.handle
						FROM
							`tbl_sections` AS s
						WHERE
							s.id = '{$section}'
						LIMIT 1
					");

					$entry_xml->setAttribute($handle, (string)$count);
				}
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field =& $entry->_Parent->fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false);
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			$dom = new DOMDocument();
			$dom->loadXML($xml->generate(true));

			return new DOMXPath($dom);
		}
						
	}

?>