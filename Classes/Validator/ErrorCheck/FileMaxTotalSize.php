<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Validator\ErrorCheck;

/**
 * This script is part of the TYPO3 project - inspiring people to share!
 *
 * TYPO3 is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by
 * the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 */
class FileMaxTotalSize extends AbstractErrorCheck {
  public function check(): string {
    $checkFailed = '';
    $maxSize = intval($this->utilityFuncs->getSingle((array) ($this->settings['params'] ?? []), 'maxTotalSize'));
    $size = 0;

    // first we check earlier uploaded files
    $olderFiles = (array) ($this->globals->getSession()?->get('files') ?? []);
    if (isset($olderFiles[$this->formFieldName]) && is_array($olderFiles[$this->formFieldName])) {
      foreach ($olderFiles[$this->formFieldName] as $olderFile) {
        $size += (int) $olderFile['size'];
      }
    }

    // last we check currently uploaded file
    foreach ($_FILES as $sthg => &$files) {
      if (!is_array($files['name'][$this->formFieldName])) {
        $files['name'][$this->formFieldName] = [$files['name'][$this->formFieldName]];
      }
      if (strlen($files['name'][$this->formFieldName][0]) > 0 && $maxSize) {
        if (!is_array($files['size'][$this->formFieldName])) {
          $files['size'][$this->formFieldName] = [$files['size'][$this->formFieldName]];
        }
        foreach ($files['size'][$this->formFieldName] as $fileSize) {
          $size += $fileSize;
        }
        if ($size > $maxSize) {
          unset($files);
          $checkFailed = $this->getCheckFailed();
        }
      }
    }

    return $checkFailed;
  }

  public function init(array $gp, array $settings): void {
    parent::init($gp, $settings);
    $this->mandatoryParameters = ['maxTotalSize'];
  }
}
