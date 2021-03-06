<?php
/**
 * BlogCommentモデル
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       app.Model
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */
class BlogComment extends AppModel
{
	public $name = 'BlogComment';

	public $order = array("BlogComment.lft" => "ASC");

	public $actsAs = array('Tree');

/**
 * バリデート処理
 *
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function __construct() {
		parent::__construct();

		include_once dirname(dirname(__FILE__)).'/Config/defines.inc.php';

		/*
		 * エラーメッセージ設定
		*/
		$this->validate = array(
			'content_id' => array(
				'numeric' => array(
					'rule' => array('numeric'),
					'required' => true,
					'allowEmpty' => false,
					'message' => __('The input must be a number.')
				),
			),
			'blog_post_id' => array(
				'numeric' => array(
					'rule' => array('numeric'),
					'required' => true,
					'allowEmpty' => false,
					'message' => __('The input must be a number.')
				),
			),
			'parent_id' => array(
				'numeric' => array(
					'rule' => array('numeric'),
					'allowEmpty' => true,
					'message' => __('The input must be a number.')
				),
			),
			// TODO:トラックバックのタイトルがはいるのみ？バリデートが必要ならば追加する。
			// 'title',

			'comment' => array(
				'notEmpty'  => array(
					'rule' => array('notEmpty'),
					'message' => __('Please input %s.', __('Comment')),
					'required' => true,
				),
			),
			'author' => array(
				'maxlength'  => array(
					'rule' => array('maxLength', NC_VALIDATOR_USER_NAME_LEN),
					'message' => __('The input must be up to %s characters.', NC_VALIDATOR_USER_NAME_LEN),
				),
			),
			'author_email' => array(
				'email' => array(
					'rule' => array('email'),
					'message' => __('The input must be a %s.', __('E-mail')),
					'allowEmpty' => true,
				),
				'maxlength'  => array(
					'rule' => array('maxLength', NC_VALIDATOR_VARCHAR_LEN),
					'message' => __('The input must be up to %s characters.', NC_VALIDATOR_VARCHAR_LEN)
				),
			),
			'author_url' => array(
				'url' => array(
					'rule' => array('url'),
					'message' => __('The input must be a %s.', __('URL')),
					'allowEmpty' => true,
				),
				'maxlength'  => array(
					'rule' => array('maxLength', NC_VALIDATOR_VARCHAR_LEN),
					'message' => __('The input must be up to %s characters.', NC_VALIDATOR_VARCHAR_LEN)
				),
			),
			'author_ip' => array(
				'ip' => array(
					'rule' => array('ip'),
					'message' => __('The input must be a number.')
				),
				'maxlength'  => array(
					'rule' => array('maxLength', 100),
					'message' => __('The input must be up to %s characters.', 100)
				),
			),
			'is_approved' => array(
				'boolean'  => array(
					'rule' => array('boolean'),
					'last' => true,
					'required' => true,
					'message' => __('The input must be a boolean.')
				),
			),
			// TODO:2系のカラムをそのまま移動したが、使用するかどうかカラム名から考慮する必要あり。
			//'blog_name',
			//'direction_flag',
			//'tb_url',
			//'link',
		);
	}

/**
 * beforeDelete
 * @param   void
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function beforeDelete() {
		App::uses('Archive', 'Model');
		$Archive = new Archive();
		// アーカイブ削除
		if(!$Archive->deleteUnique($this->alias, $this->id)) {
			return false;
		}
		return true;
	}
/**
 * 最近のコメント取得
 *
 * @param   integer $content_id
 * @param   integer $visible_item
 * @param   array   $conditions
 * @return  Model BlogComments
 * @since   v 3.0.0.0
 */
	public function recentComments($content_id, $visible_item, $conditions = array()) {
		$params = array(
			'fields' => array('BlogComment.*', 'BlogPost.post_date', 'BlogPost.title', 'BlogPost.permalink'),
			'conditions' => $conditions,
			'limit' => intval($visible_item),
			'page' => 1,
			'joins' => array(
				array(
					"type" => "INNER",
					"table" => "blog_posts",
					"alias" => "BlogPost",
					"conditions" => "`BlogComment`.`blog_post_id`=`BlogPost`.`id`"
				),
				array(
					"type" => "INNER",
					"table" => "contents",
					"alias" => "Content",
					"conditions" => "`BlogComment`.`content_id`=`Content`.`id`"
				),
				array(
					"type" => "LEFT",
					"table" => "page_user_links",
					"alias" => "PageUserLink",
					"conditions" => "`BlogPost`.`created_user_id`=`PageUserLink`.`user_id`".
						" AND `Content`.`room_id`=`PageUserLink`.`room_id`"
				),
				array(
					"type" => "LEFT",
					"table" => "authorities",
					"alias" => "Authority",
					"conditions" => "`Authority`.`id`=`PageUserLink`.`authority_id`"
				)
			),
			'order' => array('BlogComment.created' => 'DESC')
		);
		return $this->find('all', $params);
	}

