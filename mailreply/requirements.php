<?php

/**
 * Checked by HumHub before this module is enabled (ModuleService::checkRequirements) and before a
 * marketplace install (OnlineModuleManager::checkRequirements). Returning a string aborts with
 * that message.
 *
 * It must NOT ask the module manager whether mail is enabled: at the point this file runs during
 * `module/enable`, no modules are registered yet - hasModule('mail') is false and the enabled
 * list is empty even on a system where Messenger is installed and running (verified by probe,
 * 2026-10-25). Asking it that way blocked this module from ever being enabled.
 *
 * The module_enabled table is the source of truth and is readable at any point. Any failure here
 * fails OPEN: a requirements file must never be the reason a working system cannot install a
 * module.
 */

use yii\db\Query;

try {
    $mailEnabled = (new Query())
        ->from('module_enabled')
        ->where(['module_id' => 'mail'])
        ->exists(Yii::$app->db);
} catch (\Throwable $e) {
    return true; // cannot tell - do not stand in the way
}

if (!$mailEnabled) {
    return 'The Messenger (mail) module has to be installed and enabled before Message Replies can be used.';
}

return true;
