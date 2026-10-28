<?php

namespace humhub\modules\mailreply\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\mail\models\MessageEntry;
use Yii;

/**
 * The quoted message shown inside a reply's bubble.
 *
 * The quoted text is always re-rendered from the stored entry, never from anything the client
 * sent, and goes through the same RichText::preview() call mail uses for its inbox previews.
 */
class QuotedEntry extends Widget
{
    public MessageEntry $parentEntry;

    /**
     * @var int max characters of the quoted message
     */
    public int $previewLength = 120;

    /**
     * @inheritdoc
     */
    public function run()
    {
        $author = $this->parentEntry->user;

        return $this->render('quotedEntry', [
            'parentEntry' => $this->parentEntry,
            'authorName' => $author ? $author->displayName : Yii::t('MailreplyModule.base', 'Deleted user'),
            'isOwnQuote' => $author && $author->is(Yii::$app->user->getIdentity()),
            'preview' => $this->renderPreview(),
        ]);
    }

    private function renderPreview(): string
    {
        $content = (string)$this->parentEntry->content;

        if (trim($content) === '') {
            // Attachment-only message: there is no text to quote.
            // Html::tag escapes the label: a translation is not user input, but it is still
            // text from a file, and text from a file does not belong in raw markup.
            return Html::tag('em', Yii::t('MailreplyModule.base', 'Attachment'));
        }

        return RichText::preview($content, $this->previewLength);
    }
}
