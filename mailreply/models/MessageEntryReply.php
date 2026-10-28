<?php

namespace humhub\modules\mailreply\models;

use humhub\components\ActiveRecord;
use Yii;
use humhub\modules\mail\models\MessageEntry;
use yii\db\ActiveQuery;

/**
 * Links a message entry to the entry it replies to.
 *
 * This table belongs to THIS module: mail's own `message_entry` table is never altered, so the
 * module installs and uninstalls without touching the Messenger schema.
 *
 * @property int $id
 * @property int $entry_id        the reply
 * @property int $parent_entry_id the quoted entry
 * @property int $message_id      conversation both entries belong to (denormalised for preloading)
 * @property string $created_at
 * @property int $created_by
 */
class MessageEntryReply extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'mailreply_link';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['entry_id', 'parent_entry_id', 'message_id'], 'required'],
            [['entry_id', 'parent_entry_id', 'message_id'], 'integer'],
            [['entry_id'], 'unique'],
            [['parent_entry_id'], 'compare', 'compareAttribute' => 'entry_id', 'operator' => '!=',
                'message' => Yii::t('MailreplyModule.base', 'A message cannot quote itself.')],
        ];
    }

    public function getEntry(): ActiveQuery
    {
        return $this->hasOne(MessageEntry::class, ['id' => 'entry_id']);
    }

    public function getParentEntry(): ActiveQuery
    {
        return $this->hasOne(MessageEntry::class, ['id' => 'parent_entry_id']);
    }
}