/**
 * コメント投稿時初期値
 *
 * @param   integer $contentId
 * @param   integer $blogPostId
 * @return  Model BlogComment
 * @since   v 3.0.0.0
 */
	public function findDefault($contentId, $blogPostId) {
		$ret = array(
			'BlogComment' => array(
				'id' => 0,
				'content_id' => $contentId,
				'blog_post_id' => $blogPostId,
				'parent_id' => null,
				'title' => '',
				'comment' => '',
				'author' => '',
				'author_email' => '',
				'author_url' => '',
				'author_ip' => '',
				'is_approved' => NC_APPROVED_FLAG_ON,
				// TODO:バリデーション同様必要に応じて見直す
// 				'blog_name' => '',
// 				'direction_flag' => _OFF,
// 				'tb_url' => null,
// 				'link' => null,
			),
		);
		return $ret;
	}

/**
 * バリデート前処理
 *  ブログの設定に応じてauthorとauthor_emailを必須に変更
 *
 * @param   array $options
 * @return  boolean
 * @since   v 3.0.0.0
 */
	public function beforeValidate($options = array()) {
		if(isset($this->data['BlogComment']['content_id']) && !Configure::read(NC_SYSTEM_KEY.'.isLogin')){
			App::uses('Blog', 'Blog.Model');
			$this->Blog = new Blog();
			$blog = $this->Blog->findByContentId($this->data['BlogComment']['content_id']);

			// 投稿者名とEmailアドレスを必須に変更
			if($blog['Blog']['comment_required_name']){
				$this->validate['author']['notEmpty'] = array(
					'rule' => array('notEmpty'),
					'message' => __('Please input %s.', __('Name')),
					'required' => true,
				);
				$this->validate['author_email']['notEmpty'] = array(
					'rule' => array('notEmpty'),
					'message' => __('Please input %s.', __('E-mail')),
					'required' => true,
				);
				$this->validate['author_email']['email'] = array(
					'rule' => array('email'),
					'message' => __('The input must be a %s.', __('E-mail')),
					'required' => true,
				);
			}
		}
		return parent::beforeValidate($options);
	}

/**
 * コメント一覧表示のparams取得
 *
 * @param   integer $blogPostId
 * @param   integer $userId
 * @param   integer $hierarchy
 * @param   array   $savedComment 個別に取得する必要があるコメントIDのArray
 * @return  array
 * @since   v 3.0.0.0
 */
	public function getConditions($blogPostId, $userId, $hierarchy, $savedComment) {
		if($hierarchy >= NC_AUTH_MIN_CHIEF) {
			return array(
				'BlogComment.blog_post_id' => $blogPostId
			);
		}

		return array(
			'BlogComment.blog_post_id' => $blogPostId,
			'OR' => array(
				'BlogComment.is_approved' => NC_APPROVED_FLAG_ON,
				'BlogComment.created_user_id' => $userId,
				'BlogComment.id' => $savedComment,
			)
		);
	}

/**
 * ブログへのコメントをTree構造のArrayで取得
 *
 * @param   array $rootComments BlogCommentモデルのArray
 * @param   integer $userId
 * @param   integer $hierarchy
 * @param   array   $savedComment 個別に取得する必要があるコメントのID
 * @return  array $commentTree 引数のコメントをrootにもったTree構造のArray
 * @since   v 3.0.0.0
 */
	public function findCommentTree($rootComments, $userId, $hierarchy, $savedComment) {
		$commentTree = array();

		// ルートに紐づくコメントの取得
		if(!empty($rootComments)) {
			$conditions = $this->getConditions($rootComments[0]['BlogComment']['blog_post_id'], $userId, $hierarchy, $savedComment);

			$treeConditons = array(
				'BlogComment.lft >=' => $rootComments[0]['BlogComment']['lft'] < $rootComments[count($rootComments) - 1]['BlogComment']['lft'] ? $rootComments[0]['BlogComment']['lft'] : $rootComments[count($rootComments) - 1]['BlogComment']['lft'],
				'BlogComment.rght <=' => $rootComments[0]['BlogComment']['rght'] > $rootComments[count($rootComments) - 1]['BlogComment']['rght'] ? $rootComments[0]['BlogComment']['rght'] : $rootComments[count($rootComments) - 1]['BlogComment']['rght'],
			);
			$param['conditions'] = array_merge($treeConditons, $conditions);
			$commentTree =  $this->find('threaded', $param);
		}
		return $commentTree;
	}

/**
 * コメント一覧のPaginateの追加conditions取得
 *
 * @param   integer $blogPostId
 * @param   integer $userId
 * @param   integer $hierarchy
 * @param   array   $savedComment 個別に取得する必要があるコメントのID
 * @return  array
 * @since   v 3.0.0.0
 */
	public function getPaginateConditions($blogPostId, $userId, $hierarchy, $savedComment) {
		$conditions = $this->getConditions($blogPostId, $userId, $hierarchy, $savedComment);

		$rootConditions = array(
			'BlogComment.parent_id' => null
		);

		return array_merge($rootConditions, $conditions);
	}

/**
 * 条件に該当するコメントの件数を取得する
 *
 * @param   array $conditions
 * @return  integer
 * @since   v 3.0.0.0
 */
	public function recordCount($conditions) {
		return $this->find('count', array('conditions' => $conditions));
	}

/**
 * PaginatorComponentのpaginateより呼び出される
 * paginateの中で発行されるレコード数をカウントするSQLが不要な場合は発行しない
 *
 * @param   array   $conditions
 * @param   integer $recursive
 * @param   array   $extra
 * @return  integer
 * @since   v 3.0.0.0
 */
	public function paginateCount($conditions, $recursive, $extra) {
		if(isset($extra['recordCount'])) {
			return $extra['recordCount'];
		}
		return $this->recordCount($conditions);
	}

}