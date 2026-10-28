<?php

namespace humhub\modules\mailreply\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\mail\models\MessageEntry;
use humhub\modules\mailreply\services\ReplyLinkService;
use humhub\modules\ui\icon\widgets\Icon;
use Yii;

/**
 * The per-entry "Reply" action, appended to mail's entry menu.
 *
 * Renders nothing unless the viewer is a participant of the conversation - the same check
 * mail's own controller makes - following the widget authorisation idiom used by the
 * reportcontent module (permission test in beforeRun()).
 */
class ReplyButton extends Widget
{
    public MessageEntry $entry;

    /**
     * @inheritdoc
     */
    public function beforeRun()
    {
        if (!parent::beforeRun()) {
            return false;
        }

        // Asks the service, which answers once per conversation. Going through
        // $this->entry->message here instead cost one message + one participation query per
        // rendered entry (~200 extra selects on a 50-entry conversation).
        return ReplyLinkService::canReplyInMessage((int)$this->entry->message_id);
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $label = Yii::t('MailreplyModule.base', 'Reply to this message');

        return Html::tag('a', (string)Icon::get('reply'), [
            // href keeps the link keyboard-focusable, exactly as mail's edit button does.
            'href' => '#',
            'class' => 'mailreply-reply-button time badge',
            'data-action-click' => 'mailreply.startReply',
            'data-mailreply-entry' => (int)$this->entry->id,
            'title' => $label,
            'aria-label' => $label,
        ]);
    }
}
