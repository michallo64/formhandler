<?php
declare(strict_types=1);

namespace Typoheads\Formhandler\Ajax;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Typoheads\Formhandler\Component\Manager;
use Typoheads\Formhandler\Utility\Globals;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
*                                                                        *
* TYPO3 is free software; you can redistribute it and/or modify it under *
* the terms of the GNU General Public License version 2 as published by  *
* the Free Software Foundation.                                          *
*                                                                        *
* This script is distributed in the hope that it will be useful, but     *
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
* TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
* Public License for more details.                                       *
*                                                                        */

/**
 * A class removing uploaded files. This class is called via AJAX.
 */
class RemoveFile
{
    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var string
     */
    private $uploadedFileName;

    /**
     * @var Manager
     */
    private $componentManager;

    /**
     * @var Globals
     */
    private $globals;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Typoheads\Formhandler\Utility\GeneralUtility
     */
    private $utilityFuncs;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var array
     */
    private $langFiles;

    /**
     * Main method of the class.
     */
    public function main()
    {
        $this->init();
        $content = '';
        $field = null;

        if ($this->fieldName) {
            $sessionFiles = $this->globals->getSession()->get('files');
            if (is_array($sessionFiles)) {
                foreach ($sessionFiles as $field => $files) {
                    if (!strcmp($field, $this->fieldName)) {

                        //get upload folder
                        $uploadFolder = $this->utilityFuncs->getTempUploadFolder();

                        //build absolute path to upload folder
                        $uploadPath = $this->utilityFuncs->getTYPO3Root() . $uploadFolder;

                        $found = false;
                        foreach ($files as $key => &$fileInfo) {
                            if (!strcmp($fileInfo['uploaded_name'], $this->uploadedFileName)) {
                                $found = true;
                                unset($sessionFiles[$field][$key]);
                                if (file_exists($uploadPath . $fileInfo['uploaded_name'])) {
                                    unlink($uploadPath . $fileInfo['uploaded_name']);
                                }
                            }
                        }
                        if (!$found) {
                            foreach ($files as $key => &$fileInfo) {
                                if (!strcmp($fileInfo['name'], $this->uploadedFileName)) {
                                    unset($sessionFiles[$field][$key]);
                                    if (file_exists($uploadPath . $fileInfo['name'])) {
                                        unlink($uploadPath . $fileInfo['name']);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->globals->getSession()->set('files', $sessionFiles);

            // Add the content to or Result Box: #formResult
            if ($field !== null && is_array($sessionFiles) && !empty($sessionFiles[$field])) {
                $markers = [];
                $view = $this->componentManager->getComponent('View\\Form');
                $view->setSettings($this->settings);
                $view->fillFileMarkers($markers);
                $langMarkers = $this->utilityFuncs->getFilledLangMarkers($markers['###' . $this->fieldName . '_uploadedFiles###'], $this->langFiles);
                $markers['###' . $this->fieldName . '_uploadedFiles###'] = $this->globals->getCObj()->substituteMarkerArray($markers['###' . $this->fieldName . '_uploadedFiles###'], $langMarkers);
                $content = $markers['###' . $this->fieldName . '_uploadedFiles###'];
            }
        }
        print $content;
    }

    /**
     * Initialize the class. Read GET parameters
     */
    protected function init()
    {
        $this->fieldName = htmlspecialchars($_GET['field']);
        $this->uploadedFileName = htmlspecialchars($_GET['uploadedFileName']);
        if (isset($_GET['pid'])) {
            $this->id = (int)($_GET['pid']);
        } else {
            $this->id = (int)($_GET['id']);
        }

        $this->componentManager = GeneralUtility::makeInstance(Manager::class);
        $this->globals = GeneralUtility::makeInstance(Globals::class);
        $this->utilityFuncs = GeneralUtility::makeInstance(\Typoheads\Formhandler\Utility\GeneralUtility::class);
        $this->utilityFuncs->initializeTSFE($this->id);
        $this->globals->setCObj($GLOBALS['TSFE']->cObj);
        $randomID = htmlspecialchars(GeneralUtility::_GP('randomID'));
        $this->globals->setRandomID($randomID);

        if (!$this->globals->getSession()) {
            $ts = $GLOBALS['TSFE']->tmpl->setup['plugin.']['Tx_Formhandler.']['settings.'];
            $sessionClass = $this->utilityFuncs->getPreparedClassName($ts['session.'], 'Session\\PHP');
            $this->globals->setSession($this->componentManager->getComponent($sessionClass));
        }

        $this->settings = $this->globals->getSession()->get('settings');
        $this->langFiles = $this->utilityFuncs->readLanguageFiles([], $this->settings);

        //init ajax
        if ($this->settings['ajax.']) {
            $class = $this->utilityFuncs->getPreparedClassName($this->settings['ajax.'], 'AjaxHandler\\JQuery');
            $ajaxHandler = $this->componentManager->getComponent($class);
            $this->globals->setAjaxHandler($ajaxHandler);

            $ajaxHandler->init($this->settings['ajax.']['config.']);
            $ajaxHandler->initAjax();
        }
    }
}
