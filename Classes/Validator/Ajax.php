<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Typoheads\Formhandler\Validator\ErrorCheck\AbstractErrorCheck;

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
class Ajax extends AbstractValidator {
  public function loadConfig(): void {
    $tsConfig = $this->utilityFuncs->parseConditionsBlock((array) ($this->globals->getSession()?->get('settings') ?? []), $this->gp);
    $this->settings = [];
    $this->validators = (array) ($tsConfig['validators.'] ?? []);
    if ($tsConfig['ajax.']) {
      $this->settings['ajax.'] = $tsConfig['ajax.'];
    }
  }

  public function validate(array &$errors): bool {
    // Nothing to do here
    return true;
  }

  /**
   * Validates the submitted values using given settings.
   */
  public function validateAjax(string $field, array $gp, array &$errors): bool {
    $this->loadConfig();
    if ($this->validators) {
      foreach ($this->validators as $idx => $settings) {
        if (is_array($settings) && is_array($settings['config.'] ?? null)) {
          $this->settings = $settings['config.'];
        }
      }
    }
    if (is_array($this->settings['fieldConf.'])) {
      $disableErrorCheckFields = $this->getDisableErrorCheckFields();
      $restrictErrorChecks = $this->getRestrictedErrorChecks();

      $fieldSettings = $this->settings['fieldConf.'][$field.'.'];

      // parse error checks
      if (is_array($fieldSettings['errorCheck.'])) {
        $counter = 0;
        $errorChecks = [];

        // set required to first position if set
        foreach ($fieldSettings['errorCheck.'] as $key => $check) {
          if (!strstr(strval($key), '.') && strlen(trim($check)) > 0) {
            if (!strcmp($check, 'required') || !strcmp($check, 'file_required')) {
              $errorChecks[$counter]['check'] = $check;
              unset($fieldSettings['errorCheck.'][$key]);
              ++$counter;
            }
          }
        }

        // set other errorChecks
        foreach ($fieldSettings['errorCheck.'] as $key => $check) {
          if (!strstr(strval($key), '.')) {
            $errorChecks[$counter]['check'] = $check;
            if (is_array($fieldSettings['errorCheck.'][$key.'.'] ?? null)) {
              $errorChecks[$counter]['params'] = $fieldSettings['errorCheck.'][$key.'.'];
            }
            ++$counter;
          }
        }

        // foreach error checks
        foreach ($errorChecks as $idx => $check) {
          // Skip error check if the check is disabled for this field or if all checks are disabled for this field
          if (!empty($disableErrorCheckFields)
            && in_array('all', array_keys($disableErrorCheckFields))
            || (
              in_array($field, array_keys($disableErrorCheckFields))
                && (
                  (
                    is_array($this->disableErrorCheckFields[$field]) && in_array($check['check'], $this->disableErrorCheckFields[$field])
                  )
                  || empty($disableErrorCheckFields[$field])
                )
            )
          ) {
            continue;
          }

          $classNameFix = ucfirst($check['check']);
          if (false === strpos($classNameFix, 'Tx_')) {
            $fullClassName = $this->utilityFuncs->prepareClassName('\\Typoheads\\Formhandler\\Validator\\ErrorCheck\\'.$classNameFix);

            /** @var ?AbstractErrorCheck $errorCheckObject */
            $errorCheckObject = GeneralUtility::makeInstance($fullClassName);
          } else {
            // Look for the whole error check name, maybe it is a custom check like Tx_SomeExt_ErrorCheck_Something
            $fullClassName = $this->utilityFuncs->prepareClassName($check['check']);

            /** @var ?AbstractErrorCheck $errorCheckObject */
            $errorCheckObject = GeneralUtility::makeInstance($fullClassName);
          }
          if (null === $errorCheckObject) {
            $this->utilityFuncs->debugMessage('check_not_found', [$fullClassName], 2);

            continue;
          }
          if (empty($restrictErrorChecks) || in_array($check['check'], $restrictErrorChecks)) {
            $errorCheckObject->init($gp, $check);
            $errorCheckObject->setFormFieldName($field);
            if ($errorCheckObject->validateConfig()) {
              $checkFailed = $errorCheckObject->check();
              if (strlen($checkFailed) > 0) {
                $errorsField = (array) ($errors[$field] ?? []);
                $errorsField[] = $checkFailed;
                $errors[$field] = $errorsField;
              }
            } else {
              $this->utilityFuncs->throwException('Configuration is not valid for class "'.$fullClassName.'"!');
            }
          }
        }
      }
    }

    return empty($errors);
  }

  public function validateAjaxForm(array $gp, array &$errors): bool {
    return true;
  }
}
