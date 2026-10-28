<?php

use humhub\components\Migration;

/**
 * Creates this module's own link table. Both foreign keys cascade on delete, so deleting a
 * message entry or a whole conversation removes the reply links with it - no event handlers
 * and no orphan rows.
 */
class m261025_130000_init extends Migration
{
    public function safeUp()
    {
        $this->createTable('mailreply_link', [
            'id' => $this->primaryKey(),
            'entry_id' => $this->integer()->notNull(),
            'parent_entry_id' => $this->integer()->notNull(),
            'message_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
        ]);

        // One parent per reply; also makes a duplicate insert impossible under concurrency.
        $this->createIndex('uq_mailreply_entry', 'mailreply_link', 'entry_id', true);
        // Preload path: all links of one conversation.
        $this->createIndex('ix_mailreply_message', 'mailreply_link', 'message_id');

        $this->addForeignKey('fk_mailreply_entry', 'mailreply_link', 'entry_id', 'message_entry', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_mailreply_parent', 'mailreply_link', 'parent_entry_id', 'message_entry', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_mailreply_message', 'mailreply_link', 'message_id', 'message', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('mailreply_link');
    }
}
