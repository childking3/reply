<?php

use humhub\components\ActiveRecord;
use humhub\components\Widget;
use humhub\modules\mail\models\MessageEntry;
use humhub\modules\mail\widgets\ConversationEntry;
use humhub\modules\mail\widgets\ConversationEntryMenu;
use humhub\modules\mail\widgets\ConversationView;
use humhub\modules\mail\widgets\Messages;
use humhub\modules\mailreply\Events;
use humhub\modules\mailreply\services\ReplyLinkService;
use yii\base\Application;

/**
 * Every hook here is one of the three extension points blessed by
 * docs/develop/module-change-behavior.md. Not a single file of the mail module (or of the core)
 * is modified by this module.
 *
 * The events use the ASSOCIATIVE form ('class'/'event'/'callback') rather than the shorter
 * positional tuple. Both are valid, but on the production network every module written with the
 * tuple form currently fails to register its handlers ("required property 'event' missing!",
 * logged on every web request for reportcontent, sharebetween, online-users, questions,
 * text-editor, auth-google and the core space module among others), while every module using
 * this form registers cleanly - mail and menu-manager included. Until that is resolved, this
 * form is the one that actually works there, and it costs nothing.
 */
return [
    'id' => 'mailreply',
    'class' => 'humhub\modules\mailreply\Module',
    'namespace' => 'humhub\modules\mailreply',
    'events' => [
        // Replace mail's entry widget with our subclass, so a reply can render the quoted
        // message inside the bubble ("Replace a widget" pattern).
        [
            'class' => ConversationEntry::class,
            'event' => Widget::EVENT_CREATE,
            'callback' => [Events::class, 'onConversationEntryCreate'],
        ],

        // Append the "Reply" action to the per-entry menu ("Append content to a widget's
        // output"). mail renders no menu at all for an entry the user cannot edit, so the
        // handler has to be able to emit the wrapper itself.
        [
            'class' => ConversationEntryMenu::class,
            'event' => Widget::EVENT_AFTER_RUN,
            'callback' => [Events::class, 'onConversationEntryMenuRun'],
        ],

        // One pair of queries per conversation page instead of one per entry.
        [
            'class' => Messages::class,
            'event' => Widget::EVENT_BEFORE_RUN,
            'callback' => [Events::class, 'onMessagesBeforeRun'],
        ],

        // Register this module's JS/CSS on the conversation page only.
        [
            'class' => ConversationView::class,
            'event' => Widget::EVENT_BEFORE_RUN,
            'callback' => [Events::class, 'onConversationViewBeforeRun'],
        ],

        // Persist the reply link after mail has saved the new entry.
        [
            'class' => MessageEntry::class,
            'event' => ActiveRecord::EVENT_AFTER_INSERT,
            'callback' => [Events::class, 'onMessageEntryAfterInsert'],
        ],

        // Start every request with empty caches: statics outlive a request in a queue worker or
        // a worker runtime, and nothing cached for one user may be reused for the next.
        [
            'class' => Application::class,
            'event' => Application::EVENT_BEFORE_REQUEST,
            'callback' => [ReplyLinkService::class, 'resetRequestCache'],
        ],
    ],
];
