<?php
	
	Class fieldhtml_panel extends Field{
		
		public function __construct(){
			parent::__construct();
			$this->_name = __('HTML Panel');
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label(__('URL Expression'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][url_expression]', $this->get('url_expression')));
			$wrapper->appendChild($label);
									
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode=false, $mode=NULL, $entry_id=NULL) {
			if (is_null($data) || !is_array($data) || is_null($data['value'])) return;
			
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'),
					($encode ? General::sanitize($data['value']) : $data['value']),
					array('handle' => $data['handle'])
				)
			);
		}
		
		public function processRawFieldData($data, &$status, &$message = NULL, $simulate=false, $entry_id=null) {
			$status = self::__OK__;
			return array(
				'handle' => Lang::createHandle($data),
				'value' => $data
			);
		}
		
		public function commit() {
			if (!parent::commit()) return;
			
			$id = $this->get('id');
			if ($id === FALSE) return;
			
			$fields = array(
				'field_id' => $id,
				'url_expression' => $this->get('url_expression')
			);
			
			$handle = $this->handle();
			Symphony::Database()->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return Symphony::Database()->insert($fields, "tbl_fields_{$handle}");
		}
		
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			if(!isset(Administration::instance()->Page)) return;
			
			// work out what page we are on, get portions of the URL
			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];
			
			// get an Entry object for this entry
			$entries = EntryManager::fetch($entry_id);
			
			if (is_array($entries)) $entry = reset($entries);
			
			// parse dynamic portions of the panel URL
			$url = $this->parseExpression($entry, $this->get('url_expression'));
			if (!preg_match('/^http/', $url)) $url = URL . $url;
			
			// create Symphony cookie to pass with each request
			$cookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';
			session_write_close();

			$gateway = new Gateway;
			$gateway->init($url);
			$gateway->setopt('TIMEOUT', 10);		
			$gateway->setopt(CURLOPT_COOKIE, $cookie);
			$gateway->setopt(CURLOPT_SSL_VERIFYPEER, FALSE);

			$result = $gateway->exec();
						
			// a unique name for this panel instance
			$instance_id = $callback['context']['section_handle'] . '_' . $this->get('element_name');
			
			$container = new XMLELement('div', $result);
			$container->setAttribute('id', $instance_id);
			$container->setAttribute('class', 'inline frame');
			
			$label = new XMLElement('label', $this->get('label'));
			$label->appendChild($container);
			$wrapper->appendChild($label);			
			
			$asset_index = $this->get('id') * rand(10, 100);
			
			// add panel-specific styling
			$instance_css = '/html-panel/' . $instance_id . '.css';
			if (file_exists(WORKSPACE . $instance_css)) {
				Administration::instance()->Page->addStylesheetToHead(URL . '/workspace' . $instance_css, 'screen', $asset_index++);
			}
			
			// add panel-specific behaviour
			$instance_js = '/html-panel/' . $instance_id . '.js';
			if (file_exists(WORKSPACE . $instance_js)) {
				Administration::instance()->Page->addScriptToHead(URL . '/workspace' . $instance_js, $asset_index++);
			}
			
		}
		
		public function createTable(){
			return Symphony::Database()->query(			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `handle` varchar(255) default NULL,
    			  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`)
				) TYPE=MyISAM;"			
			);			
		}
		
		// modified from Reflection Field
		private function parseExpression($entry, $expression) {
		
			$xpath = $this->getXPath($entry);			
			$replacements = array();			
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);
			
			foreach ($matches[0] as $match) {
				$results = @$xpath->query(trim($match, '{}'));				
				if ($results->length) {
					$replacements[$match] = $results->item(0)->nodeValue;
				} else {
					$replacements[$match] = '';
				}
			}
			
			$value = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);
			
			return $value;
		}
		
		// modified from Reflection Field
		private function getXPath($entry) {
			
			if (!$entry instanceOf Entry) return new DOMXPath(new DOMDocument());
			
			$entry_xml = new XMLElement('entry');
			$entry_xml->setAttribute('id', $entry->get('id'));

			foreach ($entry->getData() as $field_id => $values) {
				if (empty($field_id)) continue;
				
				$field = FieldManager::fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, FALSE, NULL);
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			$dom = new DOMDocument();
			$dom->loadXML($xml->generate(TRUE));

			return new DOMXPath($dom);
		}
						
	}
