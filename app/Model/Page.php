<?php
/**
 * Pageモデル
 *
 * <pre>
 *  ページ一覧
 * </pre>
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       app.Model
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */

class Page extends AppModel
{
	public $name = 'Page';
	public $actsAs = array('Page', 'TimeZone');	// , 'Validation'
	public $validate = array();

// 公開日付をsaveする前に変換するかどうかのフラグ
	public $autoConvert = true;

/**
 * バリデート処理
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function __construct() {
		parent::__construct();

		//エラーメッセージ取得
		$this->validate = array(
				'page_name' => array(
					'notEmpty'  => array(
						'rule' => array('notEmpty'),
						'last' => true,
						'required' => true,
						//'allowEmpty' => false,
						'message' => __('Please be sure to input.')
					),
					'maxlength'  => array(
						'rule' => array('maxLength', NC_VALIDATOR_PAGE_TITLE_LEN),
						'last' => false ,
						'message' => __('The input must be up to %s characters.', NC_VALIDATOR_PAGE_TITLE_LEN)
					),
					'duplicationPageName'  => array(
						'rule' => array('_duplicationPageName'),
						'message' => __('The same name is already in use.Please choose another one.')
					)
				),
				'permalink' => array(
					'notEmptyPermalink'  => array(
						'rule' => array('_notEmptyPermalink'),
						'last' => true,
						//'required' => true,
						//'allowEmpty' => false,
						'message' => __('Please be sure to input.')
					),
					'invalidPermalink'  => array(
						'rule' => array('_invalidPermalink'),
						'last' => true,
						'message' => __('It contains an invalid string.')
					),
					'maxlength'  => array(
						'rule' => array('maxLength', NC_VALIDATOR_PERMALINK_LEN),
						'message' => __('The input must be up to %s characters.', NC_VALIDATOR_PERMALINK_LEN).__('(The total number of the top node)')
					),
					'duplicationPermalink'  => array(
						'rule' => array('_duplicationPermalink'),
						'message' => __('The same name is already in use.Please choose another one.')
					)
				),
				'display_sequence' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'allowEmpty' => false,
						'message' => __('The input must be a number.')
					)
				),
				'position_flag' => array(
					'boolean'  => array(
						'rule' => array('boolean'),
						'message' => __('The input must be a boolean.')
					)
				),
				'space_type' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'required' => true,
						'message' => __('The input must be a number.')
					),
					'range' => array(
						'rule' => array('range', NC_SPACE_TYPE_PUBLIC - 1, NC_SPACE_TYPE_GROUP + 1),
						'message' => __('The input must be a number bigger than %d and less than %d.', NC_SPACE_TYPE_PUBLIC, NC_SPACE_TYPE_GROUP)
					)
				),
				'display_flag' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'required' => true,
						'message' => __('The input must be a number.')
					),
					'inList' => array(
						'rule' => array('inList', array(
							NC_DISPLAY_FLAG_OFF,
							NC_DISPLAY_FLAG_ON,
							NC_DISPLAY_FLAG_DISABLE,
						), false),
						'allowEmpty' => false,
						'message' => __('It contains an invalid string.')
					)
				),

				'display_from_date' => array(
					'datetime'  => array(
						'rule' => array('datetime'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('Unauthorized pattern for %s.', __('Date-time'))
					),
					'isFutureDateTime'  => array(
						'rule' => array('isFutureDateTime'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('%s in the past can not be input.', __('Date-time'))
					),
					'invalidDisplayFromDate'  => array(
						'rule' => array('invalidDisplayFromDate'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('Because the page is not a private, You can\'t set a publish date.')
					),
				),
				'display_to_date' => array(
					'datetime'  => array(
						'rule' => array('datetime'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('Unauthorized pattern for %s.', __('Date-time'))
					),
					'isFutureDateTime'  => array(
						'rule' => array('isFutureDateTime'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('%s in the past can not be input.', __('Date-time'))
					),
					'invalidDisplayToDate'  => array(
						'rule' => array('invalidDisplayToDate'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('Because the page is not published, You can\'t set a closed date.')
					),
					'invalidDisplayFromToDate'  => array(
						'rule' => array('invalidDisplayFromToDate'),
						'last' => true,
						'allowEmpty' => true,
						'message' => __('Please input in [publish date < closed date].')
					),
				),
				'display_apply_subpage' => array(
					'boolean'  => array(
						'rule' => array('boolean'),
						'last' => true,
						'required' => true,
						'message' => __('It contains an invalid string.')
					)
				),
				'is_approved' => array(
					'boolean'  => array(
						'rule' => array('boolean'),
						'last' => true,
						'required' => true,
						'message' => __('It contains an invalid string.')
					)
				),
		);
	}

/**
 * ページ名称重複チェック
 * 		コミュニティー名称のみ
 * @param   array    $check
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function _duplicationPageName($check){
		$position_flag = intval($this->data['Page']['position_flag']);
		if($position_flag == _OFF) {
			return true;
		}
		if(!isset($this->data['Page']['lang'])) {
			$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
			$check['lang'] = array('', $lang);
		} else {
			$check['lang'] = $this->data['Page']['lang'];
		}
		$check['position_flag'] = _ON;
		if(!isset($this->data['Page']['space_type']) || $this->data['Page']['space_type'] != NC_SPACE_TYPE_GROUP) {
			return true;
		}
		if(!isset($this->data['Page']['thread_num']) || $this->data['Page']['thread_num'] != 1) {
			return true;
		}

		$check['thread_num'] = $this->data['Page']['thread_num'];
		$check['space_type'] = $this->data['Page']['space_type'];

		if(!empty($this->data['Page']['id']))
			$check['id !='] = $this->data['Page']['id'];

		$count = $this->find( 'count', array('conditions' => $check, 'recursive' => -1) );
		if($count != 0)
			return false;
		return true;
	}

/**
 * 固定リンクが空かどうかのチェック
 * @param   array    $check
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function _notEmptyPermalink($check) {
		$permalink = trim($check['permalink'], '/');
		$space_type = intval($this->data['Page']['space_type']);
		$thread_num = intval($this->data['Page']['thread_num']);
		$position_flag = intval($this->data['Page']['position_flag']);
		$display_sequence = intval($this->data['Page']['display_sequence']);

		if($position_flag == _OFF || $thread_num == 0) {
			// TOPノード
			if($permalink != '') {
				return false;
			}
			return true;
		}
		$permalink_arr = explode('/', $permalink);
		$chk_thread_num = ($space_type == NC_SPACE_TYPE_PUBLIC || ($thread_num == 2 && $display_sequence == 1)) ? $thread_num - 1 : $thread_num;
		if(!isset($permalink_arr[$chk_thread_num - 1]) || $permalink_arr[$chk_thread_num - 1] == '') {
			if($thread_num == 0 || ($thread_num == 1 && $space_type == NC_SPACE_TYPE_PUBLIC)
				|| ($thread_num == 2 && $display_sequence == 1 && $space_type == NC_SPACE_TYPE_PUBLIC)) {
				// Topノード、パブリックTopノード、各ノードのTopページが存在する可能性あり
				// パブリックでトップページのページ追加可能
				return true;
			}
			return false;
		}
		$current_permalink = $permalink_arr[$chk_thread_num - 1];
		// notEmpty
		if (empty($current_permalink) && $current_permalink != '0') {
			return false;
		}
		return preg_match('/[^\s]+/m', $current_permalink);
	}

/**
 * 固定リンクに不正な文字列がないかのチェック
 *
 * @param  array     $check
 * @return boolean
 * @since   v 3.0.0.0
 */
	public function _invalidPermalink($check) {
		$permalink = trim($check['permalink'], '/');
		$space_type = intval($this->data['Page']['space_type']);
		$thread_num = intval($this->data['Page']['thread_num']);
		$display_sequence = intval($this->data['Page']['display_sequence']);
		if($permalink == '') {
			return true;
		}
		$space_type = intval($this->data['Page']['space_type']);
		$permalink_arr = explode('/', $permalink);
		$chk_thread_num = ($space_type == NC_SPACE_TYPE_PUBLIC || ($thread_num == 2 && $display_sequence == 1)) ? $thread_num - 1 : $thread_num;
		if(count($permalink_arr) != $chk_thread_num) {
			return false;
		}
		$current_permalink = $permalink_arr[$chk_thread_num - 1];

		if(preg_match(NC_PERMALINK_PROHIBITION, $current_permalink)) {
			return false;
		}
		$chk_permalink = $this->getPermalink($permalink, $space_type);
		if(preg_match(NC_PERMALINK_PROHIBITION_DIR_PATTERN, $chk_permalink)) {
			return __('Unavailable string is used by the system.');
		}
		return true;
	}

/**
 * 固定リンクの重複チェック
 *
 * @param  array     $check
 * @return boolean
 * @since   v 3.0.0.0
 */
	public function _duplicationPermalink($check){
		if($check['permalink'] == '') {
			return true;
		}
		if(!isset($this->data['Page']['lang'])) {
			$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
			$check['lang'] = array('', $lang);
		} else {
			$check['lang'] = $this->data['Page']['lang'];
		}
		$check['position_flag'] = _ON;
		$check['display_sequence !='] = 0;

		if(!empty($this->data['Page']['id']))
			$check['id !='] = $this->data['Page']['id'];

		if(!empty($this->data['Page']['space_type']))
			$check['space_type'] = $this->data['Page']['space_type'];
		$count = $this->find( 'count', array('conditions' => $check, 'recursive' => -1) );
		if(($this->data['Page']['thread_num'] == 1 || $this->data['Page']['display_sequence'] == 1) && $count == 1) {
			// ノード Or Node Top Page
			$count = 0;
		}
		if($count != 0)
			return false;
		return true;
	}

/**
 * beforeSave
 * @param   array  $options
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function beforeSave($options = array()) {
		if(!$this->autoConvert) {
			return true;
		}
		if (!empty($this->data['Page']['display_from_date']) ) {
			$this->data['Page']['display_from_date'] = $this->dateUtc($this->data['Page']['display_from_date']);
		}
		if (!empty($this->data['Page']['display_to_date']) ) {
			$this->data['Page']['display_to_date'] = $this->dateUtc($this->data['Page']['display_to_date']);
		}
		return true;
	}

/**
 * ページリストからページ取得
 * @param   integer or array    $page_id_arr
 * @param   integer  $user_id
 * @param   integer  $space_type
 * @return  array    $pages
 * @since   v 3.0.0.0
 */
	public function findAuthById($page_id_arr, $user_id, $space_type = null) {
		$conditions = array('Page.id' => $page_id_arr);

		$params = array(
			'fields' => $this->_getFieldsArray($user_id, $space_type),
			'joins' => $this->_getJoinsArray($user_id, 'LEFT', $space_type),
			'conditions' => $conditions
		);

		if(is_array($page_id_arr)) {
			return $this->afterFindIds($this->find('all', $params), $user_id);
		}
		$ret = $this->afterFindIds($this->find('first', $params), $user_id);
		return $ret;
	}

/**
 * afterFind
 * @param   array   $results
 * @param   integer  $user_id
 * @return  array   $pages
 * @since   v 3.0.0.0
 */
	public function afterFindIds($results, $user_id) {
		$pages = array();
		$single_flag = false;
		if(isset($results['Page']['id'])) {
			$single_flag = true;
			$current_page_id = $results['Page']['id'];
			$results = array($results);
		}
		if(is_array($results)) {
			foreach ($results as $key => $val) {
				if(!isset($val['Authority']['hierarchy'])) {
					$val['Authority']['hierarchy'] = $this->getDefaultHierarchy($val, $user_id);
				}
				$val['Page'] = $this->setPageName($val['Page']);
				$pages[$val['Page']['id']] = $val;
			}
		}
		if(count($pages) == 0)
			return false;

		if($single_flag) {
			return $pages[$current_page_id];
		}

		return $pages;
	}

/**
 * パンくずリストの配列を取得する
 *
 * @param Model Page $page
 * @param string $user_id
 * @return array
 * @access public
 */
	function findBreadcrumb($page, $user_id = null) {
		$results = array();

		if(!isset($page['Authority']['hierarchy'])) {
			$page['Authority']['hierarchy'] = $this->getDefaultHierarchy($page);
		}
		if(isset($page['CommunityLang']['community_name'])) {
			$page['Page']['page_name'] = $page['CommunityLang']['community_name'];
		}
		$page['Page'] = $this->setPageName($page['Page']);
		$page['Page']['permalink'] = $this->getPermalink($page['Page']['permalink'], $page['Page']['space_type']);

		if(($page['Page']['space_type'] != NC_SPACE_TYPE_PUBLIC && $page['Page']['thread_num'] > 1) ||
				($page['Page']['space_type'] == NC_SPACE_TYPE_PUBLIC && $page['Page']['display_sequence'] > 1)) {
			$parent_page = $this->findAuthById($page['Page']['parent_id'], $user_id, $page['Page']['space_type']);
			$parents_page = $this->findBreadcrumb($parent_page, $user_id);
			foreach($parents_page as $buf_parent_page) {
				$results[] = $buf_parent_page;
			}
		}
		$results[] = $page;

		return $results;
	}

/**
 * ページメニューのリストを取得
 * TODO:$login_user_id,$current_user等が指定されると、どのようなレスポンスが返るのかわかりにくいため修正したほうがよい。
 * TODO:findViewableに置き換え、このメソッドを廃止？
 *
 * @param string    $type all or count or list
 * @param integer   $login_user_id ログイン会員ID
 * @param integer   $space_type
 * @param array     $current_user
 * @param array     $params
 * @param function  $fetchcallback callback関数 default メニュー形式
 *                                     $pages[space_type][thread_num][parent_id][display_sequence] = Page
 * @param array     $fetch_params callback関数 parameter
 * @param boolean   $is_all $params['join']が設定されていない場合、使用。 default false, true LEFT JOIN PageUserLink false INNER JOIN PageUserLink
 * @return array
 * @since   v 3.0.0.0
 */
	public function findMenu($type, $login_user_id = null, $space_type = NC_SPACE_TYPE_PUBLIC, $current_user = null, $params = null, $fetchcallback = null, $fetch_params = null, $is_all = false) {
		//$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
		$space_type_flag = true;
		if(is_array($space_type)) {
			foreach($space_type as $buf_space_type) {
				if($buf_space_type != NC_SPACE_TYPE_PUBLIC && $buf_space_type != NC_SPACE_TYPE_GROUP) {
					$space_type_flag = false;
					break;
				}
			}
		}
		if($space_type == NC_SPACE_TYPE_PUBLIC || $space_type == NC_SPACE_TYPE_GROUP || (is_array($space_type) && $space_type_flag)) {
			$conditions = array(
				'Page.space_type' => $space_type,
				'Page.position_flag' => _ON,
				//'Page.lang' => array('', $lang)
			);
		} else {
			$conditions = array(
				//'Page.space_type' => array(NC_SPACE_TYPE_MYPORTAL, NC_SPACE_TYPE_PRIVATE),
				'Page.position_flag' => _ON,
				'Page.display_flag !=' => NC_DISPLAY_FLAG_DISABLE,
				//'Page.lang' => array('', $lang)
			);
			if(isset($current_user)) {
				if($login_user_id != $current_user['User']['id'] || $space_type == NC_SPACE_TYPE_MYPORTAL) {
					// マイルームを表示しない。
					$conditions['Page.room_id'] = $current_user['User']['myportal_page_id'];
				} else if($space_type == NC_SPACE_TYPE_PRIVATE) {
					// マイルームのみ
					$conditions['Page.room_id'] = $current_user['User']['private_page_id'];
				} else {
					$conditions['Page.room_id'] = array($current_user['User']['myportal_page_id'], $current_user['User']['private_page_id']);
				}
			}
		}
		if(!isset($params['conditions'])) {
			$params['conditions'] = $conditions;
		} else {
			$params['conditions'] = array_merge($conditions, $params['conditions']);
		}
		if(!isset($params['conditions']['Page.thread_num'])) {
			$params['conditions']['Page.thread_num !='] = 0;
		}

		if($type != 'count' && !isset($params['order'])) {
			$params['order'] = array(
				'Page.space_type' => "ASC",
				'Page.thread_num' => "ASC",
				'Page.display_sequence' => "ASC"
			);
		}

		if($type == 'count') {
			unset($params['fields']);
		} else if(empty($login_user_id)) {
			if(!isset($params['fields'])) {
				$params['fields'] = array('Page.*');
			}
		} else {
			if(!isset($params['fields'])) {
				$params['fields'] = $this->_getFieldsArray($login_user_id, $space_type);
			}
		}
		if(!isset($params['joins'])) {
			$join_type = ($is_all) ? 'LEFT' : 'INNER';
			$params['joins'] = $this->_getJoinsArray($login_user_id, $join_type, $space_type);
		}

		if($fetchcallback === "" || ($fetchcallback === null && $type !== 'all')) {
			$results = $this->find($type, $params);
			if(isset($fetch_params['active_page_id']) && $type == 'all') {
				$parent_id_arr = array($fetch_params['active_page_id'] => true);
				if(isset($results['Page'])) {
					$results = array($results['Page']);
				}
				foreach($results as $key => $result) {
					if(isset($parent_id_arr[$result['Page']['parent_id']])) {
						$parent_id_arr[$result['Page']['id']] = true;
					} else {
						unset($results[$key]);
					}
				}
				$buf_results = array();
				$count = 0;
				foreach($results as $result) {
					$buf_results[$count] = $result;
					$count++;
				}
				$results = $buf_results;
			}
		} else if(!is_null($fetchcallback)) {
			$results = call_user_func_array($fetchcallback, array($this->find($type, $params), $fetch_params));
		} else {
			$results = $this->afterFindMenu($this->find($type, $params), $login_user_id, $fetch_params);
		}
		return $results;
	}

/**
 * 閲覧可能room_idのリストを取得
 *
 * @param   string  $type first or all or list
 * @param   array   $addParams
 * @param   integer $userId
 * @param   integer|array $spaceType
 * @param   array   $roomIdArr
 * @param   boolean $isDisplayPublicCommunity
 * @param   boolean $isDisplayMyPortal
 * @param   boolean $isDisplayPrivate
 * @return  Model Pages
 * @since   v 3.0.0.0
 */
	public function findViewableRoom($type = 'all', $addParams = array(), $userId = null, $spaceType = null, $roomIdArr = array(), $isDisplayPublicCommunity = true, $isMyPortalSelf = true) {
		return $this->findViewable($type, $addParams, $userId, $spaceType, $roomIdArr, $isDisplayPublicCommunity, $isMyPortalSelf, true);
	}

/**
 * 閲覧可能page_idのリストを取得
 * TODO:左カラム等は現状、含めていない。
 *
 * @param   string  $type first or all or list
 * @param   array   $addParams
 * @param   integer $userId
 * @param   integer|array $spaceType
 * @param   array   $roomIdArr
 * @param   boolean $isDisplayPublicCommunity
 * @param   boolean $isDisplayMyPortal
 * @param   boolean $isMyPortalSelf
 * @param   boolean $isDisplayPrivate
 * @param   boolean $isRoom
 * @return  Model Pages
 * @since   v 3.0.0.0
 */
	public function findViewable($type = 'all', $addParams = array(), $userId = null, $spaceType = null, $roomIdArr = array(), $isDisplayPublicCommunity = true, $isMyPortalSelf = true, $isRoom = false) {
		$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
		$loginUser = Configure::read(NC_SYSTEM_KEY.'.user');

		if(!isset($userId)) {
			$userId = $loginUser['id'];
		}

		$conditions = array(
			'Page.position_flag' => _ON,
			'Page.lang' => array('', $lang),
			'Page.display_flag !=' => NC_DISPLAY_FLAG_DISABLE,
			'Page.thread_num !=' => 0,
		);
		if($isRoom) {
			$conditions[] = "`Page`.`id`=`Page`.`room_id`";
		}
		if(isset($spaceType) && (!is_array($spaceType) || count($spaceType) > 0)) {
			$conditions['Page.space_type'] = $spaceType;
		}
		$joins = array(
			array(
				"type" => "LEFT",
				"table" => "communities",
				"alias" => "Community",
				"conditions" => "`Page`.`root_id`=`Community`.`room_id`"
			),
		);

		if($isMyPortalSelf) {
			$centerPage = Configure::read(NC_SYSTEM_KEY.'.'.'center_page');
			if($centerPage['Page']['id'] != $loginUser['myportal_page_id'] &&
				$centerPage['Page']['space_type'] == NC_SPACE_TYPE_MYPORTAL) {
				// マイポータルで、現在のカレントのもの取得
				// TODO:マイポータルに子グループを作成できる仕様にすると動作しない。
				App::uses('User', 'Model');
				$User = new User();
				$currentUser = $User->currentUser($centerPage, $loginUser);
			} else {
				$currentUser = array('Authority' =>array(
					'myportal_use_flag' => $loginUser['allow_myportal_viewing_hierarchy'],
					'allow_myportal_viewing_hierarchy' => $loginUser['allow_myportal_viewing_hierarchy'],
				));
			}

			if(isset($currentUser['Authority']) && (
				($currentUser['Authority']['myportal_use_flag'] == NC_MYPORTAL_USE_ALL) ||
				($currentUser['Authority']['myportal_use_flag'] == NC_MYPORTAL_MEMBERS &&
				$loginUser['hierarchy'] >= $currentUser['Authority']['allow_myportal_viewing_hierarchy']))) {
				// 参加
				$currentMyPortal = $centerPage['Page']['room_id'];
			}
		}

		if(empty($userId)) {
			// ログイン前
			if($isDisplayPublicCommunity) {
				$conditions['or'] = array(
					'Community.publication_range_flag' => NC_PUBLICATION_RANGE_FLAG_ALL,
					'Page.space_type' => NC_SPACE_TYPE_PUBLIC
				);
			} else {
				$conditions['or'] = array(
					'Page.space_type' => NC_SPACE_TYPE_PUBLIC
				);
			}
		} else {
			// ログイン後
			if($isDisplayPublicCommunity) {
				$conditions['or'] = array(
					'Community.publication_range_flag' => array(NC_PUBLICATION_RANGE_FLAG_LOGIN_USER, NC_PUBLICATION_RANGE_FLAG_ALL),
					array(
						'PageUserLink.authority_id IS NOT NULL',
						'PageUserLink.authority_id !=' => NC_AUTH_OTHER_ID,
					),
				);
			} else {
				$conditions['or'] = array(
					'Page.space_type' => NC_SPACE_TYPE_PUBLIC,
					array(
						'PageUserLink.authority_id IS NOT NULL',
						'PageUserLink.authority_id !=' => NC_AUTH_OTHER_ID,
					),
				);
			}
		}
		if(!empty($currentMyPortal)) {
			$conditions['or']['Page.room_id'] = $currentMyPortal;
		}

		if(!empty($roomIdArr) && count($roomIdArr) > 0) {
			$conditions[] = array(
				'Page.room_id' => $roomIdArr
			);
		}

		if(!empty($userId)) {
			// ログイン後
			$joins[] = array(
				"type" => "LEFT",
				"table" => "page_user_links",
				"alias" => "PageUserLink",
				"conditions" => "`PageUserLink`.`user_id`=".intval($userId).
					" AND `Page`.`room_id`=`PageUserLink`.`room_id`"
			);
			$joins[] = array(
				"type" => "LEFT",
				"table" => "authorities",
				"alias" => "Authority",
				"conditions" => "`Authority`.`id`=`PageUserLink`.`authority_id`"
			);
		}
		if(empty($spaceType) || $spaceType == NC_SPACE_TYPE_GROUP) {
			$joins[] = array(
				"type" => "LEFT",
				"table" => "community_langs",
				"alias" => "CommunityLang",
				"conditions" => "`Page`.`id`=`CommunityLang`.`room_id`".
				" AND `CommunityLang`.`lang` ='".$lang."'"
			);
		}
		$fields = array();
		$order = array();
		$page = (isset($addParams['page'])) ? $addParams['page'] : null;
		$limit = (isset($addParams['limit'])) ? $addParams['limit'] : null;
		if($type != 'count') {
			if(isset($addParams['fields'])) {
				$fields = $addParams['fields'];
			} else {
				$fields = $this->_getFieldsArray($userId, $spaceType);
			}

			if(isset($addParams['order'])) {
				$order = $addParams['order'];
			} else {
				$order = array(
					'Page.space_type' => "ASC",
					'Page.thread_num' => "ASC",
					'Page.display_sequence' => "ASC"
				);
			}
		}

		if(isset($addParams['conditions'])) {
			$conditions = array_merge($conditions, $addParams['conditions']);
		}
		if(isset($addParams['joins'])) {
			$joins = array_merge($joins, $addParams['joins']);
		}

		$params = array(
			'fields' => $fields,
			'conditions' => $conditions,
			'joins' => $joins,
			'order' => $order,
			'page' => $page,
			'limit' => $limit,
		);

		return $this->find($type, $params);
	}

/**
 * CommunityLang.community_nameを含むページ情報取得
 *
 * @param integer   $pageId
 * @return Model Page, CommunityLang.community_name
 * @since   v 3.0.0.0
 */
	public function findIncludeComunityLang($pageId) {
		$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
		$params = array(
			'fields' => array('Page.*', 'CommunityLang.community_name'),
			'joins' => array(
				array(
					"type" => "LEFT",
					"table" => "community_langs",
					"alias" => "CommunityLang",
					"conditions" => "`Page`.`room_id`=`CommunityLang`.`room_id`".
					" AND `CommunityLang`.`lang` ='".$lang."'"
				),
			),
			'conditions' => array('Page.id' => $pageId)
		);
		return $this->find('first', $params);
	}

/**
 * Current_pageの子供のページを取得
 *
 * @param string    $type first or all or list
 * @param array     $current_user
 * @param integer   $login_user_id ログイン会員ID
 * @param string    $lang
 * @return  array   $fields
 * @since   v 3.0.0.0
 */
	public function findChilds($type, $current_page, $login_user_id = null, $lang = null) {

		$lang = !isset($lang) ? $current_page['Page']['lang'] : $lang;
		$params = array('conditions' => array(
			'Page.root_id' => $current_page['Page']['root_id'],
			'Page.thread_num >' => $current_page['Page']['thread_num']
		));
		if($lang != '') {
			$params['conditions']['Page.lang'] = array('', $lang);
		}

		$fetch_params = array('active_page_id' => $current_page['Page']['id']);
		return $this->findMenu($type, $login_user_id, $current_page['Page']['space_type'], null, $params, "", $fetch_params, true);
	}

/**
 * Current_pageの子供のページを取得
 * @param integer    $login_user_id
 * @param array      $params
 * @param boolean   $is_all
 * @return  integer コミュニティー数
 * @since   v 3.0.0.0
 */
	public function findCommunityCount($login_user_id = null, $params = null, $is_all = true) {
		if(!isset($params)) {
			$params = array(
				'conditions' => array(
					'Page.thread_num' => 1
				)
			);
		}
		return $this->findMenu('count', $login_user_id, NC_SPACE_TYPE_GROUP, null, $params, null, null, $is_all);
	}

/**
 * コミュニティーpaginate用メソッド
 * @param array    $conditions
 * @param array    $fields
 * @param array    $order
 * @param array    $limit
 * @param array    $page
 * @param integer  $recursive
 * @param array    $extra
 * @return  array  コミュニティーのroom_idリスト
 * @since   v 3.0.0.0
 */
	public function paginate($conditions, $fields, $order, $limit, $page = 1, $recursive = null, $extra = array()) {
		$params = array(
			'fields' => $fields,
			'conditions' => $conditions,
			'page' => $page,
			'limit' => $limit,
			'recursive' => $recursive
		);
		if(isset($extra['is_all']) && $extra['is_all']) {
			$is_all = true;
		} else {
			$is_all = false;
		}
		return $this->findMenu('list', $extra['user_id'], NC_SPACE_TYPE_GROUP, null, $params, null, null, $is_all);
	}

/**
 * コミュニティーpaginate用メソッド(コミュニティー数)
 * @param array    $conditions
 * @param integer  $recursive
 * @param array    $extra
 * @return  integer コミュニティー数
 * @since   v 3.0.0.0
 */
	public function paginateCount($conditions = null, $recursive = 0, $extra = array()) {
		$params = array(
			'conditions' => $conditions,
			'recursive' => $recursive
		);
		return $this->findCommunityCount($extra['user_id'], $params, (isset($extra['is_all']) && $extra['is_all']) ? true : false);
	}

/**
 * Pageモデル共通Fields文
 * @param   string  $space_type
 * @return  array   $fields
 * @since   v 3.0.0.0
 */
	protected function _getFieldsArray($userId, $spaceType = null) {
		if(empty($userId)) {
			$ret = array(
				'Page.*',
			);
		} else {
			$ret = array(
				'Page.*',
				'Authority.myportal_use_flag, Authority.private_use_flag, Authority.hierarchy'
			);
		}
		if(empty($spaceType) || $spaceType == NC_SPACE_TYPE_GROUP) {
			$ret[count($ret)] = 'CommunityLang.community_name, CommunityLang.summary, CommunityLang.description';
		}
		return $ret;
	}

/**
 * Pageモデル共通JOIN文
 * @param   integer $user_id
 * @param   string  $type LEFT or INNER
 * @param   string  $space_type
 * @return  array   $joins
 * @since   v 3.0.0.0
 */
	protected function _getJoinsArray($userId, $type = 'LEFT', $spaceType = null) {
		$ret = array(
			array(
				"type" => $type,
				"table" => "page_user_links",
				"alias" => "PageUserLink",
				"conditions" => "`Page`.`room_id`=`PageUserLink`.`room_id`".
					" AND `PageUserLink`.`user_id` =".intval($userId)
			),
			array(
				"type" => "LEFT",
				"table" => "authorities",
				"alias" => "Authority",
				"conditions" => "`Authority`.`id`=`PageUserLink`.`authority_id`"
			)
		);
		if(empty($spaceType) || $spaceType == NC_SPACE_TYPE_GROUP) {
			$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
			$ret[count($ret)] = array(
				"type" => "LEFT",
				"table" => "community_langs",
				"alias" => "CommunityLang",
				"conditions" => "`Page`.`id`=`CommunityLang`.`room_id`".
					" AND `CommunityLang`.`lang` ='".$lang."'"
			);
		}
		return $ret;
	}

/**
 * ルーム（ページ）削除処理
 * @param mixed $id ID of record to delete
 * @param boolean $all_delete コンテンツもすべて削除するかどうか（NC_DELETE_MOVE_PARENTの場合、コンテンツを親のコンテンツへ）
 * @param Model Page $child_pages 指定されていなければ取得
 * @param integer $parent_room_id $all_delete NC_DELETE_MOVE_PARENTの場合の振り替え先room_id
 * @param boolean $is_recursive 再帰的に呼ばれたかどうか
 * @return boolean True on success
 * @since   v 3.0.0.0
 */
	public function deletePage($id = null, $all_delete = _OFF, $child_pages = null, $parent_room_id = null, $is_recursive = false) {
		if (!empty($id)) {
			$this->id = $id;
		}
		$id = $this->id;

		$page = $this->findById($id);
		if(!$page) {
			return false;
		}

		if($all_delete == NC_DELETE_MOVE_PARENT && !isset($parent_room_id)) {
			$parent_page = $this->findById($page['Page']['parent_id']);
			$parent_room_id = isset($parent_page['Page']) ? $parent_page['Page']['room_id'] : 0;
		}

		/*
		 * ブロック削除
		*/
		App::uses('Block', 'Model');
		$Block = new Block();
		$blocks = $Block->findByPageId($id);
		if($blocks != false && count($blocks) > 0) {
			if(isset($blocks['Block'])) {
				$blocks = array($blocks);
			}
			foreach($blocks as $block) {
				$Block->deleteBlock($block, $all_delete, $parent_room_id, true);
			}
		}

		if(!$is_recursive) {
			// 子ページ削除処理
			if(!isset($child_pages)) {
				$user = Configure::read(NC_SYSTEM_KEY.'.user');
				$child_pages = $this->findChilds('all', $page, $user['id']);
			}
			foreach($child_pages as $child_page) {
				if(!$this->deletePage($child_page['Page']['id'], $all_delete, null, $parent_room_id, true)) {
					return false;
				}
			}
			//前詰め処理
			if($page['Page']['thread_num'] == 1) {
				$childs_count = 0;
			} else {
				$childs_count = count($child_pages);
			}

			if(!$this->decrementDisplaySeq($page, $childs_count + 1)) {
				return false;
			}
		}

		// TODO:page_columns削除
		// TODO:page_metas削除
		// TODO:page_styles削除
		// TODO:page_sum_views削除
		// TODO:page_columns削除
		// TODO:page_themes削除
		// TODO:uploads削除
		// コミュニティーの写真、コミュニティーのWYSIWYGの画像も含まれる。
		// TODO:menu削除

		if($page['Page']['id'] == $page['Page']['room_id']) {
			// ルーム
			App::uses('PageUserLink', 'Model');
			$PageUserLink = new PageUserLink();
			$conditions = array(
				"PageUserLink.room_id" => $page['Page']['id']
			);
			$ret = $PageUserLink->deleteAll($conditions);
			if(!$ret) {
				return false;
			}

			App::uses('ModuleLink', 'Model');
			$ModuleLink = new ModuleLink();
			$conditions = array(
				"ModuleLink.room_id" => $page['Page']['id']
			);
			$ret = $ModuleLink->deleteAll($conditions);
			if(!$ret) {
				return false;
			}

			if($page['Page']['thread_num'] == 1 && $page['Page']['space_type'] == NC_SPACE_TYPE_GROUP) {
				// コミュニティー削除
				App::uses('Community', 'Model');
				$Community = new Community();
				$conditions = array(
					"Community.room_id" => $page['Page']['id']
				);
				$ret = $Community->deleteAll($conditions);
				if(!$ret) {
					return false;
				}

				App::uses('CommunityLang', 'Model');
				$CommunityLang = new CommunityLang();
				$conditions = array(
					"CommunityLang.room_id" => $page['Page']['id']
				);
				$ret = $CommunityLang->deleteAll($conditions);
				if(!$ret) {
					return false;
				}

				App::uses('CommunityTag', 'Model');
				$CommunityTag = new CommunityTag();
				$params = array(
					'fields' => array('CommunityTag.tag_id'),
					'conditions' => array(
						"CommunityTag.room_id" => $page['Page']['id']
					)
				);

				$communities_tag_ids = $CommunityTag->find('list', $params);
				if(count($communities_tag_ids) > 0) {
					$conditions = array(
						"CommunityTag.room_id" => $page['Page']['id']
					);
					$ret = $CommunityTag->deleteAll($conditions);
					if(!$ret) {
						return false;
					}

					App::uses('Tag', 'Model');
					$Tag = new Tag();
					$params = array(
						'conditions' => array(
							"Tag.id" => $communities_tag_ids
						)
					);

					$tags = $Tag->find('all', $params);
					foreach($tags as $tag) {
						if($tag['Tag']['used_number'] <= 1) {
							// delete
							$ret = $Tag->delete($tag['Tag']['id']);
						} else {
							// update
							$fields = array('Tag.used_number'=> intval($tag['Tag']['used_number']) - 1);
							$conditions = array(
								"Tag.id" => $tag['Tag']['id']
							);
							$ret = $Tag->updateAll($fields, $conditions);
						}
						if(!$ret) {
							return false;
						}
					}
				}
			}

			// ブロックとして配置していない該当ルームのコンテンツを親ルームがあれば、そちらへ移動、なければ完全に削除。
			App::uses('Content', 'Model');
			$Content = new Content();
			$params = array(
				'conditions' => array(
					"Content.room_id" => $page['Page']['room_id']
				)
			);

			$delete_contents = $Content->find('all', $params);
			if(count($delete_contents) > 0) {
				foreach($delete_contents as $delete_content) {
					$Content->deleteContent($delete_content, NC_DELETE_MOVE_PARENT, $parent_room_id);	// $all_delete
				}
			}
		}

		// 削除処理
		$ret = $this->delete($id);
		if($ret === false) {
			return $ret;
		}

		/*
		 * 削除されたページがConfig.first_startpage_id,second_startpage_id,third_startpage_idならば、更新
		* (パブリックに更新)
		*/
		App::uses('Config', 'Model');
		$Config = new Config();
		$conditions = array(
			'module_id' => 0,
			'cat_id' => NC_LOGIN_CATID,
			'name' => array('first_startpage_id','second_startpage_id','third_startpage_id')
		);
		$params = array(
			'fields' => array(
				'Config.name',
				'Config.value'
			),
			'conditions' => $conditions
		);
		$configs = $Config->find('all', $params);
		$fields = array(
			'Config.value'=> 0
		);
		if($id == $configs['first_startpage_id'] && !$Config->updateAll($fields, array('Config.name' => 'first_startpage_id'))) {
			return false;
		}
		if($id == $configs['second_startpage_id'] && !$Config->updateAll($fields, array('Config.name' => 'second_startpage_id'))) {
			return false;
		}
		if($id == $configs['third_startpage_id'] && !$Config->updateAll($fields, array('Config.name' => 'third_startpage_id'))) {
			return false;
		}

		return true;
	}

/**
 * display_sequenceデクリメント処理
 *
 * @param  array     $page ページテーブル配列
 * @param  integer   $display_sequence デクリメントする数
 * @return boolean true or false
 * @since   v 3.0.0.0
 */
	public function decrementDisplaySeq($page = null,$display_sequence = 1, $conditions = array()) {
		$display_sequence = -1*$display_sequence;
		return $this->_operationDisplaySeq($page, $display_sequence, $conditions);
	}

/**
 * display_sequenceインクリメント処理
 *
 * @param  array     $page ページテーブル配列
 * @param  integer   $display_sequence インクリメントする数
 * @return boolean true or false
 * @since   v 3.0.0.0
 */
	public function incrementDisplaySeq($page = null,$display_sequence = 1, $conditions = array()) {
		return $this->_operationDisplaySeq($page, $display_sequence, $conditions);
	}

