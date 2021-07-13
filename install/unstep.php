<?php
/**
 * @author RG. <rg.archuser@gmail.com>
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Context;

if (!check_bitrix_sessid()) {
    return false;
}

$message = new CAdminMessage('');
$context = Context::getCurrent();
?>
<form action="<?= $context->getRequest()->getRequestedPage(); ?>">
    <?= bitrix_sessid_post(); ?>
    <input type="hidden" name="lang" value="<?= $context->getLanguage(); ?>">
    <input type="hidden" name="id" value="<?= $context->getRequest()->get('id'); ?>">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <?php
    $message->ShowMessage(Loc::getMessage('MOD_UNINST_WARN'));
    ?>
    <p><?= Loc::getMessage('MOD_UNINST_SAVE'); ?></p>
    <p>
        <input type="checkbox" name="savedata" value="Y" id="savedata" checked>
        <label for="savedata"><?= Loc::getMessage('MOD_UNINST_SAVE_TABLES'); ?></label>
    </p>
    <input type="submit" name="" value="<?= Loc::getMessage('MOD_UNINST_DEL'); ?>">
</form>