<?php
/**
 * @author RG. <rg.archuser@gmail.com>
 */

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class proklung_rabbitmq
 */
class proklung_rabbitmq extends CModule
{
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_ID = 'proklung.rabbitmq';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('YC_RMQ_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('YC_RMQ_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('YC_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('YC_PARTNER_URI');
    }

    /**
     * @inheritDoc
     */
    public function DoInstall()
    {
        parent::DoInstall();

        /** @global CMain $APPLICATION */
        global $APPLICATION;

        if ($this->checkRequirements()) {
            ModuleManager::registerModule($this->MODULE_ID);
            Loader::includeModule($this->MODULE_ID);

            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
            $this->InstallTasks();
        } else {
            $APPLICATION->ThrowException(Loc::getMessage('YC_INSTALL_ERROR_REQUIREMENTS'));
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('YC_INSTALL_TITLE'),
            $this->getPath() . '/install/step.php'
        );
    }

    /**
     * @inheritDoc
     */
    public function DoUninstall()
    {
        parent::DoUninstall();

        /** @global CMain $APPLICATION */
        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();

        if (is_null($request->get('step')) || (int)$request->get('step') === 1) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('YC_UNINSTALL_TITLE'),
                $this->getPath() . '/install/unstep.php'
            );
        }

        if ((int)$request->get('step') === 2) {
            Loader::includeModule($this->MODULE_ID);

            if (is_null($request->get('savedata')) || $request->get('savedata') !== 'Y') {
                $this->UnInstallDB();
            }

            $this->UnInstallEvents();
            $this->UnInstallFiles();
            $this->UnInstallTasks();

            Loader::clearModuleCache($this->MODULE_ID);
            ModuleManager::unRegisterModule($this->MODULE_ID);
        }
    }

    /**
     * @return boolean
     */
    public function checkRequirements(): bool
    {
        return true;
    }

    /**
     * @param boolean $includeDocumentRoot
     *
     * @return string
     */
    public function getPath(bool $includeDocumentRoot = true): string
    {
        return $includeDocumentRoot
            ? dirname(__DIR__)
            : (string)str_ireplace(Application::getDocumentRoot(),'', dirname(__DIR__));
    }
}
