<?php

namespace humhub\modules\mailreply\widgets;

use humhub\modules\mail\widgets\ConversationEntry;
use humhub\modules\mailreply\services\ReplyLinkService;
use Yii;

/**
 * mail's own entry widget plus the quoted message.
 *
 * Installed through Widget::EVENT_CREATE (see config.php). Subclassing is what keeps the
 * "replacement must accept the same public properties" rule from
 * docs/develop/module-change-behavior.md trivially satisfied, and means every future change
 * mail makes to an entry is inherited instead of copied.
 */
class ConversationEntryWithReply extends ConversationEntry
{
    /**
     * Anchor inside mail 3.4.6's widgets/views/conversationEntry.php: the bubble's inner
     * container. Verified against mail 3.4.6; if a later mail version renames it the quote is
     * rendered above the bubble instead (visibly different, never broken).
     */
    private const BUBBLE_ANCHOR = '<div class="markdown-render">';

    /**
     * Optional second anchor: when the sender's name is shown it is the first element inside
     * the bubble, and the quote belongs below it.
     */
    private const AUTHOR_LABEL_PATTERN = '#^\s*<div class="author-label".*?</div>#s';

    /**
     * Yii resolves a widget's views from the directory of the widget's own class, so without
     * this every render() inherited from mail would look inside THIS module and fail with
     * ViewNotFoundException (it did, first run). Inherited renders stay with mail's views;
     * this module's own markup lives in its own widgets (QuotedEntry).
     *
     * @inheritdoc
     */
    public function getViewPath()
    {
        return Yii::getAlias('@mail/widgets/views');
    }

    /**
     * @inheritdoc
     */
    public function runMessage(): string
    {
        $html = parent::runMessage();

        $parentEntry = ReplyLinkService::getParentEntry($this->entry);
        if ($parentEntry === null) {
            return $html;
        }

        $quote = (string)QuotedEntry::widget(['parentEntry' => $parentEntry]);

        if ($quote === '') {
            return $html;
        }

        return $this->insertIntoBubble($html, $quote);
    }

    private function insertIntoBubble(string $html, string $quote): string
    {
        $pos = strpos($html, self::BUBBLE_ANCHOR);
        if ($pos === false) {
            return $quote . $html;
        }

        $at = $pos + strlen(self::BUBBLE_ANCHOR);
        $rest = substr($html, $at);

        // Keep the quote under the sender's name when mail renders one.
        if (preg_match(self::AUTHOR_LABEL_PATTERN, $rest, $m)) {
            $at += strlen($m[0]);
        }

        return substr($html, 0, $at) . $quote . substr($html, $at);
    }
}
