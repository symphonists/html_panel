<?php

	Class extension_html_panel extends Extension{
	
		public function about(){
			return array(
						'name' => 'Field: HTML Panel',
						'version' => '1.3.1',
						'release-date' => '2011-02-07',
						'author' => array(
							'name' => 'Nick Dunn',
							'website' => 'http://nick-dunn.co.uk'
						)
					);
		}
		
		public function install() {
			return Symphony::Database()->query("
				CREATE TABLE `tbl_fields_html_panel` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`url_expression` varchar(255) default NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `field_id` (`field_id`)
				) TYPE=MyISAM
			");
		}
		
		public function uninstall(){
			return Symphony::Database()->query("DROP TABLE `tbl_fields_html_panel`");
		}
	
	}