	protected function _operationDisplaySeq($page = null,$display_sequence = 1, $conditions = array()) {
		$lang = Configure::read(NC_CONFIG_KEY.'.'.'language');
		$fields = array('Page.display_sequence'=>'Page.display_sequence+('.$display_sequence.')');
		$conditions = array_merge($conditions, array(
			"Page.position_flag" => $page['Page']['position_flag'],
			"Page.lang" => array("", $lang),
			"Page.space_type" => $page['Page']['space_type'],
			"Page.display_sequence >=" => $page['Page']['display_sequence']
		));
		if($page['Page']['thread_num'] == 1) {
			$conditions["Page.thread_num"] = 1;
		} else {
			$conditions["Page.root_id"] = $page['Page']['root_id'];
			$conditions["Page.thread_num >"] = 1;
		}
		$ret = $this->updateAll($fields, $conditions);
		return $ret;
	}

/**
 * 移動後固定リンク取得
 *
 * @param  Model Page     $page
 * @param  Model Page     $parent_page 移動先親Page
 * @return boolean true or false
 * @since   v 3.0.0.0
 */
	public function getMovePermalink($page, $parent_page) {
		if($page['Page']['permalink'] == '') {
			// Topページ
			$page['Page']['permalink'] = preg_replace(NC_PERMALINK_PROHIBITION, NC_PERMALINK_PROHIBITION_REPLACE, $page['Page']['page_name']);
		}
		$permalink_arr = explode('/', $page['Page']['permalink']);
		if($parent_page['Page']['permalink'] != '') {
			$permalink = $parent_page['Page']['permalink'] . '/' . $permalink_arr[count($permalink_arr)-1];
		} else {
			$permalink = $permalink_arr[count($permalink_arr)-1];
		}
		return $permalink;
	}
}