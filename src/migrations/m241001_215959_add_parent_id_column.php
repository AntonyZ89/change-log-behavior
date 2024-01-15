<?php

use yii\db\Migration;

class m241001_215959_add_parent_id_column extends Migration
{
    // Use safeUp/safeDown to run migration code within a transaction
    private $table = '{{%changelogs}}';

    public function safeUp()
    {
        $this->addColumn($this->table, 'parentId', $this->integer()->after('relatedObjectId'));
    }

    public function safeDown()
    {
        $this->dropColumn($this->table, 'parentId');
    }
}

