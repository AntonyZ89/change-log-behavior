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
     * @var \yii\db\Connection|null
     */
    public $db = null;

    /**
     * @var string
     */
    public $type = 'update';

    /**
     * @var string|null|callable
     */
    public $parentId = null;

    /**
     * @var array
     */
    public $customFields = [];

    /**
     * Auto cache custom fields on trigger `ActiveRecord::EVENT_AFTER_FIND`
     *
     * Be careful when using this, it can lead to performance issues
     *
     * @var bool
     */
    public $autoCache = false;

    /**
     * @var bool
     */
    public $dataOnDelete = false;

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
            ActiveRecord::EVENT_AFTER_FIND => function () {
                if ($this->autoCache) {
                    $this->cacheCustomFields();
                }
            },
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

        if (!empty($diff)) {
            $parentId = null;

            if ($this->parentId) {
                if (is_callable($this->parentId)) {
                    $parentId = call_user_func($this->parentId, $owner);
                } else {
                    $parentId = $owner[$this->parentId];
                }
            }

            $diff = $owner->setChangelogLabels($diff);
            $logEvent = new LogItem(['db' => $this->db]);
            $logEvent->relatedObject = $owner;
            $logEvent->parentId = $parentId;
            $logEvent->data = $diff;
            $logEvent->type = $this->type;
            $logEvent->save();
        }

        // reset cache
        if (isset($diff['custom_fields'])) {
            $this->_cached_custom_fields = array_map(function ($val) {
                return $val[1];
            }, $diff['custom_fields']);
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

        $logEvent = new LogItem(['db' => $this->db]);
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
        /**
         * @var ActiveRecord $owner
         */
        $owner = $this->owner;
        $generateDiff = function () use ($owner) {
            $diff = [];

            foreach ($owner->attributes as $attrName => $attrVal) {
                //avoid float compare
                $attrVal = is_float($attrVal) ? StringHelper::floatToString($attrVal) : $attrVal;

                $diff[$attrName] = [$attrVal, null];
            }

            $diff = $this->applyExclude($diff);
            $diff = $this->applyCustomFields($diff, true);

            $diff = $this->owner->setChangelogLabels($diff);

            return $diff ?? '';
        };

        $logEvent = new LogItem(['db' => $this->db]);
        $logEvent->relatedObject = $owner;
        $logEvent->data = $this->dataOnDelete ? $generateDiff() : '';
        $logEvent->type = self::DELETED;
        $logEvent->save();
    }

    public function applyCustomFields(array $diff, bool $force = false)
    {
        if (empty($this->customFields)) {
            return $diff;
        }

        $result = [];

        $customFields = $this->computeCustomFields();

        foreach ($customFields as $key => $new) {
            $force_attr = substr($key, -1) === '!';
            $key = $force_attr ? substr($key, 0, -1) : $key;

            $old = $this->_cached_custom_fields[$key] ?? null;

            if ($force || $force_attr || $old != $new) {
                $result[$key] = [$old, $new];
            }
        }

        if (!empty($result)) {
            $diff['custom_fields'] = $result;
        }

        return $diff;
    }

    public function computeCustomFields()
    {
        return array_map(function ($field) {
            if (is_string($field)) {
                $object = $this->owner;

                foreach (explode('.', $field) as $segment) {
                    if (!is_object($object) || !isset($object->{$segment})) {
                        return null;
                    }
                    $object = $object->{$segment};
                }

                return is_callable($object) ? $object() : $object;
            }

            return call_user_func($field, $this->owner);
        }, $this->customFields);
    }

    public function cacheCustomFields()
    {
        if (empty($this->customFields)) {
            return;
        }

        $this->_cached_custom_fields = $this->computeCustomFields();
    }
}

