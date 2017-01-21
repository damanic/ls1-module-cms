<?php

	class Cms_ContentBlock extends Db_ActiveRecord
	{
		public $table_name = 'content_blocks';
		
		public static $page_blocks = array();

		public static function create()
		{
			return new self();
		}
		
		public static function get_by_page_and_code($page_id, $code)
		{
			if (!array_key_exists($page_id, self::$page_blocks))
				self::$page_blocks[$page_id] = self::create()->find_all_by_page_id($page_id)->as_array(null, 'code');
				
			if (array_key_exists($code, self::$page_blocks[$page_id]))
				return self::$page_blocks[$page_id][$code];
			
			return null;
		}
		
		public static function clear_cache()
		{
			self::$page_blocks = array();
		}

		public function before_save($deferred_session_key = null)
		{
			Backend::$events->fireEvent('cms:onBeforeSaveContentBlock', $this);
		}
		
		public function after_save()
		{
			if($this->page_id)
			{
				$user = Phpr::$security->getUser();
				if($user)
					Db_DbHelper::query('update pages set updated_user_id=:user_id, updated_at=:updated_at where id=:id', array(
						'user_id'=> $user->id,
						'id'=>$this->page_id,
						'updated_at'=>Phpr_DateTime::gmtNow())
					);
				else
					Db_DbHelper::query('update pages set updated_at=:updated_at where id=:id', array('updated_at'=>Phpr_DateTime::gmtNow(),'id'=>$this->page_id));
			}
		}
	}

?>