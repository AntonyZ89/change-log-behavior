<?php

namespace antonyz89\changeLogBehavior;

use antonyz89\changeLogBehavior\helpers\CompositeRelationHelper;
use yii\behaviors\TimestampBehavior;
use yii\console\Application;
use yii\db\ActiveRecord;
use Yii;
use yii\db\Connection;

/**
 * This is the model class for table "log_event".
 *
 * @property integer $id
 * @property string $relatedObjectType
 * @property integer $relatedObjectId
 * @property integer|null $parentId
 * @property string $data
 * @property string $createdAt
 * @property string $type
 * @property integer $userId
 * @property string $module
 * @property yii\db\ActiveQuery $user
 * @property string $hostname
 *
 * example of log event creation:
 *          $model =    $this->findModel($id);
 *          $event = new Event;
 *          $event->type  = 'user_view';
 *          $event->relatedObject = $model;
 *          $event->save(false);
 */
class LogItem extends ActiveRecord
{
    /**
     * @var ActiveRecord
     */
    public $relatedObject;

    private static $_db;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%changelogs}}';
    }

    static public function getDb()
    {
        return self::$_db ?? parent::getDb();
    }

    public function setDb(?Connection $db)
    {
        self::$_db = $db;
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'createdAt',
                'updatedAtAttribute' => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['relatedObjectId', 'userId', 'parentId'], 'integer'],
            [['module'], 'string'],
            [['createdAt', 'relatedObject', 'data'], 'safe'],
            [['relatedObjectType', 'type', 'hostname'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'relatedObjectType' => 'Related Object Type',
            'relatedObjectId' => 'Related Object ID',
            'data' => 'Data',
            'createdAt' => 'Created At',
            'type' => 'Type',
            'module' => 'Module',
            'userId' => 'User ID',
            'parentId' => 'Parent ID',
            'hostname' => 'Hostname',
        ];
    }

    /**
     * @param bool $insert
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function beforeSave($insert)
    {
        if (empty($this->userId) && !(Yii::$app instanceof Application) && !Yii::$app->user->isGuest) {
            $this->userId = Yii::$app->user->id;
        }

        if (empty($this->hostname) && Yii::$app->request->hasMethod('getUserIP')) {
            $this->hostname = Yii::$app->request->getUserIP();
        }

        if (!empty($this->data) && is_array($this->data)) {
            $this->data = json_encode($this->data);
        }

        if ($this->relatedObject) {
            $pk = $this->relatedObject->primaryKey;

            if (is_array($pk)) {
                $pk = json_encode($pk);
            }

            $this->relatedObjectType = CompositeRelationHelper::resolveObjectType($this->relatedObject);
            $this->relatedObjectId = $pk;
        }

        $this->module = Yii::$app->id;

        return parent::beforeSave($insert);
    }
}
