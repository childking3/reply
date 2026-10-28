/**
 * Message Replies - client side.
 *
 * Adds no behaviour to mail's own files: the reply target is carried in a hidden input this
 * script adds to mail's existing reply form, which mail's client.submit() then posts along
 * (the same trick mail itself uses for its 'showDateBadge' field).
 */
humhub.module('mailreply', function (module, require, $) {

    var TARGET_FIELD = 'mailreplyTarget';
    var PREVIEW_LENGTH = 120;

    /**
     * jQuery normalises data-* names, but reading the attribute as a fallback removes any doubt
     * about which spelling is in play and survives a node that was cloned without jQuery data.
     */
    var dataOf = function ($el, name) {
        var v = $el.data(name);
        if (v === undefined || v === null || v === '') {
            v = $el.attr('data-' + name);
        }
        return v;
    };

    var getForm = function () {
        return $('.mail-message-form form').first();
    };

    var getEntry = function (entryId) {
        return $('.mail-conversation-entry[data-entry-id="' + entryId + '"]').first();
    };

    /**
     * Author and text of an entry, read from the DOM so no request is needed. The server
     * re-renders the quote from its own copy of the entry when the reply is saved, so nothing
     * read here is ever trusted as content.
     */
    var describeEntry = function ($entry) {
        var author = $.trim($entry.find('.author-label').first().text());

        var $content = $entry.find('.markdown-render').first().clone();
        $content.find('.mailreply-quote').remove(); // never quote a quote
        var text = $.trim($content.text());

        return {
            author: author || module.text('label.you'),
            text: text.length > PREVIEW_LENGTH ? text.substring(0, PREVIEW_LENGTH) + '...' : text
        };
    };

    var setTargetField = function (entryId) {
        var $form = getForm();
        if (!$form.length) {
            return;
        }

        var $input = $form.find('input[name="' + TARGET_FIELD + '"]');
        if (!$input.length) {
            $input = $('<input>').attr({type: 'hidden', name: TARGET_FIELD}).appendTo($form);
        }

        $input.val(entryId);
    };

    var clearTargetField = function () {
        getForm().find('input[name="' + TARGET_FIELD + '"]').remove();
    };

    var showComposer = function (entryId, description) {
        var $form = getForm();
        if (!$form.length) {
            return;
        }

        removeComposer();

        var $cancel = $('<button>')
            .attr({type: 'button', 'aria-label': module.text('label.cancel'), title: module.text('label.cancel')})
            .addClass('mailreply-composer-cancel')
            .attr('data-action-click', 'mailreply.cancelReply')
            .text('×');

        var $body = $('<div>').addClass('mailreply-composer-body')
            .append($('<span>').addClass('mailreply-composer-title')
                .text(module.text('label.replyingTo').replace('{name}', description.author)))
            .append($('<span>').addClass('mailreply-composer-text').text(description.text));

        $('<div>')
            .addClass('mailreply-composer')
            .attr('data-mailreply-pending', entryId)
            .append($body)
            .append($cancel)
            .prependTo($form);
    };

    var removeComposer = function (animated) {
        var $composer = $('.mailreply-composer');
        if (!$composer.length) {
            return;
        }

        if (!animated) {
            $composer.remove();
            return;
        }

        // Let the slide-out finish before the node goes. The timeout is the fallback for
        // browsers (or reduced-motion settings) where no animation event ever fires.
        $composer.addClass('closing').one('animationend', function () {
            $(this).remove();
        });
        setTimeout(function () {
            $composer.remove();
        }, 400);
    };

    var focusEditor = function () {
        var $editor = getForm().find('.ProsemirrorEditor').first();
        if ($editor.length) {
            var widget = require('ui.widget').Widget.instance($editor);
            if (widget && widget.focus) {
                widget.focus();
            }
        }
    };

    /**
     * data-action-click handler on the per-entry Reply action.
     */
    var startReply = function (evt) {
        var entryId = dataOf(evt.$trigger, 'mailreply-entry');
        var $entry = getEntry(entryId);
        if (!entryId || !$entry.length) {
            return;
        }

        setTargetField(entryId);
        showComposer(entryId, describeEntry($entry));
        focusEditor();
    };

    /**
     * data-action-click handler on the cancel button of the composer strip.
     */
    var cancelReply = function () {
        removeComposer(true);
        clearTargetField();
    };

    /**
     * data-action-click handler on a quoted block: jump to the original message.
     */
    var jumpToEntry = function (evt) {
        var entryId = dataOf(evt.$trigger, 'mailreply-parent');
        var $target = getEntry(entryId);

        if (!$target.length) {
            module.log.info(module.text('info.notLoaded'), true);
            return;
        }

        if ($target[0].scrollIntoView) {
            $target[0].scrollIntoView({block: 'center', behavior: 'smooth'});
        }

        $target.removeClass('mailreply-flash');
        // force the animation to restart even on a repeated jump to the same entry
        void $target[0].offsetWidth;
        $target.addClass('mailreply-flash');
        setTimeout(function () {
            $target.removeClass('mailreply-flash');
        }, 1300);
    };

    /**
     * Touch devices only: show a message's actions when it is tapped, then hide them again.
     * On a phone :hover either never fires or sticks after a tap, so mail's hover-reveal cannot
     * be relied on - and leaving the actions permanently visible clutters every message.
     */
    var TAP_REVEAL_MS = 3200;
    var revealTimer = null;

    var isTouch = function () {
        return window.matchMedia && window.matchMedia('(hover: none)').matches;
    };

    var revealActions = function ($entry) {
        $('.mail-conversation-entry.mailreply-actions-visible').not($entry).removeClass('mailreply-actions-visible');
        $entry.addClass('mailreply-actions-visible');
        clearTimeout(revealTimer);
        revealTimer = setTimeout(function () {
            $entry.removeClass('mailreply-actions-visible');
        }, TAP_REVEAL_MS);
    };

    var init = function () {
        if (isTouch()) {
            $(document).off('click.mailreplytap').on('click.mailreplytap', '.mail-conversation-entry', function (e) {
                // A tap on the actions themselves must not restart or cancel anything - let the
                // button do its job.
                if ($(e.target).closest('.conversation-menu').length) {
                    return;
                }
                revealActions($(this));
            });

            // Tapping outside the conversation hides them again straight away.
            $(document).off('click.mailreplytapout').on('click.mailreplytapout', function (e) {
                if (!$(e.target).closest('.mail-conversation-entry').length) {
                    clearTimeout(revealTimer);
                    $('.mail-conversation-entry.mailreply-actions-visible').removeClass('mailreply-actions-visible');
                }
            });
        }

        // The quoted block is a div with role="button", so Enter and Space have to be wired up
        // by hand - a click handler alone leaves keyboard users unable to jump to the original.
        $(document).off('keydown.mailreply').on('keydown.mailreply', '.mailreply-quote', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        // mail clears its rich text editor with .trigger('clear') only after a reply was
        // actually accepted, so this is the exact moment the pending quote is done with.
        $(document).off('clear.mailreply').on('clear.mailreply', '.mail-message-form .ProsemirrorEditor', function () {
            removeComposer(true);
            clearTargetField();
        });
    };

    module.export({
        init: init,
        startReply: startReply,
        cancelReply: cancelReply,
        jumpToEntry: jumpToEntry
    });
});
