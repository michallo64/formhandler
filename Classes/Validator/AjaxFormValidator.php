<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Typoheads\Formhandler\Validator\ErrorCheck\AbstractErrorCheck;

class AjaxFormValidator extends AbstractValidator {
  private string $formValuesPrefix = '';

  public function loadConfig(): void {
    $tsConfig = $this->utilityFuncs->parseConditionsBlock((array) ($this->globals->getSession()?->get('settings') ?? []), $this->gp);
    $this->formValuesPrefix = strval($tsConfig['formValuesPrefix'] ?? '');
    $this->settings = [];
    $this->validators = (array) ($tsConfig['validators.'] ?? []);
    if ($tsConfig['ajax.']) {
      $this->settings['ajax.'] = $tsConfig['ajax.'];
    }
  }

  public function validate(array &$errors): bool {
    return true;
  }

  public function validateAjax(string $field, array $gp, array &$errors): bool {
    return true;
  }

  public function validateAjaxForm(array $gp, array &$errors): bool {
    $this->gp = $gp;
    $this->loadConfig();
    if ($this->validators) {
      foreach ($this->validators as $idx => $settings) {
        if (is_array($settings) && is_array($settings['config.'] ?? null)) {
          $this->settings = $settings['config.'];
        }
      }
    }

    if (!is_array($this->settings['fieldConf.'])) {
      return true;
    }

    $this->disableErrorCheckFields = $this->getDisableErrorCheckFields();
    $this->restrictErrorChecks = $this->getRestrictedErrorChecks();

    if (!in_array('all', array_keys($this->disableErrorCheckFields))) {
      $errors = $this->validateRecursive($errors, $this->gp, (array) $this->settings['fieldConf.']);
    }

    return empty($errors);
  }

  /**
   * @param array<string, mixed> $gp
   * @param array<string, mixed> $errors
   * @param array<string, mixed> $fieldConfig
   *
   * @return array<string, mixed>
   */
  protected function validateRecursive(array $errors, array $gp, array $fieldConfig, ?string $rootField = null, string $fieldPath = '', string $fieldSelector = ''): array {
    foreach ($fieldConfig as $key => $fieldSettings) {
      $fieldName = trim($key, '.');
      $fieldPathTemp = !empty($fieldPath) ? $fieldPath.'|'.$fieldName : $fieldName;

      if (!array_key_exists($fieldName, $gp)) {
        $this->utilityFuncs->debugMessage('missing_field_for_error_check', [$fieldName], 2);
      }

      $errorFieldName = (null === $rootField) ? $fieldName : $rootField;

      if (is_array($fieldSettings)) {
        $tempSettings = $fieldSettings;
        unset($tempSettings['errorCheck.']);
        if (count($tempSettings)) {
          if ('fieldArray' == $fieldName) {
            foreach ($gp as $arrayKey => $arrayValue) {
              $fieldSelectorTemp = !empty($fieldSelector) ? $fieldSelector.']['.$arrayKey : $arrayKey;

              $errorsTemp = $this->validateRecursive([], (array) $arrayValue, $tempSettings, strval($arrayKey), $fieldPathTemp, $fieldSelectorTemp);
              if (!empty($errorsTemp)) {
                $errors[$arrayKey] = $errorsTemp;
              }
            }
          } else {
            $fieldSelectorTemp = !empty($fieldSelector) ? $fieldSelector.']['.$fieldName : $fieldName;
            $errorsTemp = $this->validateRecursive([], (array) ($gp[$fieldName] ?? []), $tempSettings, $fieldName, $fieldPathTemp, $fieldSelectorTemp);
            if (!empty($errorsTemp)) {
              $errors[$fieldName] = $errorsTemp;
            }
          }
        }

        if (!is_array($fieldSettings['errorCheck.'] ?? null)) {
          continue;
        }

        $counter = 0;
        $errorChecks = [];

        // set required to first position if set
        foreach ($fieldSettings['errorCheck.'] as $checkKey => $check) {
          if (!strstr((string) $checkKey, '.')) {
            if (!strcmp($check, 'required') || !strcmp($check, 'file_required')) {
              $errorChecks[$counter]['check'] = $check;
              unset($fieldSettings['errorCheck.'][$checkKey]);
              ++$counter;
            }
          }
        }

        // set other errorChecks
        foreach ($fieldSettings['errorCheck.'] as $checkKey => $check) {
          if (!strstr((string) $checkKey, '.') && strlen(trim($check)) > 0) {
            $errorChecks[$counter]['check'] = $check;
            if (isset($fieldSettings['errorCheck.'][$checkKey.'.']) && is_array($fieldSettings['errorCheck.'][$checkKey.'.'])) {
              $errorChecks[$counter]['params'] = $fieldSettings['errorCheck.'][$checkKey.'.'];
            }
            ++$counter;
          }
        }

        // foreach error checks
        foreach ($errorChecks as $check) {
          // Skip error check if the check is disabled for this field or if all checks are disabled for this field
          if (!empty($this->disableErrorCheckFields)
            && (in_array($errorFieldName, array_keys($this->disableErrorCheckFields)) || in_array($fieldPathTemp, array_keys($this->disableErrorCheckFields)))
            && (
              (
                in_array($errorFieldName, array_keys($this->disableErrorCheckFields)) && is_array($this->disableErrorCheckFields[$errorFieldName]) && in_array($check['check'], $this->disableErrorCheckFields[$errorFieldName])
              )
              || empty($this->disableErrorCheckFields[$errorFieldName])
            )
          ) {
            continue;
          }
          $classNameFix = ucfirst($check['check']);
          if (false === strpos($classNameFix, 'Tx_') && false === strpos($classNameFix, '\\')) {
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

          if (empty($this->restrictErrorChecks) || in_array($check['check'], $this->restrictErrorChecks)) {
            $this->utilityFuncs->debugMessage('calling_class', [$fullClassName]);
            $errorCheckObject->init($gp, $check);
            $errorCheckObject->setFormFieldName($fieldName);
            if ($errorCheckObject->validateConfig()) {
              $checkFailed = $errorCheckObject->check();
              if (strlen($checkFailed) > 0) {
                if (!isset($errors[$fieldName]) || !is_array($errors[$fieldName])) {
                  $errors[$fieldName] = [];
                }
                $errors[$fieldName][] = [
                  'fieldSelector' => $this->formValuesPrefix.(empty($fieldSelector) ? '' : '['.$fieldSelector.']').'['.$fieldName.']',
                  'failed' => $checkFailed,
                  'message' => $this->getErrorMessage($fieldName.'_'.$check['check']),
                ];
              }
            } else {
              $this->utilityFuncs->throwException('Configuration is not valid for class "'.$fullClassName.'"!');
            }
          } else {
            $this->utilityFuncs->debugMessage('check_skipped', [$check['check']]);
          }
        }
      }
    }

    return $errors;
  }

  private function getErrorMessage(string $fieldName): string {
    $message = '';
    foreach ($this->globals->getLangFiles() as $subIdx => $langFile) {
      $temp = trim(LocalizationUtility::translate('LLL:'.$langFile.':error_'.$fieldName) ?? '');
      if (strlen($temp) > 0) {
        $message = $temp;
      }
    }

    return $message;
  }
}
