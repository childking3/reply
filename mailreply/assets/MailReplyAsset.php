<?php

namespace humhub\modules\mailreply\assets;

use humhub\components\assets\AssetBundle;
use humhub\modules\mail\assets\MailMessengerAsset;
use Yii;
use yii\web\View;

class MailReplyAsset extends AssetBundle
{
    public $sourcePath = '@mailreply/resources';

    public $jsOptions = ['position' => View::POS_END];

    public $js = [
        'js/humhub.mailreply.js',
    ];

    public $css = [
        'css/mailreply.css',
    ];

    /**
     * mail's messenger bundle has to be there before this module extends its behaviour.
     */
    public $depends = [
        MailMessengerAsset::class,
    ];

    /**
     * @inheritdoc
     */
    public static function register($view)
    {
        $view->registerJsConfig('mailreply', [
            'text' => [
                'label.replyingTo' => Yii::t('MailreplyModule.base', 'Replying to {name}'),
                'label.you' => Yii::t('MailreplyModule.base', 'yourself'),
                'label.cancel' => Yii::t('MailreplyModule.base', 'Cancel reply'),
                'info.notLoaded' => Yii::t('MailreplyModule.base', 'The quoted message is not loaded. Scroll up to load older messages.'),
            ],
        ]);

        return parent::register($view);
    }
}
