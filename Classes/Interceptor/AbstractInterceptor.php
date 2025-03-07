<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Interceptor;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Typoheads\Formhandler\Component\AbstractComponent;
use Typoheads\Formhandler\Logger\AbstractLogger;

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

/**
 * Abstract interceptor class.
 */
abstract class AbstractInterceptor extends AbstractComponent {
  /**
   * Logs an action of an interceptor, e.g. if Interceptor_IPBlocking blocked a request.
   *
   * @param bool $markAsSpam Indicates if this was a blocked SPAM attempt. Will be highlighted in the backend module.
   */
  protected function log(bool $markAsSpam = false): void {
    $classesArray = $this->settings['loggers.'];
    if (isset($classesArray) && is_array($classesArray)) {
      foreach ($classesArray as $idx => $tsConfig) {
        $className = $this->utilityFuncs->getPreparedClassName($tsConfig);
        if (is_array($tsConfig) && !empty($className) && 1 !== (int) $this->utilityFuncs->getSingle($tsConfig, 'disable')) {
          $this->utilityFuncs->debugMessage('calling_class', [$className]);

          /** @var AbstractLogger $obj */
          $obj = GeneralUtility::makeInstance($className);
          if ($markAsSpam) {
            $tsConfig['config.']['markAsSpam'] = 1;
          }
          $obj->init($this->gp, $tsConfig['config.']);
          $obj->process();
        } else {
          $this->utilityFuncs->throwException('classesarray_error');
        }
      }
    }
  }
}
