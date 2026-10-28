<?php

use humhub\helpers\Html;
use humhub\modules\mail\models\MessageEntry;

/* @var $parentEntry MessageEntry */
/* @var $authorName string */
/* @var $isOwnQuote bool */
/* @var $preview string already rendered by RichText::preview() */
?>
<div class="mailreply-quote<?= $isOwnQuote ? ' own' : '' ?>"
     data-action-click="mailreply.jumpToEntry"
     data-mailreply-parent="<?= (int)$parentEntry->id ?>"
     role="button"
     tabindex="0">
    <span class="mailreply-quote-author"><?= Html::encode($authorName) ?></span>
    <span class="mailreply-quote-text"><?= $preview ?></span>
</div>
