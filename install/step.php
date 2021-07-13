<?php
/**
 * @author RG. <rg.archuser@gmail.com>
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Context;

if (!check_bitrix_sessid()) {
    return false;
}

/** @global CMain $APPLICATION */
global $APPLICATION;

$message = new CAdminMessage('');

if ($ex = $APPLICATION->GetException()) {
    $message->ShowMessage(Loc::getMessage('MOD_INST_ERR'));
} else {
    $message->ShowNote(Loc::getMessage('MOD_INST_OK'));
}
?>
<form action="<?= Context::getCurrent()->getRequest()->getRequestedPage(); ?>">
    <input type="hidden" name="lang" value="<?= Context::getCurrent()->getLanguage(); ?>">
    <input type="submit" name="" value="<?= Loc::getMessage('MOD_BACK'); ?>">
</form>