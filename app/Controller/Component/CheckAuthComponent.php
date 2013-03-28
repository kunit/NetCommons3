<?php
/**
 * CheckAuthComponentクラス
 *
 * <pre>
 * 権限をチェックするComponentクラス
 * </pre>
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       App.Controllers.Components
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */
class CheckAuthComponent extends Component {
/**
 * Controller
 *
 * @var     object
 */
	protected $_controller = null;

/**
 * Other components utilized by Component
 *
 * @var     array
 */
	public $components = array('Auth', 'Session');	// , 'Init'

/**
 * 許す権限
 *
 * @var integer
 * default Auto
 */
	public $allowAuth = null;

/**
 * 許す会員権限
 *
 * @var integer
 * default 誰でも閲覧可
 */
	public $allowUserAuth = NC_AUTH_OTHER;

/**
 * チェックしないURL(正規表現にて記述)
 *
 * @var  string
 */
	public $prohibitionURL = NC_PROHIBITION_URL;

/**
 * パーマリンクからのPageとblock_idのはってあるページが一致しているかどうかのチェックするかどうか
 * @var boolean
 */
	public $chkMovedPermanently = true;

/**
 * 一般モジュールでblock_idをチェックするかどうか
 * @var boolean
 */
	public $chkBlockId = true;

/**
 * pageデータを取得する条件の順番
 * 取得に失敗した場合、次を行う
 * @var array
 * url:URLから取得
 *      permalink OR
 *		module(act)/$active_plugin/$active_controller/$block_id OR
 *		module(act)/$block_id
 * request:リクエストパラメータのpage_id($requestPageId)から取得
 */
	public $checkOrder = array("url", "request");

/**
 * リクエストのpage_id名称
 * @var string
 */
	public $requestPageId = "page_id";

/**
 * Constructor
 *
 * @param ComponentCollection $collection A ComponentCollection this component can use to lazy load its components
 * @param array $settings Array of configuration settings.
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$this->_controller = $collection->getController();
		parent::__construct($collection, $settings);
	}

/**
 * 権限チェック
 *
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function check() {
		$controller = $this->_controller;
		//$this->_setControllerParams($controller);
		$user = $this->Auth->user();//認証済みユーザーを取得
		$controller_name = $controller->request->params['controller'];
		$plugin_name = $controller->request->params['plugin'];
		$user_id = isset($user['id']) ? intval($user['id']) : 0;
		Configure::write(NC_SYSTEM_KEY.'.user_id', $user_id);
		$redirect_url = ($user_id == 0) ? '/users/login' : null;

		if(isset($plugin_name) && $plugin_name != 'group') {
			$camel_plugin_name = Inflector::camelize($plugin_name);
			$module = $controller->Module->findByDirname($camel_plugin_name);
			if(isset($module['Module']['dir_name']) && $module['Module']['dir_name'] != $camel_plugin_name) {
				$controller->flash(__('Content not found.'), '', 'CheckAuth.check.001', '404');
				return;
			}
			$controller->nc_module = $module;
			Configure::write(NC_SYSTEM_KEY.'.Modules.'.$camel_plugin_name, $module);
		}

		if($plugin_name == 'page' || $controller_name == 'pages' || $controller_name == 'group' ||
				$controller_name == 'users' || $module['Module']['system_flag'] == _OFF) {
			// 一般系モジュール
			if(!$this->checkGeneral()) {
				return;
			}
		} else {
			// 管理系モジュール
			if(!$this->checkAdmin($module['Module']['id'])) {
				return;
			}
		}

		if($controller->request->header('X-NC-PAGE') || ($controller->request->params['plugin'] == '' && $controller->request->params['controller'] == 'pages')) {
			Configure::write(NC_SYSTEM_KEY.'.block_type', 'blocks');
		}

		// 権限チェック
		if(!isset($this->allowAuth) && isset($page['Page'])) {
			if($page['Page']['space_type'] == NC_SPACE_TYPE_PUBLIC) {
				$this->allowAuth = NC_AUTH_OTHER;
			} else if($page['Page']['space_type'] == NC_SPACE_TYPE_MYPORTAL) {
				// TODO:Configにマイポータルを公開か、ログイン会員のみかどうかをもつ必要あり
				// 現状、常に公開
				$this->allowAuth = NC_AUTH_OTHER;
			} else if($page['Page']['space_type'] == NC_SPACE_TYPE_PRIVATE) {
				$this->allowAuth = NC_AUTH_GUEST;;
			} else {
				// コミュニティ
				$params = array(
					'fields' => array(
						'Community.publication_range_flag'
					),
					'conditions' => array('room_id' => $page['Page']['root_id']),
				);
				$current_community = $controller->Community->find('first', $params);
				if($current_community['Community']['publication_range_flag'] == NC_PUBLICATION_RANGE_FLAG_ALL) {
					// 公開
					$this->allowAuth = NC_AUTH_OTHER;
				} else {
					$this->allowAuth = NC_AUTH_GUEST;
				}
			}
		}

		if(!$this->checkAuth($controller->hierarchy)) {
			if($user_id == 0) {
				$this->Session->setFlash(__('Forbidden permission to access the page.'), 'default', array(), 'auth');
			}
			$controller->flash(__('Forbidden permission to access the page.'), $redirect_url, 'CheckAuth.check.003', '403');
			return;
		}


		if(isset($controller->nc_block['Block']) && !$controller->nc_block['Block']['is_master']) {
			// 編集画面なのに権限を付与したショートカットならばエラー。ただし、ショートカット先が主坦ならばエラーとしない。
			$content =  $controller->Content->findAuthById($controller->nc_block['Block']['master_id'], $user_id);
			$ret = $this->checkAuth($content['Authority']['hierarchy'], NC_AUTH_CHIEF);
			if($controller->nc_is_edit && !$ret) {
				$controller->flash(__('Forbidden permission to access the page.'), $redirect_url, 'CheckAuth.check.004', '403');
				return;
			}
		} else {
			$ret = $this->checkAuth($controller->hierarchy, NC_AUTH_CHIEF);
		}
		$controller->nc_show_edit = $ret;

		// 権限チェック(会員権限)
		if(!$this->checkUserAuth($user['hierarchy'])) {
			if($user_id == 0) {
				$this->Session->setFlash(__('Forbidden permission to access the page.'), 'default', array(), 'auth');
			}
			$controller->flash(__('Forbidden permission to access the page.'), $redirect_url, 'CheckAuth.check.005', '403');
			return;
		}

		if($controller->hierarchy >= NC_AUTH_MIN_CHIEF) {
			$controller->is_chief = true;
		} else {
			$controller->is_chief = false;
		}
	}

/**
 * 管理系権限チェック
 *
 * @param   integer $module_id
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function checkAdmin($module_id) {
		$controller = $this->_controller;
		$user = $this->Auth->user();
		$user_id = isset($user['id']) ? intval($user['id']) : 0;
		$redirect_url = ($user_id == 0) ? '/users/login' : null;
		$url = $controller->request->url;
		$authority_id = isset($user['authority_id']) ? intval($user['authority_id']) : NC_AUTH_OTHER;

		// メンバ変数セット
		$controller->id = '_system'. $module_id;
		$controller->module_id = $module_id;

		$controller->hierarchy = $controller->ModuleSystemLink->findHierarchy($module_id, $authority_id);

		return true;
	}

/**
 * 一般系系権限チェック
 *
 * @param   Model Module $module
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function checkGeneral() {
		$controller = $this->_controller;
		$user = $this->Auth->user();
		$user_id = isset($user['id']) ? intval($user['id']) : 0;
		$this->_setControllerParams($controller);
		if (isset($controller->nc_block['Block'])) {
			// 既に権限チェック済 - PageからrequestActionよりブロックを表示した場合
			return true;
		}
		$redirect_url = ($user_id == 0) ? '/users/login' : null;
		$url = $controller->request->url;

		$permalink = isset($controller->request->params['permalink']) ? $controller->request->params['permalink'] : '';
		$block_id = isset($controller->request->params['block_id']) ? intval($controller->request->params['block_id']) : 0;
		$data_page_id = !empty($controller->request->data[$this->requestPageId]) ? $controller->request->data[$this->requestPageId] : 0;
		$named_page_id = !empty($controller->request->named[$this->requestPageId]) ? $controller->request->named[$this->requestPageId] : 0;
		$url_page_id = !empty($controller->request->query[$this->requestPageId]) ? $controller->request->query[$this->requestPageId] : 0;
		$page_id = !empty($data_page_id) ? $data_page_id : (!empty($named_page_id) ? $named_page_id : (!empty($url_page_id) ? $url_page_id : 0));

		$controller_name = $controller->request->params['controller'];
		$action_name = $controller->request->params['action'];
		$lang = $this->Session->read(NC_CONFIG_KEY.'.'.'language');

		$permalink = trim($permalink, '/');
		Configure::write(NC_SYSTEM_KEY.'.permalink', $permalink);
		Configure::write(NC_SYSTEM_KEY.'.block_id', $block_id);

		// メンバ変数セット
		$controller->id = '_'. $block_id;
		$controller->block_id = $block_id;

		if(!preg_match($this->prohibitionURL, $url)) {
			if($block_id > 0) {
				$block =  $controller->Block->findAuthById($block_id, $user_id);
				if($block === false || !isset($block['Block'])) {
					// 置いているpluginが異なる
					$controller->flash(__('Content not found.'), '', 'CheckAuth.checkGeneral.001', '404');
					return false;
				}
				$controller->nc_block = $block;

				$active_page = $controller->Page->findById($block['Block']['page_id']);
				if(!$active_page) {
					// ブロックIDに対応したページが存在しない
					$controller->flash(__('Page not found.'), $redirect_url, 'CheckAuth.checkGeneral.002', '404');
					return false;
				}
			}

			$result = $this->_getPage($controller, $permalink, $page_id, $user_id, $lang);

			if($result === false) {
				$page = false;
				$center_page = false;
			} else {
				list($center_page, $page) = $result;
			}
			Configure::write(NC_SYSTEM_KEY.'.'.'center_page', $center_page);
			if($this->chkMovedPermanently && isset($active_page) && $active_page['Page']['position_flag'] &&
					($page === false || $page['Page']['id'] != $active_page['Page']['id'])) {
				// パーマリンクからのPageとblock_idのはってあるページが一致しなければエラー
				// ３０１をかえして、その後、移動後のページへ遷移させる
				// TODO:中央カラムのみにはってあるブロックのみチェックしているが、ヘッダーのブロックのチェックもするべき
				$redirect_url = preg_replace('$^'.$permalink.'$i', $active_page['Page']['permalink'], $url);
				$controller->flash(__('Moved Permanently.'), $redirect_url, 'CheckAuth.checkGeneral.003', '301');
				return false;
			}

			if($page === false || $center_page === false) {
				// ページが存在しない
				$controller->flash(__('Page not found.'), $redirect_url, 'CheckAuth.checkGeneral.004', '404');
				return false;
			}

			$controller->nc_page = isset($active_page) ? $active_page : $page;	// blockから取得できるPage優先
			$controller->nc_current_page = $page;								// pageから取得できるPage
			if($page['Page']['display_flag'] == NC_DISPLAY_FLAG_DISABLE ||
					($page['Page']['display_flag'] == NC_DISPLAY_FLAG_OFF && $page['Authority']['hierarchy'] < NC_AUTH_MIN_CHIEF)) {
				$controller->flash(__('Content not found.'), $redirect_url, 'CheckAuth.checkGeneral.005', '404');
				return false;
			}

			// PageとBlockのhierarchyの低いほうをセット
			$page_hierarchy = (isset($page) && !is_null($page['Authority']['hierarchy'])) ? intval($page['Authority']['hierarchy']) : NC_AUTH_OTHER;
			$block_hierarchy = (isset($block) && !is_null($block['Block']['hierarchy'])) ? intval($block['Block']['hierarchy']) : $page_hierarchy;
			$controller->hierarchy = min($block_hierarchy, $page_hierarchy);

			if(isset($block) && !is_null($block['Content']['master_id'])) {
				$controller->content_id = intval($block['Content']['master_id']);
			}

			$controller->page_id = isset($block['Block']['page_id']) ? intval(($block['Block']['page_id'])) : intval($page['Page']['id']);

			//TODO:test
			/*
			 * ページ
			*/
			if($controller_name == 'pages') {
				// test
				$page_id_arr = array($page['Page']['id'], 5, 6, 7, 8);
				// ページ内のカラムページのリストをセット
				$controller->page_id_arr = $page_id_arr;
			}
			//$controller->page_id = $page['Page']['id'];

