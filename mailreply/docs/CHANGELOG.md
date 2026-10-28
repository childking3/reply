# Changelog

## 1.0.2 (2026-10-26)
- Fix: on phones the reply action and mail's edit pencil were permanently visible on every
  message. 1.0.1 forced the whole entry menu visible on touch because `:hover` is unreliable
  there; now a tap on a message reveals its actions for a few seconds and they fade out again,
  and a tap outside the conversation hides them immediately. Desktop hover behaviour is mail's,
  untouched.

## 1.0.1 (2026-10-25)
- Fix: a long quoted message overflowed the screen at phone widths. The quoted text was
  `white-space: nowrap`, which gave the message bubble an intrinsic width it could not shrink
  below; it now wraps and clamps to two lines.
- Fix: the reply action sat 8px past the right edge on a 360px screen. mail styles
  `.conversation-menu` with `padding-left: 35px` for the start of the row, and this module
  reorders it to the end; the padding is now applied on the correct side.
- Change: removed this module's own hover rules. mail already reveals the entry menu on `:hover`
  and `:focus-within`; only the `@media (hover: none)` override for touch devices is kept.

## 1.0.0 (2026-10-25)
- Reply to a single message inside a Messenger conversation, a reply action per
  message, a "replying to" strip while composing, the quoted message rendered inside the reply,
  and click-to-jump back to the original.
- Implemented purely through HumHub's documented extension points - no file of the mail module or
  of the core is modified. The module owns its own `mailreply_link` table and uninstalls cleanly.
