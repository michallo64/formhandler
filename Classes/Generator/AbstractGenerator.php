<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Generator;

use Typoheads\Formhandler\Component\AbstractComponent;

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
 * Abstract generator class for Formhandler.
 */
abstract class AbstractGenerator extends AbstractComponent {
  protected string $filename = '';

  protected string $filenameOnly = '';

  /** @var array<string, mixed> */
  protected array $formhandlerSettings = [];

  protected string $outputPath = '';

  /**
   * Returns the link with the right action set to be used in Finisher_SubmittedOK.
   *
   * @param array<string, mixed> $linkGP The GET parameters to set
   *
   * @return string The link
   */
  public function getLink(array $linkGP = []): string {
    $text = $this->getLinkText();

    $params = $this->getDefaultLinkParams();
    $componentParams = $this->getComponentLinkParams($linkGP);
    if (is_array($componentParams)) {
      $params = $this->utilityFuncs->mergeConfiguration($params, $componentParams);
    }

    return $this->cObj->getTypolink($text, $GLOBALS['TSFE']->id, $params, $this->getLinkTarget());
  }

  /**
   * Returns specific link parameters for a generator.
   *
   * @param array<string, mixed> $linkGP the link parameters set before
   *
   * @return array<string, mixed> The parameters
   */
  abstract protected function getComponentLinkParams(array $linkGP): array;

  /**
   * Returns the default link parameters of a generator containing the timestamp and hash of the log record.
   *
   * @return array<string, mixed> The default parameters
   */
  protected function getDefaultLinkParams(): array {
    $prefix = $this->globals->getFormValuesPrefix();
    $tempParams = [
      'tstamp' => $this->globals->getSession()?->get('inserted_tstamp'),
      'hash' => $this->globals->getSession()?->get('unique_hash'),
    ];
    $params = [];
    if ($prefix) {
      $params[$prefix] = $tempParams;
    } else {
      $params = $tempParams;
    }

    if (isset($this->settings['additionalParams.']) && is_array($this->settings['additionalParams.'])) {
      foreach ($this->settings['additionalParams.'] as $param => $value) {
        if (false === strpos($param, '.')) {
          if (isset($this->settings['additionalParams.'][$param.'.']) && is_array($this->settings['additionalParams.'][$param.'.'])) {
            $value = $this->utilityFuncs->getSingle($this->settings['additionalParams.'], $param);
          }
          $params[$param] = $value;
        }
      }
    }

    return $params;
  }

  /**
   * Returns the link target.
   *
   * @return string The link target
   */
  protected function getLinkTarget(): string {
    $target = $this->utilityFuncs->getSingle($this->settings, 'linkTarget');
    if (0 === strlen($target)) {
      $target = '_self';
    }

    return $target;
  }

  /**
   * Returns the link text.
   *
   * @return string The link text
   */
  protected function getLinkText(): string {
    $text = $this->utilityFuncs->getSingle($this->settings, 'linkText');
    if (0 === strlen($text)) {
      $text = 'Save';
    }

    return $text;
  }
}
