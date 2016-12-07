<?php
    /**
     * Created by PhpStorm.
     * User: Cranky4
     * Date: 28.03.2016
     * Time: 12:57
     */

    namespace cranky4\ChangeLogBehavior;

    use yii\base\Exception;
    use yii\base\Model;
    use yii\base\Widget;
    use yii\grid\GridView;
    use yii\helpers\Inflector;

    /**
     * Class ChangesList
     * @package app\widgets
     */
    class ChangeLogList extends Widget
    {
        /**
         * @var Model
         */
        public $model;
        /**
         * @var string
         */
        public $header;

        /**
         * @inheritdoc
         */
        public function init()
        {
            parent::init();
            if (!$this->header) {
                $this->header = "<h2>".Inflector::camel2words($this->model->formName())." change log:</h2>";
            }
        }

        /**
         * @inheritdoc
         * @return bool|string
         * @throws Exception
         */
        public function run()
        {
            $model = $this->model;
            if (!$model) {
                return false;
            }
            if (!$model->hasMethod('getLog')) {
                throw new Exception("Attach ".ChangeLogBehavior::className()." behavior to ".$model::className());
            }

            /**
             * @var ChangeLogBehavior $model
             */
            $logProvider = $model->getLog();
            /**
             * @var ChangeLog $component
             */
            $component = \Yii::$app->c4ChangeLog;

            $view = $this->header;
            $view .= GridView::widget([
                'dataProvider' => $logProvider,
                'columns'      => [
                    'log_time:datetime',
                    'prefix',
                    [
                        'attribute' => 'message',
                        'content'   => function ($item) use ($component) {
                            $messages = $component->unserialize($item['message']);
                            if (is_array($messages)) {
                                $message = "";
                                foreach ($messages as $attr => $changes) {
                                    if(is_array($changes) && count($changes) == 2) {
                                        $changes = $changes[0]. " &raquo; " . $changes[1];
                                    }
                                    $message .= $attr.": ".$changes."<br>";
                                }

                                return $message;
                            }

                            return $messages;
                        },
                    ],
                ],
            ]);

            return $view;
        }

    }
