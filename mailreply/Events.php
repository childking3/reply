<?php

namespace humhub\modules\mailreply;

use humhub\helpers\Html;
use humhub\modules\mail\models\MessageEntry;
use humhub\modules\mail\widgets\ConversationEntry;
use humhub\modules\mailreply\assets\MailReplyAsset;
use humhub\modules\mailreply\services\ReplyLinkService;
use humhub\modules\mailreply\widgets\ConversationEntryWithReply;
use humhub\modules\mailreply\widgets\ReplyButton;
use Yii;

/**
 * All handlers registered in config.php. Kept thin on purpose - the decisions live in
 * {@see ReplyLinkService} and in the widgets.
 */
class Events
{
    /**
     * Swaps mail's entry widget for our subclass. The subclass only adds the quoted block, so
     * if anything here fails the conversation still renders as plain Messenger.
     */
    public static function onConversationEntryCreate($event): void
    {
        if (($event->config['class'] ?? null) === ConversationEntry::class) {
            $event->config['class'] = ConversationEntryWithReply::class;
        }
    }

    /**
     * Appends the Reply action to the per-entry menu.
     *
     * mail renders no menu at all for an entry the user cannot edit (ConversationEntryMenu::run()
     * returns '' when its private $menus is empty), so for other people's messages this handler
     * has to emit the .conversation-menu wrapper itself.
     */
    public static function onConversationEntryMenuRun($event): void
    {
        $entry = $event->sender->entry ?? null;
        if (!$entry instanceof MessageEntry || $entry->type !== MessageEntry::type()) {
            return;
        }

        $button = (string)ReplyButton::widget(['entry' => $entry]);
        if ($button === '') {
            return;
        }

        $item = Html::tag('div', $button, ['class' => 'conversation-menu-item']);
        $result = (string)$event->result;

        if (trim($result) === '') {
            $event->result = Html::tag('div', $item, ['class' => 'conversation-menu']);
            return;
        }

        // Add one more item inside mail's existing wrapper.
        $pos = strrpos($result, '</div>');
        $event->result = $pos === false
            ? $result . $item
            : substr($result, 0, $pos) . $item . substr($result, $pos);
    }

    /**
     * One pair of queries per conversation instead of one per entry.
     */
    public static function onMessagesBeforeRun($event): void
    {
        $widget = $event->sender;
        $messageId = $widget->message->id ?? null;

        if ($messageId === null && !empty($widget->entries)) {
            $first = reset($widget->entries);
            $messageId = $first->message_id ?? null;
        }

        // mail's own cursor: the page being rendered holds entries older than this.
        $cursor = is_numeric($widget->from ?? null) ? (int)$widget->from : null;

        ReplyLinkService::preloadMessage($messageId === null ? null : (int)$messageId, $cursor);
    }

    /**
     * Registers this module's JS/CSS. Bound to the conversation root widget, so nothing is
     * loaded on pages that have no conversation.
     */
    public static function onConversationViewBeforeRun($event): void
    {
        MailReplyAsset::register(Yii::$app->view);
    }

    /**
     * Stores the reply link for the entry mail has just saved.
     */
    public static function onMessageEntryAfterInsert($event): void
    {
        $entry = $event->sender;
        if (!$entry instanceof MessageEntry || $entry->type !== MessageEntry::type()) {
            return;
        }

        try {
            ReplyLinkService::saveFromRequest($entry);
        } catch (\Throwable $e) {
            // Sending a message must never fail because of this module.
            Yii::error($e, 'mailreply');
        }
    }
}
