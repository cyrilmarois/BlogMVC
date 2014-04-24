<?php

/**
 * This model represents single user.
 *
 * @method static User model() Gets User model.
 * @property int $postCount Amount of user's posts (has to be called with stat
 * relation).
 * @property Post[] $posts User posts (find* method has to be called with
 * 'posts' relation),
 *
 * @version    Release: 0.1.0
 * @since      0.1.0
 * @package    BlogMVC
 * @subpackage Yii
 * @author     Fike Etki <etki@etki.name>
 */
class User extends ActiveRecordLayer
{
    /**
     * User's login and display name.
     * 
     * @var string
     * @since 0.1.0
     */
    public $username;
    /**
     * User's password hash.
     * 
     * @var string
     * @since 0.1.0
     */
    public $password;
    /**
     * New password which should be set instead of current one. Virtual
     * property, isn't stored in database - it's value is hashed and put into
     * {@link $password} property.
     * 
     * @var string
     * @since 0.1.0
     */
    public $newPassword;
    /**
     * 
     * 
     * @var string
     * @since 0.1.0
     */
    public $newPasswordRepeat;
    
    /**
     * Returns model table name.
     * 
     * @return string Related table name.
     * @since 0.1.0
     */
    public function tableName()
    {
        return 'users';
    }
    /**
     * Scope method which allows paged access to records.
     * 
     * @param int $page    Page number. Note that it isn't validated inside the
     * method.
     * @param int $perPage Number of records per page.
     *
     * @return \User Current instance.
     * @since 0.1.0
     */
    public function paged($page, $perPage=25)
    {
        $this->getDbCriteria()->mergeWith(
            array(
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'order' => 'id ASC',
            )
        );
        return $this;
    }
    /**
     * Scope method which allows to fetch most active users.
     *
     * @param int $limit Maximum number of records to return.
     *
     * @throws \BadMethodCallException Thrown if invalid limit argument is
     * provided.
     *
     * @return \User current instance
     * @since 0.1.0
     */
    public function mostActive($limit=5)
    {
        if (($limit = (int)$limit) < 1) {
            $message = 'Limit argument has to be an integer not less than 1';
            throw new \BadMethodCallException($message);
        }
        $this->getDbCriteria()->mergeWith(
            array(
                'alias' => 'users',
                'join' => 'INNER JOIN posts ON posts.user_id = users.id',
                'group' => 'users.id',
                'order' => 'COUNT(posts.id)',
                'limit' => $limit,
            )
        );
        return $this;
    }
    /**
     * Before-save callback.
     * 
     * @return boolean Returns false if parent beforeSave() returns false,
     * otherwise returns true.
     * @since 0.1.0
     */
    public function beforeSave()
    {
        if (!parent::beforeSave()) {
            return false;
        }
        if (!empty($this->newPassword)) {
            $this->password = sha1($this->newPassword);
        }
        return true;
    }
    /**
     * Validator method for entered password. Note that validation comes with
     * internal hashing, so this is different from native CCompareValidator.
     *
     * @param string $attribute Attribute name.
     * @param array  $params    Additional validation params.
     *
     * @throws \CException Thrown if additional params don't contain required
     * `compareAttribute` entry.
     *
     * @return void
     * @since 0.1.0
     */
    public function validateNewPassword($attribute, array $params)
    {
        if (!isset($params['compareAttribute'])) {
            $message = 'Password verification requires `compareAttribute` parameter';
            throw new \CException($message);
        }
        if ($this->$attribute !== $this->$params['compareAttribute']) {
            $error = \Yii::t('validation-errors', 'user.passwordMismatch');
            $this->addError($attribute, $error);
        }
    }
    /**
     * Validator method for validating current password.
     * 
     * @param string $attribute Attribute name.
     *
     * @return void
     * @since 0.1.0
     */
    public function validatePassword($attribute/*, array $params*/)
    {
        \Yii::beginProfile('user.validatePassword');
        // Non-AR approach to avoid unnecessary overhead.
        $currentPassword = \Yii::app()->db->createCommand()
            ->select('password')
            ->from($this->tableName())
            ->where('id = :id', array(':id' => $this->getPrimaryKey()))
            ->queryScalar();
        if ($currentPassword !== sha1($this->$attribute)) {
            $error = \Yii::t('validation-errors', 'user.incorrectPassword');
            $this->addError($attribute, $error);
        }
        \Yii::endProfile('user.validatePassword');
    }

    /**
     * Validates inexistence of user with the same username.
     *
     * @param string $attribute Username attribute name.
     *
     * @return void
     * @since 0.1.0
     */
    public function validateUsernameUniqueness($attribute)
    {
        \Yii::beginProfile('user.validateUsernameUniqueness');
        $username = $this->$attribute;
        $exists = (bool) \Yii::app()->db->createCommand()
            ->select('username')
            ->from($this->tableName())
            ->where(
                'username = :username',
                array(':username' => $username)
            )->queryScalar();
        if ($exists) {
            $e = \Yii::t(
                'validation-errors',
                'user.usernameExists',
                array('{username}' => $username,)
            );
            $this->addError($attribute, $e);
        }
        \Yii::endProfile('user.validateUsernameUniqueness');
    }
    /**
     * Return set of localized attribute labels.
     * 
     * @return string[] Attribute labels.
     * @since 0.1.0
     */
    public function getAttributeLabels()
    {
        return array(
            'id' => 'ID',
            'username' => 'user.username',
            'password' => 'user.password',
            'newPassword' => 'user.newPassword',
            'newPasswordRepeat' => 'user.newPasswordRepeat',
            'postCount' => 'user.postCount',
        );
    }

    /**
     * Defines model relations.
     *
     * @return array Set of relation definitions.
     * @since 0.1.0
     */
    public function relations()
    {
        $commentTable = Comment::model()->tableName();
        return array(
            'posts' => array(self::HAS_MANY, 'Post', 'user_id'),
            'postCount' => array(self::STAT, 'Post', 'user_id'),
            'commentCount' => array(
                self::STAT,
                'Post',
                'user_id',
                'select' => 'COUNT(c.id)',
                'join' => 'INNER JOIN '.$commentTable.' c ON t.id = c.post_id',
            ),
        );
    }
    /**
     * Standard Yii method for defining validation rules.
     * 
     * @return array Set of validation rules.
     * @since 0.1.0
     */
    public function rules()
    {
        return array(
            array(
                array('username',),
                'length',
                'allowEmpty' => false,
                'min' => 3,
                'max' => 255,
                'on' => array('insert', 'usernameUpdate',),
            ),
            array(
                array('username',),
                'validateUsernameUniqueness',
                'on' => array('insert', 'usernameUpdate',),
            ),
            array(
                array('newPassword', 'newPasswordRepeat',),
                'length',
                'allowEmpty' => false,
                'min' => 6,
                'max' => 255,
                'on' => array('insert', 'passwordUpdate',),
            ),
            array(
                array('newPasswordRepeat',),
                'compare',
                'compareAttribute' => 'newPassword',
                'on' => array('insert', 'passwordUpdate',),
            ),
            array(
                array('password',),
                'validatePassword',
                'on' => array('passwordUpdate',),
            ),
        );
    }
}
