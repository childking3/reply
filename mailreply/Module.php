<?php

namespace humhub\modules\mailreply;

use humhub\components\Module as BaseModule;
use Yii;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Yii::t('MailreplyModule.base', 'Message Replies');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Yii::t('MailreplyModule.base', 'Reply to a single message inside a Messenger conversation.');
    }
}