			// TODO:ログインに遷移する場合、以下を記述
			//$controller->Auth->deny();
		}

		// block_idチェック
		if($this->chkBlockId) {
			if(!isset($block_id) || $block_id == 0) {
				$controller->flash(__('Content not found.'), $redirect_url, 'CheckAuth.checkGeneral.006', '404');
				return false;
			}
		}

		/**
		 * Setting Mode
		 */
		$mode = $this->Session->read(NC_SYSTEM_KEY.'.mode');
		if($controller->hierarchy >= NC_AUTH_MIN_CHIEF) {
			if(isset($controller->request->query['setting_mode']) &&
					!is_null($controller->request->query['setting_mode'])) {
				switch ($controller->request->query['setting_mode']) {
					case NC_GENERAL_MODE:
						$this->Session->delete(NC_SYSTEM_KEY.'.mode');
						break;
					case NC_BLOCK_MODE:
						$this->Session->write(NC_SYSTEM_KEY.'.mode', NC_BLOCK_MODE);
						break;
				}
			}
		} else if($user_id == 0 && !empty($mode)) {
			$this->Session->delete(NC_SYSTEM_KEY.'.mode');
		}

		return true;
	}

/**
 * 権限チェック
 *
 * @param   integer $hierarchy
 * @param   integer $allowAuth
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function checkAuth($hierarchy, $allowAuth = null) {
		if(!isset($allowAuth)) {
			$allowAuth = ($this->allowAuth == null) ? NC_AUTH_OTHER : $this->allowAuth;
		}
		$allowAuth = $this->_getMinAuth($allowAuth);

		if($hierarchy < $allowAuth) {	// $page_id != 0 &&
			return false;
		}
		return true;
	}

/**
 * 権限チェック
 *
 * @param   integer $hierarchy
 * @param   integer $allowUserAuth
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function checkUserAuth($hierarchy, $allowUserAuth = null) {
		if(!isset($allowUserAuth)) {
			$allowUserAuth = ($this->allowUserAuth == null) ? NC_AUTH_OTHER : $this->allowUserAuth;
		}
		$allowUserAuth = $this->_getMinAuth($allowUserAuth);
		if(intval($hierarchy) < $allowUserAuth) {
			return false;
		}
		return true;
	}

/**
 * Page取得
 * <pre>
 * Page情報を取得する
 * </pre>
 *
 * @param   Controller $controller Instantiating controller
 * @param   string  $permalink
 * @param   integer $user_id
 * @param   string  $lang
 * @return  array ($center_page, $page) エラーの場合、false
 * @since   v 3.0.0.0
 */
	protected function _getPage(Controller $controller, $permalink, $page_id, $user_id, $lang) {
		$page = null;
		$request_page = null;
		$center_page = false;
		if(empty($user_id)) {
			$page_params = array(
				'fields' => array(
					'Page.*'
				)
			);
		} else {
			$page_params = array(
				'fields' => array(
					'Page.*,'.
					'Authority.hierarchy'
				),
				'joins' => array(
					array(
						"type" => "LEFT",
						"table" => "page_user_links",
						"alias" => "PageUserLink",
						"conditions" => "`Page`.`room_id`=`PageUserLink`.`room_id`".
							" AND `PageUserLink`.`user_id` =".$user_id
						),
					array(
						"type" => "LEFT",
						"table" => "authorities",
						"alias" => "Authority",
						"conditions" => "`Authority`.id``=`PageUserLink`.`authority_id`"
					)
				)
			);
		}

		foreach($this->checkOrder as $order) {
			unset($page_params['conditions']);
			switch($order) {
				case "url":
					$space_type = NC_SPACE_TYPE_PUBLIC;
					if($permalink != '') {
						if(NC_SPACE_PUBLIC_PREFIX != '' && substr($permalink, 0, strlen(NC_SPACE_PUBLIC_PREFIX) + 1) == NC_SPACE_PUBLIC_PREFIX.'/') {
							$permalink = substr($permalink, strlen(NC_SPACE_PUBLIC_PREFIX) + 1);
						} else if(NC_SPACE_MYPORTAL_PREFIX != '' && substr($permalink, 0, strlen(NC_SPACE_MYPORTAL_PREFIX) + 1) == NC_SPACE_MYPORTAL_PREFIX.'/') {
							$space_type = NC_SPACE_TYPE_MYPORTAL;
							$permalink = substr($permalink, strlen(NC_SPACE_MYPORTAL_PREFIX) + 1);
						} else if(NC_SPACE_PRIVATE_PREFIX != '' && substr($permalink, 0, strlen(NC_SPACE_PRIVATE_PREFIX) + 1) == NC_SPACE_PRIVATE_PREFIX.'/') {
							$space_type = NC_SPACE_TYPE_PRIVATE;
							$permalink = substr($permalink, strlen(NC_SPACE_PRIVATE_PREFIX) + 1);
						} else if(NC_SPACE_GROUP_PREFIX != '' && substr($permalink, 0, strlen(NC_SPACE_GROUP_PREFIX) + 1) == NC_SPACE_GROUP_PREFIX.'/') {
							$space_type = NC_SPACE_TYPE_GROUP;
							$permalink = substr($permalink, strlen(NC_SPACE_GROUP_PREFIX) + 1);
						}
					}
					if($permalink == "") {
						// トップページ
						$page_params['conditions'] = array(
							'Page.permalink' => '',
							'Page.position_flag' => _ON,
							'Page.space_type' => $space_type,
							'Page.display_sequence' => 1,
							'Page.thread_num' => 2
						);
					} else {
						$page_params['conditions'] = array(
							'Page.permalink' => $permalink,
							'Page.position_flag' => _ON,
							'Page.space_type' => $space_type,
							'Page.display_sequence !=' => 0,
							'Page.thread_num >' => 1
						);
					}
					break;
				case "request":
					if($page_id == 0) {
						continue;
					} else {
						$page_params['conditions'] = array('Page.id' => $page_id);
					}
					break;
			}

			if(empty($page_params['conditions']))
				continue;

			$pages = $controller->Page->find('all', $page_params);
			$page = null;
			if(isset($pages[0])) {
				$active_lang = null;
				foreach($pages as $current_page) {
					if($current_page['Page']['lang'] == $lang) {
						$page = $current_page;
						break;
					} else if(($active_lang === null) ||
							($active_lang !== '' && $active_lang != 'en')) {
						// 英語を優先的に表示する
						$active_lang = $current_page['Page']['lang'];
						$page = $current_page;
					}
				}
				if(isset($page['Page'])) {
					if($order == 'url') {
						$center_page  = $page;
						break;
					} else {
						$request_page = $page;
					}
				}
			}
		}
		if($request_page) {
			$page = $request_page;
		}

		if(!isset($page)) {
			return false;
		}
		$page = $controller->Page->afterFindIds($page, $user_id);

		return array($center_page, $page);
	}

	protected function _setControllerParams(Controller $controller) {
		$blocks = Configure::read(NC_SYSTEM_KEY.'.blocks');
		$block = Configure::read(NC_SYSTEM_KEY.'.block');
		$page = Configure::read(NC_SYSTEM_KEY.'.page');

		$controller->hierarchy = NC_AUTH_OTHER;	// 初期化
		$controller->content_id = 0;			// 初期化

		if(isset($blocks)) {
			$controller->nc_blocks = $blocks;
			Configure::delete(NC_SYSTEM_KEY.'.blocks');
		}

		if(isset($block)) {
			$controller->nc_block = $block;
			Configure::delete(NC_SYSTEM_KEY.'.block');

			$controller->id = '_'.intval($block['Block']['id']);
			$controller->block_id = intval($block['Block']['id']);
			Configure::write(NC_SYSTEM_KEY.'.block_id', intval($block['Block']['id']));

			if(!is_null($block['Block']['hierarchy'])) {
				$controller->hierarchy = intval($block['Block']['hierarchy']);
			}
			if(!is_null($block['Content']['master_id'])) {
				$controller->content_id = intval($block['Content']['master_id']);
			}
		}

		if(isset($page)) {
			$controller->nc_page = $page;
			Configure::delete(NC_SYSTEM_KEY.'.page');
		}
	}

/**
 * チェックする権限の最小値を取得
 *
 * @param   string $name
 * @return  string $name
 * @since   v 3.0.0.0
 */
	protected function _getMinAuth($name) {
		switch($name) {
			case NC_AUTH_GENERAL:
				$name = NC_AUTH_MIN_GENERAL;
				break;
			case NC_AUTH_MODERATE:
				$name = NC_AUTH_MIN_MODERATE;
				break;
			case NC_AUTH_CHIEF:
				$name = NC_AUTH_MIN_CHIEF;
				break;
			case NC_AUTH_ADMIN:
				$name = NC_AUTH_MIN_ADMIN;
				break;
		}

		return $name;
	}
}