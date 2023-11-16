<?php

namespace antonyz89\changeLogBehavior;

use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\helpers\StringHelper;

/**
 * Class ChangeLogBehavior
 * @package common\modules\eventLogger\behaviors
 *
 * @property array $labels
 */
class ChangeLogBehavior extends Behavior
{
    /**
     * @var array
     */
    public $excludedAttributes = [];

    /**
     * @var string
     */
    public $type = 'update';

    /**
     * @var array
     */
    public $customFields = [];

    /**
     * @return array
     */
    const DELETED = 'deleted';

    /**
     * @var array
     */
    protected $_cached_custom_fields = [];

    /**
     * @return array
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'cacheCustomFields',
            ActiveRecord::EVENT_AFTER_UPDATE => 'addLog',
            ActiveRecord::EVENT_AFTER_INSERT => 'addLog',
            ActiveRecord::EVENT_BEFORE_DELETE => 'addDeleteLog',
        ];
    }

    /**
     * @param \yii\base\Event $event
     */
    public function addLog(Event $event)
    {
        /**
         * @var ActiveRecord $owner
         */
        $owner = $this->owner;
        $changedAttributes = $event->changedAttributes;

        $diff = [];

        foreach ($changedAttributes as $attrName => $attrVal) {
            $newAttrVal = $owner->getAttribute($attrName);

            //avoid float compare
            $newAttrVal = is_float($newAttrVal) ? StringHelper::floatToString($newAttrVal) : $newAttrVal;
            $attrVal = is_float($attrVal) ? StringHelper::floatToString($attrVal) : $attrVal;

            if ($newAttrVal != $attrVal) {
                $diff[$attrName] = [$attrVal, $newAttrVal];
            }
        }

        $diff = $this->applyExclude($diff);
        $diff = $this->applyCustomFields($diff);

        if ($diff) {
            $diff = $this->owner->setChangelogLabels($diff);
            $logEvent = new LogItem();
            $logEvent->relatedObject = $owner;
            $logEvent->data = $diff;
            $logEvent->type = $this->type;
            $logEvent->save();
        }
    }

    /**
     * @param $data
     * @param $type
     */
    public function addCustomLog($data, $type = null)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        if ($type) {
            $this->setType($type);
        }

        $logEvent = new LogItem();
        $logEvent->relatedObject = $this->owner;
        $logEvent->data = $data;
        $logEvent->type = $this->type;
        $logEvent->save();
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @param array $diff
     *
     * @return array
     */
    private function applyExclude(array $diff)
    {
        foreach ($this->excludedAttributes as $attr) {
            unset($diff[$attr]);
        }

        return $diff;
    }

    /**
     * @param array $diff
     *
     * @return array
     */
    public function setChangelogLabels(array $diff)
    {
        return $diff;
    }

    public function addDeleteLog()
    {
        $logEvent = new LogItem();
        $logEvent->relatedObject = $this->owner;
        $logEvent->data = '';
        $logEvent->type = self::DELETED;
        $logEvent->save();
    }

    public function applyCustomFields(array $diff)
    {
        if (empty($this->customFields)) {
            return $diff;
        }

        $result = [];

        $customFields = $this->computeCustomFields();;

        foreach ($customFields as $key => $new) {
            $old = $this->_cached_custom_fields[$key];

            $result[$key] = [$old, $new];
        }

        $diff['custom_fields'] = $result;

        return $diff;
    }

    public function computeCustomFields()
    {
        return array_map(function (callable $field) {
            return call_user_func($field, $this->owner);
        }, $this->customFields);
    }

    public function cacheCustomFields()
    {
        if ($this->customFields === null) {
            return;
        }

        $this->_cached_custom_fields = $this->computeCustomFields();
    }
}

