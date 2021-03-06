<?php
/**
 * UsersControllerクラス
 *
 * <pre>
 * ログイン、ログアウト用コントローラ
 * </pre>
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       App.Controller
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */

App::uses('AppController', 'Controller');

/**
 * Static content controller
 *
 * Override this controller by placing a copy in controllers directory of an application
 *
 * @package       app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers/pages-controller.html
 */
class UsersController extends AppController {

/**
 * Controller name
 *
 * @var string
 */
	public $name = 'Users';

/**
 * Model name
 *
 * @var array
 */
	public $uses = array();

/**
 * Component name
 *
 * @var array
 */
	public $components = array('Cookie', 'CheckAuth' => array('chkBlockId' => false));

/**
 * Config ログイン関連
 *
 * @var array
 */
	public $configs = null;

/**
 * コントローラの実行前処理
 * <pre>
 * ログイン処理の設定
 * </pre>
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function beforeFilter() {
		parent::beforeFilter();
		/*
		//ログイン処理を行うactionを指定
		$this->Auth->loginAction = array('controller' => 'users', 'action' => 'login');

		$this->Auth->logoutRedirect = '/';

		//ユーザーIDとパスワードのフィールドを指定
		$this->Auth->authenticate = array('MyForm' =>
			array(
				'fields' => array('username' => 'login_id'),
				'scope' => array('User.is_active' => NC_USER_IS_ACTIVE_ON),
				'findFields' => array(
									'id', 'login_id', 'handle', 'username', 'authority_id',
									'permalink', 'myportal_page_id', 'private_page_id', 'avatar',
									'lang', 'timezone_offset', 'email', 'mobile_email',
									'Authority.hierarchy', 'Authority.myportal_use_flag', 'Authority.private_use_flag',
									'Authority.public_createroom_flag', 'Authority.group_createroom_flag', 'Authority.myportal_createroom_flag',
									'Authority.private_createroom_flag', 'Authority.allow_htmltag_flag', 'Authority.allow_layout_flag',
									'Authority.allow_attachment', 'Authority.allow_video', 'Authority.change_leftcolumn_flag',
									'Authority.change_rightcolumn_flag', 'Authority.change_headercolumn_flag', 'Authority.change_footercolumn_flag',
								),
			)
		);
		$this->Auth->allow('login');

		//権限が無いactionを実行した際のエラーメッセージ
        $this->Auth->authError = __('Forbidden permission to access the page.');
        //ログイン後にリダイレクトするURL
        //$this->Auth->loginRedirect = '/users/index';
         */
	}

/**
 * ログイン処理
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function login() {
		$configs = Configure::read(NC_CONFIG_KEY);
		$this->Cookie->name = $configs['autologin_cookie_name'];
		if(!empty($this->request->data)){
			if(!$this->Auth->login()) {
                $this->Session->setFlash(__('Incorrect Login.Again, please enter.'), 'default', array(), 'auth');
            }
            $this->Session->setFlash(__('Login.'));
			$user = $this->Auth->user();//認証済みユーザーを取得

			//フォームからのデータの場合
			if($configs['autologin_use'] == NC_AUTOLOGIN_ON) {
				if (isset($user) && empty($this->request->data['User']['login_save_my_info'])) {
					//パスポート不要なので削除
					$this->Common->passportDelete($user);
				} else if(isset($user)) {
					//パスポート発行する
					$this->Common->passportWrite($user);
				}
			} else if($configs['autologin_use'] == NC_AUTOLOGIN_LOGIN) {
				$cookie = array('login_id'=>$this->request->data['User']['login_id']);
        		$this->Cookie->write('User', $cookie, true,"+ ".$configs['autologin_expires']);
			}
			unset($this->request->data['User']['login_save_my_info']);
			if ($user) {
				//新しいセッションＩＤの発行と、古いセッションの破棄
                //$this->Session->renew();

				// 最終ログイン日時更新
				$bufUser['User'] = $user;
				$this->User->updLastLogin($bufUser);
				//ログインできた、リダイレクトする
				$this->Auth->loginRedirect = $this->Common->redirectStartpage($configs);
				return $this->redirect($this->Auth->redirect());
			}
		} else {
			$user = $this->Auth->user();//認証済みユーザーを取得
		}

		if (isset($user)) {
			//認証できたユーザー。
			$this->Auth->loginRedirect = $this->Common->redirectStartpage($configs);
			$this->flash(__('You are already logged in.'), $this->Auth->redirect());
		} else if($configs['autologin_use'] == NC_AUTOLOGIN_LOGIN) {
			$cookiePassport=$this->Cookie->Read('User');
			if(isset($cookiePassport['login_id'])){
				$this->request->data['User']['login_id'] = $cookiePassport['login_id'];
			}
		}
		$this->set('autologin_use', $configs['autologin_use']);
		$this->set('login_autocomplete', $configs['login_autocomplete']);
	}

/**
 * ログアウト処理
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
    function logout() {
    	$configs = Configure::read(NC_CONFIG_KEY);
    	$user = $this->Auth->user();
    	//if($configs['autologin_use'] == NC_AUTOLOGIN_OFF) {
	        $this->Common->passportDelete($user);
    	//}
		$this->Session->renew();
        $this->Session->setFlash(__('You are now logged out.'));
        $this->redirect($this->Auth->logout());	//ログアウトし、ログイン画面へリダイレクト
    }
}
