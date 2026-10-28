<?php

namespace humhub\modules\mailreply\services;

use humhub\modules\mail\models\Message;
use humhub\modules\mail\models\MessageEntry;
use humhub\modules\mailreply\models\MessageEntryReply;
use Yii;
use yii\web\Application as WebApplication;

/**
 * Reads, writes and caches the "this entry replies to that entry" links.
 *
 * Everything that decides whether a link may be written or rendered lives here, so there is
 * exactly one place to audit.
 */
class ReplyLinkService
{
    /**
     * Name of the hidden field the reply form carries. Added to mail's existing form by this
     * module's JS, and posted along by mail's own client.submit() call.
     */
    public const POST_PARAM = 'mailreplyTarget';

    /**
     * Request-scoped cache: entry id => parent entry id, or null when the entry is not a reply.
     * @var array<int, int|null>
     */
    private static array $parentIdByEntry = [];

    /**
     * Request-scoped cache of already loaded parent entries.
     * @var array<int, MessageEntry>
     */
    private static array $parentEntries = [];

    /**
     * @var array<int, true> conversations already preloaded
     */
    private static array $preloadedMessages = [];

    /**
     * Lowest entry id the preload window of a conversation covers (0 = everything below the
     * cursor). Entries older than this still need their own lookup.
     * @var array<int, int>
     */
    private static array $preloadFloor = [];

    /**
     * Request-scoped conversation cache. Rendering a conversation asks about the same
     * conversation once per entry; without this, a 50-entry conversation cost ~200 extra
     * queries (measured: 304 selects vs 75 on the control instance).
     * @var array<int, Message|null>
     */
    private static array $messages = [];

    /**
     * Keyed "<user id>:<message id>", never by message alone.
     *
     * These caches are static, and a static outlives a single request in a long-lived process -
     * a queue worker handling job after job, or a worker runtime such as FrankenPHP, which
     * HumHub's own marketplace code already accounts for. A permission answer cached under the
     * message id alone could then be handed to a different user. Keying by identity makes that
     * impossible regardless of process model; resetRequestCache() additionally keeps the caches
     * from growing without bound.
     *
     * @var array<string, bool>
     */
    private static array $canReply = [];

    /**
     * Loads the reply links of the page about to be rendered, plus the quoted entries, in two
     * queries.
     *
     * Bounded on purpose. Loading every link of the conversation looked fine on small data and
     * was badly wrong at scale: a 3000-entry conversation with 1000 quotes fetched 1000 link
     * rows and 1000 full parent entries to render a page that showed 17 quotes, and the cost
     * grew with the conversation rather than with the page. mail renders at most
     * conversationInitPageSize/conversationUpdatePageSize entries below the cursor, and links
     * are a subset of entries, so the newest $limit links below the cursor always cover the
     * whole page.
     *
     * @param int|null $cursor mail's `from` cursor (entries with a smaller id are being shown)
     * @param int|null $limit  page size; defaults to mail's own conversation page size
     */
    public static function preloadMessage(?int $messageId, ?int $cursor = null, ?int $limit = null): void
    {
        if (!$messageId || isset(self::$preloadedMessages[$messageId])) {
            return;
        }
        self::$preloadedMessages[$messageId] = true;

        $limit = $limit ?: self::pageSize();

        $query = MessageEntryReply::find()
            ->select(['entry_id', 'parent_entry_id'])
            ->where(['message_id' => $messageId]);

        if ($cursor !== null && $cursor > 0) {
            $query->andWhere(['<', 'entry_id', $cursor]);
        }

        $rows = $query->orderBy(['entry_id' => SORT_DESC])->limit($limit)->asArray()->all();

        // Entries older than the window are not covered, so they must still be allowed to ask.
        self::$preloadFloor[$messageId] = count($rows) < $limit
            ? 0
            : (int)end($rows)['entry_id'];

        if (empty($rows)) {
            return;
        }

        $parentIds = [];
        foreach ($rows as $row) {
            $entryId = (int)$row['entry_id'];
            $parentId = (int)$row['parent_entry_id'];
            self::$parentIdByEntry[$entryId] = $parentId;
            $parentIds[$parentId] = true;
        }

        self::loadParents(array_keys($parentIds));
    }

    /**
     * mail's own conversation page size, so this module's window can never be smaller than the
     * page it has to cover.
     */
    private static function pageSize(): int
    {
        $module = Yii::$app->getModule('mail');
        $sizes = [50];
        foreach (['conversationInitPageSize', 'conversationUpdatePageSize'] as $prop) {
            if ($module !== null && isset($module->$prop) && (int)$module->$prop > 0) {
                $sizes[] = (int)$module->$prop;
            }
        }

        return max($sizes);
    }

