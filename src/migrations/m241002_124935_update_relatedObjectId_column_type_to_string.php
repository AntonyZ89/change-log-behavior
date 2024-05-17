<?php

use yii\db\Migration;

class m241002_124935_update_relatedObjectId_column_type_to_string extends Migration
{

    // Use safeUp/safeDown to run migration code within a transaction
    private $table = '{{%changelogs}}';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn($this->table, 'relatedObjectId', $this->string()->notNull());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->alterColumn($this->table, 'relatedObjectId', $this->integer()->notNull());
    }
}
