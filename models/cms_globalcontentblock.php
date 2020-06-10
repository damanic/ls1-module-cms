<?php

	class Cms_GlobalContentBlock extends Db_ActiveRecord
	{
		public $table_name = 'cms_global_content_blocks';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		
		protected static $blocks = null;

		public static function create()
		{
			return new self();
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the block name.");
			$this->define_column('code', 'Code')->validation()->fn('trim')->fn('strtolower')->required("Please specify the block code.")->regexp(',^[/a-z0-9_\.:-]*$,i', "Block code can contain only latin characters, numbers and signs _, -, /, :, and .")->unique('Block with code %s already exists.');
			$this->define_column('content', 'Content')->invisible()->validation();
			$this->define_column('block_type', 'Type');
		}
		
		public function define_form_fields($context = null)
		{
			$user = Phpr::$security->getUser();
			$existing_entry = $this->id ? true : false;
			if (!$existing_entry || ($user && $user->is_administrator())) {
				$f_name = $this->add_form_field('name', 'left')->collapsable();
				$f_code = $this->add_form_field('code', 'right')->collapsable();
			}
			
			$type = $this->block_type;
			if (!$type)
				$type = $context;

			if ($type == 'html')
			{
				$content_field = $this->add_form_field('content')->renderAs(frm_html)->size('huge');
				$editor_config = System_HtmlEditorConfig::get('cms', 'cms_global_content_block');
				$editor_config->apply_to_form_field($content_field);
				$content_field->htmlPlugins .= ',save,fullscreen,inlinepopups';
				$content_field->htmlButtons1 = 'save,separator,'.$content_field->htmlButtons1.',separator,fullscreen';
				$content_field->saveCallback('save_code');
				$content_field->htmlFullWidth = true;
			} else {
				$content_field = $this->add_form_field('content');
			}
		}
		
		public static function get_by_code($code)
		{
			if (self::$blocks === null)
				self::$blocks = self::create()->find_all()->as_array(null, 'code');
				
			if (array_key_exists($code, self::$blocks))
				return self::$blocks[$code];
			
			return null;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Cms_Module::update_cms_content_version();
		}
	}

?>