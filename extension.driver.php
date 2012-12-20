<?php
	Class extension_SAPI extends Extension {

		public static $config_handle = 'sapi';

		public function getSubscribedDelegates(){
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> 'savePreferences'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getSetting($key) {
			return Symphony::Configuration()->get($key, self::$config_handle);
		}


	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		public function getPreferencesData() {
			$data = array(
				'api-key' => '',
				'api-category' => '',
				'api-keyword' => '',
				'gateway-mode' => 'sandbox'
			);

			foreach ($data as $key => &$value) {
				$value = $this->getSetting($key);
			}

			return $data;
		}

		/**
		 * Allow the user to add their SAPI keys.
		 *
		 * @uses AddCustomPreferenceFieldsets
		 */
		public function appendPreferences($context) {
			$data = $this->getPreferencesData();

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('SAPI Details')));

			$this->buildPreferences($fieldset, array(
				array(
					'label' => 'API Key',
					'name' => 'api-key',
					'value' => $data['api-key'],
					'type' => 'text'
				),
				array(
					'label' => 'API categories',
					'name' => 'api-category',
					'value' => $data['api-category'],
					'type' => 'text'
				),
				array(
					'label' => 'API keyword',
					'name' => 'api-keyword',
					'value' => $data['api-keyword'],
					'type' => 'text'
				)
			));

			$context['wrapper']->appendChild($fieldset);
		}


		public function buildPreferences($fieldset, $data) {
			$row = null;

			foreach ($data as $index => $item) {
				if ($index % 2 == 0) {
					if ($row) $fieldset->appendChild($row);

					$row = new XMLElement('div');
					$row->setAttribute('class', 'group');
				}

				$label = Widget::Label(__($item['label']));
				$name = 'settings[' . self::$config_handle . '][' . $item['name'] . ']';

				$input = Widget::Input($name, $item['value'], $item['type']);

				$label->appendChild($input);
				$row->appendChild($label);
			}
            
			// Build the Gateway Mode
			$label = new XMLElement('label', __('Gateway Mode'));
			$options = array(
				array('sandbox', $this->isTesting() , __('Sandbox')),
				array('live',  !$this->isTesting(), __('Live'))
			);
            
			$label->appendChild(Widget::Select('settings[sapi][gateway-mode]', $options));
			$row->appendChild($label);

			$fieldset->appendChild($row);
		}

		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'sapi') == 'sandbox';
		}		

		/**
		 * Saves the SAPI to the configuration
		 *
		 * @uses savePreferences
		 */
		public function savePreferences(array &$context){
			$settings = $context['settings'];

			// Active Section
			Symphony::Configuration()->set('api-key', $settings['sapi']['api-key'], 'sapi');
			Symphony::Configuration()->set('api-category', $settings['sapi']['api-category'], 'sapi');
			Symphony::Configuration()->set('api-keyword', $settings['sapi']['api-keyword'], 'sapi');
			Symphony::Configuration()->set('gateway-mode', $settings['sapi']['gateway-mode'], 'sapi');

			Administration::instance()->saveConfig();
		}

	}