    /**
     * Returns the entry the given entry replies to, or null.
     */
    public static function getParentEntry(MessageEntry $entry): ?MessageEntry
    {
        $entryId = (int)$entry->id;

        if (!array_key_exists($entryId, self::$parentIdByEntry)) {
            $messageId = (int)$entry->message_id;
            $covered = isset(self::$preloadedMessages[$messageId])
                && $entryId >= (self::$preloadFloor[$messageId] ?? 0);

            if ($covered) {
                // This entry is inside the preloaded window, so a missing key means it is
                // simply not a reply. Querying per entry here is what made a 50-entry
                // conversation issue 26 pointless selects.
                self::$parentIdByEntry[$entryId] = null;
            } else {
                // Not preloaded: the single-entry render right after an AJAX reply ends up here.
                $row = MessageEntryReply::find()
                    ->select(['parent_entry_id'])
                    ->where(['entry_id' => $entryId])
                    ->asArray()
                    ->one();
                self::$parentIdByEntry[$entryId] = $row ? (int)$row['parent_entry_id'] : null;
            }
        }

        $parentId = self::$parentIdByEntry[$entryId];
        if (!$parentId) {
            return null;
        }

        if (!isset(self::$parentEntries[$parentId])) {
            self::loadParents([$parentId]);
        }

        $parent = self::$parentEntries[$parentId] ?? null;

        // Defence in depth: never render content from another conversation, whatever is in the
        // table. A link can only be written for the same conversation, so this should be
        // unreachable - it is here so a bad row can never become an information leak.
        if ($parent === null || (int)$parent->message_id !== (int)$entry->message_id) {
            return null;
        }

        return $parent;
    }

    /**
     * The conversation, loaded at most once per request.
     */
    public static function getMessage(?int $messageId): ?Message
    {
        if ($messageId === null || $messageId <= 0) {
            return null;
        }

        if (!array_key_exists($messageId, self::$messages)) {
            self::$messages[$messageId] = Message::findOne(['id' => $messageId]);
        }

        return self::$messages[$messageId];
    }

    /**
     * May the current user reply inside this conversation? Same check mail's own controller
     * makes, answered once per conversation instead of once per rendered entry.
     */
    public static function canReplyInMessage(?int $messageId): bool
    {
        if ($messageId === null || Yii::$app->user->isGuest) {
            return false;
        }

        $identity = Yii::$app->user->getIdentity();
        if ($identity === null) {
            return false;
        }

        $key = $identity->id . ':' . $messageId;

        if (!array_key_exists($key, self::$canReply)) {
            $message = self::getMessage($messageId);
            self::$canReply[$key] = $message !== null
                && $message->isParticipant($identity)
                && !$message->isBlocked();
        }

        return self::$canReply[$key];
    }

    /**
     * Drops every request-scoped cache. Registered on Application::EVENT_BEFORE_REQUEST so a
     * long-lived process starts each request with nothing carried over.
     */
    public static function resetRequestCache(): void
    {
        self::$parentIdByEntry = [];
        self::$parentEntries = [];
        self::$preloadedMessages = [];
        self::$preloadFloor = [];
        self::$messages = [];
        self::$canReply = [];
    }

    /**
     * Persists the reply link for an entry mail has just inserted.
     *
     * Called from the MessageEntry after-insert hook, i.e. inside the same request that posted
     * the reply. Anything unexpected is ignored silently: a failed link must never break
     * sending a message, and must never tell the caller whether some other entry id exists.
     */
    public static function saveFromRequest(MessageEntry $entry): void
    {
        if (!Yii::$app instanceof WebApplication) {
            return; // console / queue: there is no request to read a target from
        }

        $raw = Yii::$app->request->post(self::POST_PARAM);
        if ($raw === null || is_array($raw) || !is_scalar($raw)) {
            return; // absent, or an array smuggled in via parameter pollution
        }

        // Strict digits only. FILTER_VALIDATE_INT would also accept " 4671 " (PHP lets an int
        // carry surrounding whitespace); harmless in itself, since every authorisation check
        // below still runs, but there is no reason to accept a shape the client never sends.
        $value = (string)$raw;
        if ($value === '' || !preg_match('/^[1-9][0-9]{0,18}$/', $value)) {
            return; // "3x", "1e3", " 7 ", "0x12", "true", floats, 0, negatives: all rejected
        }
        $targetId = (int)$value;

        if (Yii::$app->user->isGuest) {
            return;
        }

        if ($targetId === (int)$entry->id) {
            return; // self-reference
        }

        if (!self::canReplyInMessage((int)$entry->message_id)) {
            return; // not a participant of this conversation, or blocked in it
        }

        $parent = MessageEntry::findOne(['id' => $targetId]);
        if ($parent === null) {
            return;
        }

        if ((int)$parent->message_id !== (int)$entry->message_id) {
            return; // quoting an entry from a different conversation
        }

        $link = new MessageEntryReply([
            'entry_id' => (int)$entry->id,
            'parent_entry_id' => $targetId,
            'message_id' => (int)$entry->message_id,
        ]);

        if (!$link->save()) {
            Yii::warning('mailreply: could not store reply link: ' . print_r($link->getErrors(), true), 'mailreply');
            return;
        }

        self::$parentIdByEntry[(int)$entry->id] = $targetId;
        self::$parentEntries[$targetId] = $parent;
    }

    /**
     * @param int[] $ids
     */
    private static function loadParents(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        /** @var MessageEntry[] $entries */
        $entries = MessageEntry::find()
            ->where(['id' => $ids])
            ->with('user')
            ->indexBy('id')
            ->all();

        foreach ($entries as $id => $parentEntry) {
            self::$parentEntries[(int)$id] = $parentEntry;
        }
    }
}
