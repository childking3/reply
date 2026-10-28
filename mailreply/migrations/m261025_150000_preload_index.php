<?php

use humhub\components\Migration;

/**
 * Index the preload query properly.
 *
 * The hot query is the page preload:
 *   SELECT entry_id, parent_entry_id FROM mailreply_link
 *   WHERE message_id = ? ORDER BY entry_id DESC LIMIT <page size>
 *
 * With only `ix_mailreply_message` (message_id) the plan depended on the data: for an ordinary
 * conversation MariaDB read every link row of that conversation and ran a filesort to pick the
 * newest 50; for a very long conversation it flipped to scanning the entry_id unique index
 * backwards, which walks the index until it happens to find 50 matching rows. Measured on a
 * 1,050,000-row table: the first plan sorts as many rows as the conversation has links, the
 * second gave a 515-row estimate and is unbounded for a conversation whose links sit low in the
 * index.
 *
 * (message_id, entry_id, parent_entry_id) makes the filter, the ordering AND the selected
 * columns all satisfiable from one index. Re-measured on the same 1,050,000-row table: exactly
 * 49 index entries walked, "Using index" (no table lookups), zero rows sorted - identical for a
 * 50-link and a 50,000-link conversation.
 *
 * `ix_mailreply_message` then becomes a redundant left-prefix of the new index, and the foreign
 * key on message_id is satisfied by the composite, so it is dropped.
 */
class m261025_150000_preload_index extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'ix_mailreply_message_entry',
            'mailreply_link',
            ['message_id', 'entry_id', 'parent_entry_id'],
        );

        // Redundant once the composite exists (same leftmost column, and it still backs the FK).
        $this->dropIndex('ix_mailreply_message', 'mailreply_link');
    }

    public function safeDown()
    {
        $this->createIndex('ix_mailreply_message', 'mailreply_link', 'message_id');
        $this->dropIndex('ix_mailreply_message_entry', 'mailreply_link');
    }
}
